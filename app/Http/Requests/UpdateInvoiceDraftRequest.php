<?php

namespace App\Http\Requests;

/**
 * Update replaces the whole draft invoice (header + items), so it validates
 * identically to creation - see StorePurchaseOrderRequest/UpdatePurchaseOrderRequest
 * for the same pattern.
 */
class UpdateInvoiceDraftRequest extends StoreInvoiceDraftRequest {}
