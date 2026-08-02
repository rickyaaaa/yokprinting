<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListExpensesRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Services\Security\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExpenseController extends Controller
{
    /**
     * List expenses with filters, pagination, and a filtered total.
     */
    public function index(ListExpensesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Expense::query()
            ->with('creator:id,name,email,role')
            ->when(filled($validated['date_from'] ?? null), fn (Builder $builder): Builder => $builder
                ->whereDate('expense_date', '>=', $validated['date_from']))
            ->when(filled($validated['date_to'] ?? null), fn (Builder $builder): Builder => $builder
                ->whereDate('expense_date', '<=', $validated['date_to']))
            ->when(filled($validated['category'] ?? null), fn (Builder $builder): Builder => $builder
                ->where('category', $validated['category']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('recipient', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn (Builder $creatorQuery): Builder => $creatorQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            });

        $totalExpense = (clone $query)->sum('amount');
        $expenses = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => collect($expenses->items())
                ->map(fn (Expense $expense): array => $this->serializeExpense($expense))
                ->values(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
                'total_expense' => (float) $totalExpense,
                'from' => $expenses->firstItem(),
                'to' => $expenses->lastItem(),
                'filters' => [
                    'search' => $search,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                    'category' => $validated['category'] ?? null,
                ],
                'reference' => $this->referenceData(),
            ],
        ]);
    }

    /**
     * Store a newly created expense and its private payment proof.
     */
    public function store(StoreExpenseRequest $request, ActivityLogger $activityLogger): JsonResponse
    {
        $proof = $request->file('proof_payment');
        $proofPath = $proof->store('expense-proofs', 'local');

        if (! $proofPath) {
            return response()->json([
                'message' => 'Bukti pembayaran gagal disimpan.',
            ], 500);
        }

        try {
            $expense = DB::transaction(function () use ($request, $activityLogger, $proof, $proofPath): Expense {
                $payload = $request->safe()->except('proof_payment');
                $payload['subcategory'] = $payload['category'] === Expense::CATEGORY_EMPLOYEE
                    ? $payload['subcategory']
                    : null;
                $payload['proof_path'] = $proofPath;
                $payload['proof_original_name'] = $this->normalizedProofName($proof);
                $payload['proof_mime_type'] = $proof->getMimeType() ?: 'application/octet-stream';
                $payload['created_by'] = $request->user()->getAuthIdentifier();

                $expense = Expense::query()->create($payload);

                $activityLogger->record(
                    module: 'expense',
                    action: 'create',
                    event: 'Expense created',
                    description: "Pengeluaran {$expense->categoryLabel()} dibuat.",
                    subject: $expense,
                    metadata: $this->auditSnapshot($expense),
                    riskLevel: ActivityLog::RISK_MEDIUM,
                );

                return $expense;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($proofPath);

            throw $exception;
        }

        return response()->json([
            'data' => $this->serializeExpense($expense->load('creator:id,name,email,role')),
            'message' => 'Pengeluaran berhasil ditambahkan.',
        ], 201);
    }

    /**
     * Display an expense.
     */
    public function show(Expense $expense): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeExpense($expense->load('creator:id,name,email,role')),
            'meta' => [
                'reference' => $this->referenceData(),
            ],
        ]);
    }

    /**
     * Update an expense and replace its proof when supplied.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense, ActivityLogger $activityLogger): JsonResponse
    {
        $proof = $request->file('proof_payment');
        $newProofPath = $proof?->store('expense-proofs', 'local');

        if ($proof && ! $newProofPath) {
            return response()->json([
                'message' => 'Bukti pembayaran pengganti gagal disimpan.',
            ], 500);
        }

        $oldProofPath = $expense->proof_path;

        try {
            DB::transaction(function () use ($request, $expense, $activityLogger, $proof, $newProofPath): void {
                $before = $this->auditSnapshot($expense);
                $payload = $request->safe()->except('proof_payment');
                $category = $payload['category'] ?? $expense->category;

                if ($category !== Expense::CATEGORY_EMPLOYEE) {
                    $payload['subcategory'] = null;
                }

                if ($proof && $newProofPath) {
                    $payload['proof_path'] = $newProofPath;
                    $payload['proof_original_name'] = $this->normalizedProofName($proof);
                    $payload['proof_mime_type'] = $proof->getMimeType() ?: 'application/octet-stream';
                }

                $expense->update($payload);

                $activityLogger->record(
                    module: 'expense',
                    action: 'update',
                    event: 'Expense updated',
                    description: "Pengeluaran {$expense->categoryLabel()} diperbarui.",
                    subject: $expense,
                    metadata: [
                        'before' => $before,
                        'after' => $this->auditSnapshot($expense->refresh()),
                    ],
                    riskLevel: ActivityLog::RISK_MEDIUM,
                );
            });
        } catch (Throwable $exception) {
            if ($newProofPath) {
                Storage::disk('local')->delete($newProofPath);
            }

            throw $exception;
        }

        if ($newProofPath && $oldProofPath !== $newProofPath) {
            Storage::disk('local')->delete($oldProofPath);
        }

        return response()->json([
            'data' => $this->serializeExpense($expense->refresh()->load('creator:id,name,email,role')),
            'message' => 'Pengeluaran berhasil diperbarui.',
        ]);
    }

    /**
     * Soft delete an expense while preserving its evidence for audit recovery.
     */
    public function destroy(Expense $expense, ActivityLogger $activityLogger): JsonResponse
    {
        DB::transaction(function () use ($expense, $activityLogger): void {
            $activityLogger->record(
                module: 'expense',
                action: 'delete',
                event: 'Expense deleted',
                description: "Pengeluaran {$expense->categoryLabel()} dihapus.",
                subject: $expense,
                metadata: $this->auditSnapshot($expense),
                riskLevel: ActivityLog::RISK_HIGH,
            );

            $expense->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * Download a private payment proof.
     */
    public function downloadProof(Request $request, Expense $expense, ActivityLogger $activityLogger): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($expense->proof_path), 404);

        $activityLogger->record(
            module: 'expense',
            action: 'proof_download',
            event: 'Expense proof downloaded',
            description: "Bukti pengeluaran {$expense->getKey()} diunduh.",
            subject: $expense,
            metadata: [
                'proof_original_name' => $expense->proof_original_name,
            ],
        );

        return Storage::disk('local')->download(
            $expense->proof_path,
            $expense->proof_original_name,
            ['Content-Type' => $expense->proof_mime_type],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => $expense->getKey(),
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'category_label' => $expense->categoryLabel(),
            'subcategory' => $expense->subcategory,
            'subcategory_label' => $expense->subcategoryLabel(),
            'amount' => (float) $expense->amount,
            'description' => $expense->description,
            'recipient' => $expense->recipient,
            'payment_method' => $expense->payment_method,
            'proof_original_name' => $expense->proof_original_name,
            'proof_mime_type' => $expense->proof_mime_type,
            'proof_download_url' => route('api.expenses.proof.download', $expense),
            'created_by' => $expense->created_by,
            'creator' => $expense->relationLoaded('creator') && $expense->creator ? [
                'id' => $expense->creator->getKey(),
                'name' => $expense->creator->name,
                'email' => $expense->creator->email,
                'role' => $expense->creator->role,
            ] : null,
            'created_at' => $expense->created_at?->toISOString(),
            'updated_at' => $expense->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{categories: array<string, string>, employee_subcategories: array<string, string>}
     */
    private function referenceData(): array
    {
        return [
            'categories' => Expense::categoryOptions(),
            'employee_subcategories' => Expense::employeeSubcategoryOptions(),
        ];
    }

    /**
     * Avoid recording private storage paths in the audit log.
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(Expense $expense): array
    {
        return [
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'subcategory' => $expense->subcategory,
            'amount' => (string) $expense->amount,
            'description' => $expense->description,
            'recipient' => $expense->recipient,
            'payment_method' => $expense->payment_method,
            'proof_original_name' => $expense->proof_original_name,
            'created_by' => $expense->created_by,
        ];
    }

    /**
     * Keep download filenames readable without accepting path or header characters.
     */
    private function normalizedProofName(UploadedFile $proof): string
    {
        $originalName = $proof->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^\pL\pN._ -]/u', '_', $baseName) ?: 'bukti-pembayaran';
        $baseName = Str::limit(trim($baseName, ' ._'), 200, '') ?: 'bukti-pembayaran';
        $extension = Str::lower($proof->getClientOriginalExtension());

        return $extension === '' ? $baseName : "{$baseName}.{$extension}";
    }
}
