<?php

namespace App\Services\CashBank;

use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBankService
{
    public function __construct(
        private readonly PaymentBankMethodPolicy $paymentMethods,
        private readonly ExpenseBankMethodPolicy $expenseMethods,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function activeAccount(bool $lock = false): BankAccount
    {
        return BankAccount::query()
            ->active()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->orderBy('id')
            ->firstOrFail();
    }

    public function recordPayment(Payment $payment): ?CashBankTransaction
    {
        return DB::transaction(function () use ($payment): ?CashBankTransaction {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($lockedPayment->status !== Payment::STATUS_VERIFIED
                || ! $this->paymentMethods->isBankMethod($lockedPayment->method)) {
                return null;
            }

            $existing = CashBankTransaction::query()
                ->where('source_type', CashBankTransaction::SOURCE_PAYMENT)
                ->where('source_id', $lockedPayment->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $account = $this->activeAccount(lock: true);
            $lockedPayment->loadMissing('invoice');

            return CashBankTransaction::query()->create([
                'bank_account_id' => $account->getKey(),
                'transaction_number' => $this->nextTransactionNumber($account, CashBankTransaction::TYPE_INCOME, $lockedPayment->payment_date->format('Ym')),
                'transaction_date' => $lockedPayment->payment_date,
                'type' => CashBankTransaction::TYPE_INCOME,
                'category' => 'invoice_payment',
                'amount' => $lockedPayment->amount,
                'description' => 'Pembayaran Invoice '.$lockedPayment->invoice?->invoice_number,
                'source_type' => CashBankTransaction::SOURCE_PAYMENT,
                'source_id' => $lockedPayment->getKey(),
                'status' => CashBankTransaction::STATUS_POSTED,
                'created_by' => $lockedPayment->recorded_by,
            ]);
        });
    }

    public function recordExpense(Expense $expense): ?CashBankTransaction
    {
        return $this->syncExpense($expense);
    }

    public function syncExpense(Expense $expense): ?CashBankTransaction
    {
        return DB::transaction(function () use ($expense): ?CashBankTransaction {
            $lockedExpense = Expense::withTrashed()->lockForUpdate()->findOrFail($expense->getKey());
            $transaction = CashBankTransaction::query()
                ->where('source_type', CashBankTransaction::SOURCE_EXPENSE)
                ->where('source_id', $lockedExpense->getKey())
                ->lockForUpdate()
                ->first();
            $isBank = ! $lockedExpense->trashed() && $this->expenseMethods->isBankMethod($lockedExpense->payment_method);

            if (! $isBank) {
                if ($transaction && $transaction->status === CashBankTransaction::STATUS_POSTED) {
                    $this->markCancelled($transaction, auth()->id());
                }

                return $transaction?->refresh();
            }

            if ($transaction) {
                $transaction->forceFill([
                    'transaction_date' => $lockedExpense->expense_date,
                    'category' => $lockedExpense->category,
                    'amount' => $lockedExpense->amount,
                    'description' => $lockedExpense->description,
                    'status' => CashBankTransaction::STATUS_POSTED,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                ])->save();

                return $transaction->refresh();
            }

            $account = $this->activeAccount(lock: true);

            return CashBankTransaction::query()->create([
                'bank_account_id' => $account->getKey(),
                'transaction_number' => $this->nextTransactionNumber($account, CashBankTransaction::TYPE_EXPENSE, $lockedExpense->expense_date->format('Ym')),
                'transaction_date' => $lockedExpense->expense_date,
                'type' => CashBankTransaction::TYPE_EXPENSE,
                'category' => $lockedExpense->category,
                'amount' => $lockedExpense->amount,
                'description' => $lockedExpense->description,
                'source_type' => CashBankTransaction::SOURCE_EXPENSE,
                'source_id' => $lockedExpense->getKey(),
                'status' => CashBankTransaction::STATUS_POSTED,
                'created_by' => $lockedExpense->created_by,
            ]);
        });
    }

    public function cancelPaymentTransaction(Payment $payment, ?int $cancelledBy = null): ?CashBankTransaction
    {
        return $this->cancelSourceTransaction(CashBankTransaction::SOURCE_PAYMENT, $payment->getKey(), $cancelledBy);
    }

    public function cancelExpenseTransaction(Expense $expense, ?int $cancelledBy = null): ?CashBankTransaction
    {
        return $this->cancelSourceTransaction(CashBankTransaction::SOURCE_EXPENSE, $expense->getKey(), $cancelledBy);
    }

    /** @param array<string, mixed> $data */
    public function recordManualTransaction(array $data, ?int $createdBy = null): CashBankTransaction
    {
        return DB::transaction(function () use ($data, $createdBy): CashBankTransaction {
            $account = $this->activeAccount(lock: true);
            $date = Carbon::parse($data['transaction_date']);
            $transaction = CashBankTransaction::query()->create([
                'bank_account_id' => $account->getKey(),
                'transaction_number' => $this->nextTransactionNumber($account, $data['type'], $date->format('Ym')),
                'transaction_date' => $date->toDateString(),
                'type' => $data['type'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'source_type' => CashBankTransaction::SOURCE_MANUAL,
                'source_id' => null,
                'status' => CashBankTransaction::STATUS_POSTED,
                'created_by' => $createdBy,
            ]);

            $this->activityLogger->record(
                module: 'cash_bank',
                action: $data['type'] === CashBankTransaction::TYPE_INCOME ? 'manual_income_created' : 'manual_expense_created',
                event: 'Manual cash bank transaction created',
                description: "Transaksi manual {$transaction->transaction_number} dibuat.",
                subject: $transaction,
                metadata: $this->snapshot($transaction),
                riskLevel: ActivityLog::RISK_MEDIUM,
            );

            return $transaction;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateManualTransaction(CashBankTransaction $transaction, array $data): CashBankTransaction
    {
        return DB::transaction(function () use ($transaction, $data): CashBankTransaction {
            $locked = CashBankTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $this->assertEditableManual($locked);
            $before = $this->snapshot($locked);
            $newType = $data['type'] ?? $locked->type;
            $newDate = Carbon::parse($data['transaction_date'] ?? $locked->transaction_date);

            if ($newType !== $locked->type || $newDate->format('Ym') !== $locked->transaction_date->format('Ym')) {
                $account = BankAccount::query()->lockForUpdate()->findOrFail($locked->bank_account_id);
                $data['transaction_number'] = $this->nextTransactionNumber($account, $newType, $newDate->format('Ym'));
            }

            $locked->update($data);

            $this->activityLogger->record(
                module: 'cash_bank', action: 'manual_transaction_updated', event: 'Manual cash bank transaction updated',
                description: "Transaksi manual {$locked->transaction_number} diperbarui.", subject: $locked,
                metadata: ['before' => $before, 'after' => $this->snapshot($locked)], riskLevel: ActivityLog::RISK_MEDIUM,
            );

            return $locked->refresh();
        });
    }

    public function cancelTransaction(CashBankTransaction $transaction, ?int $cancelledBy = null): CashBankTransaction
    {
        return DB::transaction(function () use ($transaction, $cancelledBy): CashBankTransaction {
            $locked = CashBankTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $this->assertEditableManual($locked);
            $this->markCancelled($locked, $cancelledBy);

            $this->activityLogger->record(
                module: 'cash_bank', action: 'transaction_cancelled', event: 'Cash bank transaction cancelled',
                description: "Transaksi {$locked->transaction_number} dibatalkan.", subject: $locked,
                metadata: $this->snapshot($locked), riskLevel: ActivityLog::RISK_HIGH,
            );

            return $locked->refresh();
        });
    }

    public function calculateBalance(BankAccount $account): float
    {
        $net = (float) $account->transactions()->posted()
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS net', [CashBankTransaction::TYPE_INCOME])
            ->value('net');

        return round((float) $account->opening_balance + $net, 2);
    }

    public function balanceBefore(BankAccount $account, ?string $date): float
    {
        if (! $date) {
            return (float) $account->opening_balance;
        }

        $net = (float) $account->transactions()->posted()
            ->whereDate('transaction_date', '<', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS net', [CashBankTransaction::TYPE_INCOME])
            ->value('net');

        return round((float) $account->opening_balance + $net, 2);
    }

    public function runningBalanceAt(BankAccount $account, CashBankTransaction $transaction): float
    {
        $net = (float) $account->transactions()->posted()
            ->where(function ($query) use ($transaction): void {
                $query->whereDate('transaction_date', '<', $transaction->transaction_date)
                    ->orWhere(function ($query) use ($transaction): void {
                        $query->whereDate('transaction_date', $transaction->transaction_date)
                            ->where('id', '<=', $transaction->getKey());
                    });
            })
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS net', [CashBankTransaction::TYPE_INCOME])
            ->value('net');

        return round((float) $account->opening_balance + $net, 2);
    }

    private function nextTransactionNumber(BankAccount $account, string $type, string $period): string
    {
        $prefix = $type === CashBankTransaction::TYPE_INCOME ? 'KB-IN' : 'KB-OUT';
        $base = "{$prefix}-{$period}-";
        $last = CashBankTransaction::query()
            ->where('bank_account_id', $account->getKey())
            ->where('transaction_number', 'like', $base.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function cancelSourceTransaction(string $sourceType, int $sourceId, ?int $cancelledBy): ?CashBankTransaction
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $cancelledBy): ?CashBankTransaction {
            $transaction = CashBankTransaction::query()
                ->where('source_type', $sourceType)->where('source_id', $sourceId)->lockForUpdate()->first();

            if ($transaction && $transaction->status === CashBankTransaction::STATUS_POSTED) {
                $this->markCancelled($transaction, $cancelledBy);
            }

            return $transaction?->refresh();
        });
    }

    private function markCancelled(CashBankTransaction $transaction, ?int $cancelledBy): void
    {
        $transaction->forceFill([
            'status' => CashBankTransaction::STATUS_CANCELLED,
            'cancelled_at' => $transaction->cancelled_at ?? now(),
            'cancelled_by' => $cancelledBy,
        ])->save();
    }

    private function assertEditableManual(CashBankTransaction $transaction): void
    {
        if (! $transaction->isManual()) {
            throw ValidationException::withMessages(['transaction' => 'Mutasi otomatis tidak dapat diubah dari transaksi manual.']);
        }

        if ($transaction->status === CashBankTransaction::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['transaction' => 'Transaksi yang sudah dibatalkan tidak dapat diubah.']);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(CashBankTransaction $transaction): array
    {
        return [
            'transaction_number' => $transaction->transaction_number,
            'transaction_date' => $transaction->transaction_date?->toDateString(),
            'type' => $transaction->type,
            'category' => $transaction->category,
            'amount' => (string) $transaction->amount,
            'description' => $transaction->description,
            'status' => $transaction->status,
        ];
    }
}
