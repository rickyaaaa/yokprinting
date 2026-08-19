<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\SupplierPriceList;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSupplierPriceList
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Record a new supplier price quote. Always inserts a new row - price
     * history is immutable, a changed price from the supplier never
     * overwrites what was recorded before.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): SupplierPriceList
    {
        return DB::transaction(function () use ($data, $actor): SupplierPriceList {
            $priceList = SupplierPriceList::query()->create([
                'supplier_id' => $data['supplier_id'],
                'product_id' => $data['product_id'],
                'price' => $data['price'],
                'valid_from' => $data['valid_from'],
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source_reference' => $data['source_reference'] ?? null,
                'created_by' => $actor?->getKey(),
            ]);

            $this->activityLogger->record(
                module: 'supplier_price',
                action: 'created',
                event: 'Supplier price list quote recorded',
                description: "Harga supplier baru dicatat: produk #{$priceList->product_id} dari supplier #{$priceList->supplier_id} sebesar {$priceList->price} berlaku sejak {$priceList->valid_from->toDateString()}.",
                subject: $priceList,
                actor: $actor,
            );

            return $priceList->load(['supplier', 'product']);
        });
    }

    /**
     * Correct an existing quote in place - only allowed while it has never
     * been used as a Purchase Order's price reference. Once a PO item
     * points at it, it's part of that PO's audit trail: create a new quote
     * instead of editing this one.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SupplierPriceList $priceList, array $data, ?User $actor = null): SupplierPriceList
    {
        return DB::transaction(function () use ($priceList, $data, $actor): SupplierPriceList {
            /** @var SupplierPriceList $locked */
            $locked = SupplierPriceList::query()->lockForUpdate()->findOrFail($priceList->getKey());

            if ($locked->isUsedInPurchaseOrder()) {
                throw ValidationException::withMessages([
                    'id' => 'Harga supplier ini sudah dipakai sebagai referensi PO dan tidak bisa diubah. Buat entri harga baru jika supplier memberi harga terbaru.',
                ]);
            }

            $before = $locked->only(['price', 'valid_from', 'valid_until', 'notes', 'source_reference']);

            $validFrom = $data['valid_from'] ?? $locked->valid_from->toDateString();
            $validUntil = array_key_exists('valid_until', $data) ? $data['valid_until'] : $locked->valid_until?->toDateString();

            if ($validUntil !== null && $validUntil < $validFrom) {
                throw ValidationException::withMessages([
                    'valid_until' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai berlaku.',
                ]);
            }

            $locked->update([
                'price' => $data['price'] ?? $locked->price,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                'source_reference' => array_key_exists('source_reference', $data) ? $data['source_reference'] : $locked->source_reference,
            ]);

            $this->activityLogger->record(
                module: 'supplier_price',
                action: 'corrected',
                event: 'Supplier price list quote corrected',
                description: "Koreksi entri harga supplier #{$locked->id}.",
                subject: $locked,
                metadata: ['before' => $before, 'after' => $locked->only(['price', 'valid_from', 'valid_until', 'notes', 'source_reference'])],
                riskLevel: ActivityLog::RISK_MEDIUM,
                actor: $actor,
            );

            return $locked->refresh()->load(['supplier', 'product']);
        });
    }

    /**
     * Whether another quote for the same supplier + product has an
     * overlapping validity window - purely informational, never blocks
     * saving (see requirement: overlaps are technically allowed).
     *
     * @param  array<string, mixed>  $data
     */
    public function hasOverlap(array $data, ?int $excludingId = null): bool
    {
        return SupplierPriceList::query()
            ->where('supplier_id', $data['supplier_id'])
            ->where('product_id', $data['product_id'])
            ->when($excludingId, fn ($query) => $query->whereKeyNot($excludingId))
            ->where(function ($query) use ($data): void {
                $query->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $data['valid_from']);
            })
            ->when(filled($data['valid_until'] ?? null), function ($query) use ($data): void {
                $query->whereDate('valid_from', '<=', $data['valid_until']);
            })
            ->exists();
    }
}
