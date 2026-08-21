<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;

class PurchaseOrderShowPageController extends Controller
{
    /**
     * Show a single PO's detail (header, items, related goods receipts).
     * Route-model-binding just confirms the PO exists (404 otherwise) - the
     * page itself is fully client-rendered from the existing
     * GET /api/purchase-orders/{id} and GET /api/goods-receipts?purchase_order_id=
     * endpoints, so nothing new needs serializing server-side.
     */
    public function __invoke(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-orders.show', ['purchaseOrderId' => $purchaseOrder->getKey()]);
    }
}
