<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerStatementRequest;
use App\Models\Customer;
use App\Services\Reports\BuildCustomerStatement;
use Illuminate\Http\JsonResponse;

class CustomerStatementController extends Controller
{
    /**
     * Display a chronological receivable ledger for a customer.
     */
    public function __invoke(
        CustomerStatementRequest $request,
        Customer $customer,
        BuildCustomerStatement $buildCustomerStatement,
    ): JsonResponse {
        $statement = $buildCustomerStatement->build(
            customer: $customer,
            startDate: $request->validated('start_date'),
            endDate: $request->validated('end_date'),
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer' => $statement['customer'],
                'period' => $statement['period'],
                'total_outstanding_amount' => $statement['summary']['outstanding_amount'],
                'total_outstanding_amount_formatted' => $statement['summary']['outstanding_amount_formatted'],
                'summary' => $statement['summary'],
                'transactions' => $statement['transactions'],
            ],
        ]);
    }
}
