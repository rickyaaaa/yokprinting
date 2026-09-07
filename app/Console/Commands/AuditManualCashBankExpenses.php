<?php

namespace App\Console\Commands;

use App\Models\CashBankTransaction;
use App\Models\Expense;
use Illuminate\Console\Command;

class AuditManualCashBankExpenses extends Command
{
    protected $signature = 'cashbank:audit-expense-candidates';

    protected $description = 'Report manual Kas & Bank outflows that look like business expenses but have no Expense record';

    /**
     * Read-only by design: it has no --apply flag at all.
     *
     * Manual transactions never carry a source_id, so none of them is linked
     * to an Expense and none reaches the profit & loss. This reports which of
     * them should have been expenses, which deliberately should not, and
     * which nobody can classify without asking - the free-text ones, since
     * the manual category field is validated only as a string.
     */
    public function handle(): int
    {
        $transactions = CashBankTransaction::query()
            ->where('source_type', CashBankTransaction::SOURCE_MANUAL)
            ->where('type', CashBankTransaction::TYPE_EXPENSE)
            ->where('status', CashBankTransaction::STATUS_POSTED)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No posted manual outflows found - nothing to reconcile.');

            return self::SUCCESS;
        }

        $buckets = ['expense' => [], 'not_expense' => [], 'needs_decision' => [], 'unknown' => []];

        foreach ($transactions as $transaction) {
            $buckets[$this->classify($transaction->category)][] = $transaction;
        }

        $this->line('');
        $this->info("{$transactions->count()} posted manual outflow(s), none of them currently reaching the profit & loss.");

        $this->report(
            'WOULD BECOME AN EXPENSE',
            'These map onto an existing Pengeluaran category, so a backfill could link them.',
            $buckets['expense'],
        );
        $this->report(
            'NEEDS A DECISION',
            'Tax: not treated as a business expense until its classification is settled.',
            $buckets['needs_decision'],
        );
        $this->report(
            'CORRECTLY NOT AN EXPENSE',
            'Owner withdrawals and balance corrections are equity/bookkeeping movements. Leave them.',
            $buckets['not_expense'],
        );
        $this->report(
            'UNRECOGNISED CATEGORY',
            'Free-typed categories - the manual form does not whitelist them. Each needs a human call.',
            $buckets['unknown'],
        );

        $backfillable = collect($buckets['expense']);

        $this->line('');
        $this->info(sprintf(
            'Backfill scope: %d transaction(s) worth Rp%s.',
            $backfillable->count(),
            number_format((float) $backfillable->sum('amount'), 0, ',', '.'),
        ));
        $this->warn('No changes were made. Expense requires a payment proof and these rows have none, so how to handle that is still an open decision.');

        return self::SUCCESS;
    }

    /**
     * @param  list<CashBankTransaction>  $transactions
     */
    private function report(string $heading, string $note, array $transactions): void
    {
        if ($transactions === []) {
            return;
        }

        $this->line('');
        $this->line("<options=bold>{$heading}</> - ".count($transactions).' transaction(s)');
        $this->line("<fg=gray>{$note}</>");
        $this->table(
            ['Nomor', 'Tanggal', 'Kategori', 'Jumlah', 'Keterangan'],
            array_map(static fn (CashBankTransaction $transaction): array => [
                $transaction->transaction_number,
                $transaction->transaction_date?->format('d M Y'),
                $transaction->category,
                'Rp'.number_format((float) $transaction->amount, 0, ',', '.'),
                str($transaction->description)->limit(40)->toString(),
            ], $transactions),
        );
    }

    private function classify(string $category): string
    {
        if (in_array($category, ['owner_withdrawal', 'balance_adjustment'], true)) {
            return 'not_expense';
        }

        if ($category === 'tax') {
            return 'needs_decision';
        }

        return $this->expenseCategoryFor($category) === null ? 'unknown' : 'expense';
    }

    /**
     * The Pengeluaran category a manual one would become, or null when there
     * is no honest mapping. Deliberately the same rule the profit & loss
     * report applies, so this audit can never disagree with the figures.
     */
    private function expenseCategoryFor(string $category): ?string
    {
        return CashBankTransaction::expenseCategoryFor($category);
    }
}
