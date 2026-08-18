<?php

namespace App\Http\Requests;

/**
 * Update replaces the whole draft PO (header + items), so it validates
 * identically to creation.
 */
class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest {}
