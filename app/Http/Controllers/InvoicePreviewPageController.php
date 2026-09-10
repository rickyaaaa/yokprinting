<?php

namespace App\Http\Controllers;

use App\Services\Invoices\BuildInvoiceDocument;
use Illuminate\Contracts\View\View;

/**
 * The on-screen invoice preview.
 *
 * A controller rather than the Route::view it replaced, because the page needs
 * the company profile: its identity and bank details used to be written into
 * the template by hand, so the page could disagree with settings and with the
 * PDF. A route closure would have been shorter but cannot survive
 * `route:cache`, which the deploy runs.
 */
class InvoicePreviewPageController extends Controller
{
    public function __invoke(BuildInvoiceDocument $buildInvoiceDocument): View
    {
        return view('invoices.preview', [
            'company' => $buildInvoiceDocument->company(),
        ]);
    }
}
