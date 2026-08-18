<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCashBankTransactionsRequest;
use App\Http\Requests\StoreManualCashBankTransactionRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Requests\UpdateManualCashBankTransactionRequest;
use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Services\CashBank\CashBankService;
use App\Services\Security\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CashBankController extends Controller
{
    public function summary(CashBankService $cashBank): JsonResponse
    {
        $account = $cashBank->activeAccount();
        $monthStart = today()->startOfMonth();
        $monthEnd = today()->endOfMonth();
        $base = $account->transactions()->posted()
            ->whereBetween('transaction_date', [$monthStart, $monthEnd]);
        $income = (float) (clone $base)->where('type', CashBankTransaction::TYPE_INCOME)->sum('amount');
        $expense = (float) (clone $base)->where('type', CashBankTransaction::TYPE_EXPENSE)->sum('amount');
        $balance = $cashBank->calculateBalance($account);

        return response()->json(['data' => [
            'account_id' => $account->getKey(),
            'account_name' => $account->name,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'opening_balance' => (float) $account->opening_balance,
            'current_balance' => $balance,
            'income_this_month' => $income,
            'expense_this_month' => $expense,
            'net_cash_flow' => $income - $expense,
            'has_negative_balance' => $balance < 0,
        ]]);
    }

    public function index(ListCashBankTransactionsRequest $request, CashBankService $cashBank): JsonResponse
    {
        $filters = $request->validated();
        $account = $cashBank->activeAccount();
        $search = trim($filters['search'] ?? '');
        $query = $account->transactions()
            ->with('creator:id,name')
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('transaction_date', '<=', $date))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('type', $type))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $method): Builder => $query->where('payment_method', $method))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('transaction_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            });
        $paginator = $query->orderBy('transaction_date')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $runningBalances = $cashBank->runningBalancesFor($account, $paginator->items());
        $rows = collect($paginator->items())->map(fn (CashBankTransaction $transaction): array => $this->serialize(
            $transaction,
            $runningBalances[$transaction->getKey()],
        ));

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'beginning_balance' => $cashBank->balanceBefore($account, $filters['date_from'] ?? null),
                'filters' => $filters,
            ],
        ]);
    }

    public function store(StoreManualCashBankTransactionRequest $request, CashBankService $cashBank): JsonResponse
    {
        $transaction = $cashBank->recordManualTransaction($request->validated(), $request->user()?->getAuthIdentifier());

        return response()->json([
            'message' => 'Transaksi Kas & Bank berhasil ditambahkan.',
            'data' => $this->serialize($transaction, $cashBank->runningBalanceAt($transaction->bankAccount, $transaction)),
        ], 201);
    }

    public function update(
        UpdateManualCashBankTransactionRequest $request,
        CashBankTransaction $transaction,
        CashBankService $cashBank,
    ): JsonResponse {
        $updated = $cashBank->updateManualTransaction($transaction, $request->validated());

        return response()->json([
            'message' => 'Transaksi manual berhasil diperbarui.',
            'data' => $this->serialize($updated, $cashBank->runningBalanceAt($updated->bankAccount, $updated)),
        ]);
    }

    public function destroy(CashBankTransaction $transaction, CashBankService $cashBank): JsonResponse
    {
        $cancelled = $cashBank->cancelTransaction($transaction, auth()->id());

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data' => $this->serialize($cancelled, $cashBank->runningBalanceAt($cancelled->bankAccount, $cancelled)),
        ]);
    }

    public function updateAccount(
        UpdateBankAccountRequest $request,
        CashBankService $cashBank,
        ActivityLogger $activityLogger,
    ): JsonResponse {
        $account = DB::transaction(function () use ($request, $cashBank, $activityLogger): BankAccount {
            $account = $cashBank->activeAccount(lock: true);
            $before = $account->only(['name', 'bank_name', 'account_number', 'opening_balance']);
            $account->update($request->validated());
            $openingBalanceChanged = (string) $before['opening_balance'] !== (string) $account->opening_balance;

            $activityLogger->record(
                module: 'cash_bank',
                action: $openingBalanceChanged ? 'opening_balance_changed' : 'bank_account_updated',
                event: $openingBalanceChanged ? 'Opening balance changed' : 'Main bank account updated',
                description: 'Pengaturan rekening utama diperbarui.', subject: $account,
                metadata: ['before' => $before, 'after' => $account->only(['name', 'bank_name', 'account_number', 'opening_balance'])],
                riskLevel: ActivityLog::RISK_HIGH,
            );

            return $account;
        });

        return response()->json(['message' => 'Rekening utama berhasil diperbarui.', 'data' => [
            'id' => $account->getKey(),
            'name' => $account->name,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'opening_balance' => (float) $account->opening_balance,
        ]]);
    }

    /** @return array<string, mixed> */
    private function serialize(CashBankTransaction $transaction, float $runningBalance): array
    {
        return [
            'id' => $transaction->getKey(),
            'transaction_number' => $transaction->transaction_number,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'type' => $transaction->type,
            'type_label' => $transaction->type === CashBankTransaction::TYPE_INCOME ? 'Uang Masuk' : 'Uang Keluar',
            'category' => $transaction->category,
            'category_label' => $this->categoryLabel($transaction->category),
            'payment_method' => $transaction->payment_method,
            'payment_method_label' => $transaction->payment_method === CashBankTransaction::PAYMENT_METHOD_CASH ? 'Tunai' : 'Transfer',
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'source_type' => $transaction->source_type,
            'source_label' => match ($transaction->source_type) {
                CashBankTransaction::SOURCE_PAYMENT => 'Pembayaran Invoice',
                CashBankTransaction::SOURCE_EXPENSE => 'Pengeluaran',
                default => 'Manual',
            },
            'income' => $transaction->type === CashBankTransaction::TYPE_INCOME ? (float) $transaction->amount : 0,
            'expense' => $transaction->type === CashBankTransaction::TYPE_EXPENSE ? (float) $transaction->amount : 0,
            'running_balance' => $runningBalance,
            'status' => $transaction->status,
            'status_label' => $transaction->status === CashBankTransaction::STATUS_POSTED ? 'Tercatat' : 'Dibatalkan',
            'is_manual' => $transaction->isManual(),
            'created_by' => $transaction->creator?->name,
        ];
    }

    private function categoryLabel(string $category): string
    {
        return [
            'invoice_payment' => 'Pembayaran Invoice', 'owner_capital' => 'Modal Owner',
            'supplier_refund' => 'Refund Supplier', 'other_income' => 'Pendapatan Lain',
            'bank_fee' => 'Biaya Admin Bank', 'owner_withdrawal' => 'Penarikan Owner',
            'tax' => 'Pajak', 'operational_cost' => 'Biaya Operasional', 'balance_adjustment' => 'Koreksi Saldo',
        ][$category] ?? str($category)->replace('_', ' ')->title()->toString();
    }
}
