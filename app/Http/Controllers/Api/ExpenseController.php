<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExpenseProofUnavailableException;
use App\Exceptions\ExpenseVersionConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListExpensesRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseProofCleanupTask;
use App\Services\CashBank\CashBankService;
use App\Services\Expenses\ExpenseProofCleanup;
use App\Services\Security\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ExpenseController extends Controller
{
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

    public function store(
        StoreExpenseRequest $request,
        ActivityLogger $activityLogger,
        ExpenseProofCleanup $proofCleanup,
        CashBankService $cashBank,
    ): JsonResponse {
        $proof = $request->file('proof_payment');
        $proofPath = $proof->store('expense-proofs', $this->proofDisk());

        if (! $proofPath) {
            return response()->json(['message' => 'Bukti pembayaran gagal disimpan.'], 500);
        }

        try {
            $expense = DB::transaction(function () use ($request, $activityLogger, $cashBank, $proof, $proofPath): Expense {
                $payload = $request->safe()->except('proof_payment');
                $payload['subcategory'] = $payload['category'] === Expense::CATEGORY_EMPLOYEE
                    ? $payload['subcategory']
                    : null;
                $payload['proof_path'] = $proofPath;
                $payload['proof_original_name'] = $this->normalizedProofName($proof);
                $payload['proof_mime_type'] = $proof->getMimeType() ?: 'application/octet-stream';
                $payload['created_by'] = $request->user()->getAuthIdentifier();

                $expense = Expense::query()->create($payload);
                $cashBank->recordExpense($expense);

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
            $this->cleanupNewProof($proofCleanup, $proofPath, 'create_rollback');

            throw $exception;
        }

        return response()->json([
            'data' => $this->serializeExpense($expense->load('creator:id,name,email,role')),
            'message' => 'Pengeluaran berhasil ditambahkan.',
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeExpense($expense->load('creator:id,name,email,role')),
            'meta' => ['reference' => $this->referenceData()],
        ]);
    }

    public function update(
        UpdateExpenseRequest $request,
        Expense $expense,
        ActivityLogger $activityLogger,
        ExpenseProofCleanup $proofCleanup,
        CashBankService $cashBank,
    ): JsonResponse {
        $proof = $request->file('proof_payment');
        $newProofPath = $proof?->store('expense-proofs', $this->proofDisk());

        if ($proof && ! $newProofPath) {
            return response()->json(['message' => 'Bukti pembayaran pengganti gagal disimpan.'], 500);
        }

        $cleanupTask = null;
        $updatedExpense = null;

        try {
            DB::transaction(function () use (
                $request,
                $expense,
                $activityLogger,
                $proofCleanup,
                $cashBank,
                $proof,
                $newProofPath,
                &$cleanupTask,
                &$updatedExpense,
            ): void {
                $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());

                if ((int) $request->validated('version') !== $locked->version) {
                    throw new ExpenseVersionConflictException($locked->version);
                }

                $before = $this->auditSnapshot($locked);
                $payload = $request->safe()->except('proof_payment', 'version');
                $category = $payload['category'] ?? $locked->category;

                if ($category !== Expense::CATEGORY_EMPLOYEE) {
                    $payload['subcategory'] = null;
                }

                if ($proof && $newProofPath) {
                    $oldProofPath = $locked->proof_path;
                    $payload['proof_path'] = $newProofPath;
                    $payload['proof_original_name'] = $this->normalizedProofName($proof);
                    $payload['proof_mime_type'] = $proof->getMimeType() ?: 'application/octet-stream';
                    $cleanupTask = $proofCleanup->queue(
                        $oldProofPath,
                        'proof_replaced',
                        $locked->getKey(),
                    );
                }

                $payload['version'] = $locked->version + 1;
                $locked->update($payload);
                $locked->refresh();
                $cashBank->syncExpense($locked);

                $activityLogger->record(
                    module: 'expense',
                    action: 'update',
                    event: 'Expense updated',
                    description: "Pengeluaran {$locked->categoryLabel()} diperbarui.",
                    subject: $locked,
                    metadata: [
                        'before' => $before,
                        'after' => $this->auditSnapshot($locked),
                    ],
                    riskLevel: ActivityLog::RISK_MEDIUM,
                );

                $updatedExpense = $locked;
            });
        } catch (ExpenseVersionConflictException $exception) {
            if ($newProofPath) {
                $this->cleanupNewProof($proofCleanup, $newProofPath, 'version_conflict', $expense->getKey());
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'current_version' => $exception->currentVersion,
            ], 409);
        } catch (Throwable $exception) {
            if ($newProofPath) {
                $this->cleanupNewProof($proofCleanup, $newProofPath, 'update_rollback', $expense->getKey());
            }

            throw $exception;
        }

        if ($cleanupTask instanceof ExpenseProofCleanupTask) {
            $proofCleanup->attempt($cleanupTask);
        }

        return response()->json([
            'data' => $this->serializeExpense($updatedExpense->load('creator:id,name,email,role')),
            'message' => 'Pengeluaran berhasil diperbarui.',
        ]);
    }

    public function destroy(Expense $expense, ActivityLogger $activityLogger, CashBankService $cashBank): JsonResponse
    {
        DB::transaction(function () use ($expense, $activityLogger, $cashBank): void {
            $locked = Expense::query()->lockForUpdate()->findOrFail($expense->getKey());
            $before = $this->auditSnapshot($locked);

            $activityLogger->record(
                module: 'expense',
                action: 'delete',
                event: 'Expense soft deleted',
                description: "Pengeluaran {$locked->categoryLabel()} dihapus sementara.",
                subject: $locked,
                metadata: $before,
                riskLevel: ActivityLog::RISK_HIGH,
            );

            $locked->delete();
            $cashBank->cancelExpenseTransaction($locked, auth()->id());
        });

        return response()->json(status: 204);
    }

    public function restore(string $expense, ActivityLogger $activityLogger): JsonResponse
    {
        try {
            $restored = DB::transaction(function () use ($expense, $activityLogger): Expense {
                $locked = Expense::onlyTrashed()->lockForUpdate()->findOrFail($expense);

                if (! Storage::disk($this->proofDisk())->exists($locked->proof_path)) {
                    throw new ExpenseProofUnavailableException($locked->getKey());
                }

                $locked->restore();
                $locked->increment('version');
                $locked->refresh();

                $activityLogger->record(
                    module: 'expense',
                    action: 'restore',
                    event: 'Expense restored',
                    description: 'Pengeluaran dipulihkan dari masa retensi.',
                    subject: $locked,
                    metadata: $this->auditSnapshot($locked),
                    riskLevel: ActivityLog::RISK_HIGH,
                );

                return $locked;
            });
        } catch (ExpenseProofUnavailableException $exception) {
            $failedExpense = Expense::onlyTrashed()->find($exception->expenseId);
            $activityLogger->record(
                module: 'expense',
                action: 'restore_failed',
                event: 'Expense restore failed',
                description: 'Pengeluaran tidak dipulihkan karena bukti audit tidak tersedia.',
                subject: $failedExpense,
                riskLevel: ActivityLog::RISK_HIGH,
            );

            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => $this->serializeExpense($restored->load('creator:id,name,email,role')),
            'message' => 'Pengeluaran berhasil dipulihkan.',
        ]);
    }

    public function downloadProof(
        Request $request,
        string $expense,
        ActivityLogger $activityLogger,
    ): StreamedResponse {
        $expenseModel = Expense::withTrashed()->findOrFail($expense);

        $activityLogger->record(
            module: 'expense',
            action: 'proof_download_requested',
            event: 'Expense proof download requested',
            description: "Bukti pengeluaran {$expenseModel->getKey()} diminta.",
            subject: $expenseModel,
            metadata: ['proof_original_name' => $expenseModel->proof_original_name],
        );

        try {
            $stream = Storage::disk($this->proofDisk())->readStream($expenseModel->proof_path);

            if (! is_resource($stream)) {
                abort(404, 'Bukti pengeluaran tidak ditemukan.');
            }
        } catch (Throwable $exception) {
            $activityLogger->record(
                module: 'expense',
                action: 'proof_download_failed',
                event: 'Expense proof download failed',
                description: 'Storage gagal menyediakan bukti pengeluaran.',
                subject: $expenseModel,
                metadata: ['error' => mb_substr($exception->getMessage(), 0, 500)],
                riskLevel: ActivityLog::RISK_MEDIUM,
            );

            if ($exception instanceof NotFoundHttpException) {
                throw $exception;
            }

            abort(503, 'Bukti pengeluaran belum dapat disediakan.');
        }

        $activityLogger->record(
            module: 'expense',
            action: 'proof_download_prepared',
            event: 'Expense proof prepared for download',
            description: 'Storage berhasil menyediakan bukti pengeluaran untuk dikirim.',
            subject: $expenseModel,
            metadata: ['proof_original_name' => $expenseModel->proof_original_name],
        );

        return response()->streamDownload(
            function () use ($stream, $activityLogger, $expenseModel): void {
                try {
                    fpassthru($stream);
                } catch (Throwable $exception) {
                    $activityLogger->record(
                        module: 'expense',
                        action: 'proof_download_stream_failed',
                        event: 'Expense proof stream failed',
                        description: 'Pengiriman stream bukti pengeluaran terputus.',
                        subject: $expenseModel,
                        metadata: ['error' => mb_substr($exception->getMessage(), 0, 500)],
                        riskLevel: ActivityLog::RISK_MEDIUM,
                    );
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            $this->safeDownloadName($expenseModel->proof_original_name),
            [
                'Content-Type' => $expenseModel->proof_mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** @return array<string, mixed> */
    private function serializeExpense(Expense $expense): array
    {
        return [
            'id' => $expense->getKey(),
            'version' => $expense->version,
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
            'proof_download_url' => route('api.expenses.proof.download', $expense->getKey()),
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

    /** @return array{categories: array<string, string>, employee_subcategories: array<string, string>} */
    private function referenceData(): array
    {
        return [
            'categories' => Expense::categoryOptions(),
            'employee_subcategories' => Expense::employeeSubcategoryOptions(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(Expense $expense): array
    {
        return [
            'version' => $expense->version,
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

    private function normalizedProofName(UploadedFile $proof): string
    {
        $originalName = $proof->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^\pL\pN._ -]/u', '_', $baseName) ?: 'bukti-pembayaran';
        $baseName = Str::limit(trim($baseName, ' ._'), 200, '') ?: 'bukti-pembayaran';
        $extension = Str::lower($proof->getClientOriginalExtension());

        return $extension === '' ? $baseName : "{$baseName}.{$extension}";
    }

    private function safeDownloadName(string $name): string
    {
        $safe = preg_replace('~[\x00-\x1F\x7F/\\\\]+~u', '_', $name) ?: 'bukti-pembayaran';

        return Str::limit(trim($safe, ' .'), 220, '') ?: 'bukti-pembayaran';
    }

    private function cleanupNewProof(
        ExpenseProofCleanup $proofCleanup,
        string $path,
        string $reason,
        ?int $expenseId = null,
    ): void {
        try {
            $proofCleanup->cleanup($path, $reason, $expenseId);
        } catch (Throwable $cleanupException) {
            Log::critical('Could not persist an expense proof cleanup retry task.', [
                'expense_id' => $expenseId,
                'reason' => $reason,
                'exception' => $cleanupException,
            ]);
        }
    }

    private function proofDisk(): string
    {
        return (string) config('expenses.proof_disk', 'expense_proofs');
    }
}
