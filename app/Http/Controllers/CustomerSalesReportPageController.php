<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Contracts\View\View;

class CustomerSalesReportPageController extends Controller
{
    public function __invoke(): View
    {
        $customers = Customer::query()
            ->select(['id', 'name'])
            ->where('status', Customer::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        return view('reports.customer-sales', compact('customers'));
    }
}
