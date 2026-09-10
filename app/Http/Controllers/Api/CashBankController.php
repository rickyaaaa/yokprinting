<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListCashBankTransactionsRequest;
use App\Http\Requests\StoreManualCashBankTransactionRequest;
use App\Http\Requests\SummariseCashBankRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Requests\UpdateManualCashBankTransactionRequest;
use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\CashBankTransaction;
use App\Services\CashBank\CashBankService;
use App\Services\Security\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CashBankController extends Controller
{
    public function summary(SummariseCashBankRequest $request, CashBankService $cashBank): JsonResponse
    {
        $account = $cashBank->activeAccount();
        $filters = $request->validated();

        // The cards follow the Histori transaksi date filter, so the headline
        // numbers always describe the rows underneath them. With no filter set
        // they keep their original meaning: this calendar month.
        $isFiltered = filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null);
        $rangeStart = $filters['date_from'] ?? today()->startOfMonth()->toDateString();
        $rangeEnd = $filters['date_to'] ?? today()->endOfMonth()->toDateString();

        $base = $account->transactions()->posted()
            ->whereBetween('transaction_date', [$rangeStart, $rangeEnd]);
        $income = (float) (clone $base)->where('type', CashBankTransaction::TYPE_INCOME)->sum('amount');
        $expense = (float) (clone $base)->where('type', CashBankTransaction::TYPE_EXPENSE)->sum('amount');

        // Balance is a running total, not a per-period sum: with a range set
        // it is the balance as that period closed, so it reconciles with the
        // last running-balance figure in the table instead of jumping to a
        // live all-time number.
        $balance = $isFiltered
            ? $cashBank->balanceBefore($account, CarbonImmutable::parse($rangeEnd)->addDay()->toDateString())
            : $cashBank->calculateBalance($account);

        return response()->json(['data' => [
            'is_filtered' => $isFiltered,
            'range_from' => $rangeStart,
            'range_to' => $rangeEnd,
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
        // Default newest-first so the most recent activity (a payment/expense
        // just recorded) lands on page 1 instead of being buried behind
        // however many older rows exist - "oldest" is still available via
        // ?sort=oldest for chronological bank-statement-style reading.
        $sort = $filters['sort'] ?? 'latest';
        $direction = $sort === 'oldest' ? 'asc' : 'desc';

        $paginator = $query->orderBy('transaction_date', $direction)->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        // runningBalancesFor() is a merge-join that requires its input
        // chronologically ascending regardless of the page's display order -
        // sort a copy for the balance calculation, keep $paginator->items()
        // (already in the requested display order) for the response itself.
        // Balances are keyed by transaction id, so display order never
        // affects which balance lands on which row.
        $chronological = collect($paginator->items())->sortBy([
            ['transaction_date', 'asc'],
            ['id', 'asc'],
        ])->values();
        $runningBalances = $cashBank->runningBalancesFor($account, $chronological);
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
                'filters' => [...$filters, 'sort' => $sort],
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

    /**
     * Export the currently filtered transaction list (same filters as
     * index) as CSV - supports the same date_from/date_to period filter.
     */
    public function export(ListCashBankTransactionsRequest $request, CashBankService $cashBank, ReportCsvExport $export): Response
    {
        $filters = $request->validated();
        $account = $cashBank->activeAccount();
        $search = trim($filters['search'] ?? '');

        $transactions = $account->transactions()
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
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $rows = $transactions->map(fn (CashBankTransaction $transaction): array => [
            $transaction->transaction_number,
            $transaction->transaction_date->toDateString(),
            $transaction->type === CashBankTransaction::TYPE_INCOME ? 'Uang Masuk' : 'Uang Keluar',
            $this->categoryLabel($transaction->category),
            $transaction->payment_method === CashBankTransaction::PAYMENT_METHOD_CASH ? 'Tunai' : 'Transfer',
            $transaction->description,
            $transaction->type === CashBankTransaction::TYPE_INCOME ? (float) $transaction->amount : 0,
            $transaction->type === CashBankTransaction::TYPE_EXPENSE ? (float) $transaction->amount : 0,
            $transaction->status === CashBankTransaction::STATUS_POSTED ? 'Tercatat' : 'Dibatalkan',
        ]);

        return $export->download(
            'kas-bank-'.now()->format('Y-m-d').'.csv',
            ['Nomor', 'Tanggal', 'Tipe', 'Kategori', 'Metode', 'Keterangan', 'Uang Masuk', 'Uang Keluar', 'Status'],
            $rows,
        );
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
                CashBankTransaction::SOURCE_PURCHASE_PAYMENT => 'Pembayaran PO',
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
            CashBankTransaction::CATEGORY_PURCHASE_PAYMENT => 'Pembayaran PO',
            'bank_fee' => 'Biaya Admin Bank', 'owner_withdrawal' => 'Penarikan Owner',
            'tax' => 'Pajak', 'operational_cost' => 'Biaya Operasional', 'balance_adjustment' => 'Koreksi Saldo',
        ][$category] ?? str($category)->replace('_', ' ')->title()->toString();
    }
}
