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
     * Manual transactions never carry a source_id, so none of them is backed
     * by an Expense row. Since ProfitLossReport folds manual outflows in
     * directly, the ones in a cost category are already recognised; this
     * reports which those are, which are deliberately not costs, and which
     * nobody can classify without asking - the free-text ones, since the
     * manual category field is validated only as a string.
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
        $this->info("{$transactions->count()} posted manual outflow(s) found.");

        $this->report(
            'COUNTED AS A COST IN THE PROFIT & LOSS',
            'These map onto a Pengeluaran category and the report already includes them.',
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
            'Recognised as cost: %d transaction(s) worth Rp%s.',
            $backfillable->count(),
            number_format((float) $backfillable->sum('amount'), 0, ',', '.'),
        ));
        $this->info('Nothing to repair, and nothing was changed. These reach the report straight from Kas & Bank.');
        $this->line('<fg=gray>Re-entering them through Pengeluaran is only worth it if you want a payment proof and a recipient on file - and the Kas & Bank row must be cancelled first, or the cash leaves twice.</>');

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
