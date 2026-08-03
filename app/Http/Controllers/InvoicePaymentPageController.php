<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InvoicePaymentPageController extends Controller
{
    /**
     * Show the stored invoice payment and production workflow detail.
     */
    public function __invoke(Request $request, Invoice $invoice): View
    {
        $invoice->load([
            'customer',
            'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'payments' => fn ($query) => $query->latest('payment_date')->latest('id'),
        ]);

        /** @var User $user */
        $user = $request->user();
        $canUpdateProduction = $user->role === User::ROLE_OWNER
            || $user->roleDefinition()
                ->whereHas('permissions', fn ($query) => $query->where('code', 'invoice.update'))
                ->exists();

        return view('payments.invoice-detail', [
            'invoiceModel' => $invoice,
            'canUpdateProduction' => $canUpdateProduction,
        ]);
    }
}
