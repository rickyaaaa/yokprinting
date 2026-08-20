<?php

use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceProductionStatusController;
use App\Http\Controllers\Api\ProfitLossReportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerFormPageController;
use App\Http\Controllers\CustomerIndexPageController;
use App\Http\Controllers\CustomerSalesReportPageController;
use App\Http\Controllers\CustomerShowPageController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\DueInvoicePageController;
use App\Http\Controllers\InvoiceEditPageController;
use App\Http\Controllers\InvoiceIndexPageController;
use App\Http\Controllers\InvoicePaymentPageController;
use App\Http\Controllers\PaymentHistoryPageController;
use App\Http\Controllers\ProductIndexPageController;
use App\Http\Controllers\ProfitLossReportPageController;
use App\Http\Controllers\ReceivablePageController;
use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => response()
    ->view('auth.login')
    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
    ->header('Pragma', 'no-cache')
    ->header('Expires', '0'))
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');
Route::get('/register', fn () => redirect()
    ->route('login')
    ->with('status', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.'));
Route::post('/register', function (Request $request) {
    if ($request->expectsJson()) {
        return response()->json([
            'message' => 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.',
        ], 403);
    }

    return redirect()
        ->route('login')
        ->with('status', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.');
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardPageController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
    Route::view('/roles', 'auth.roles')
        ->middleware('permission:role.view')
        ->name('roles.index');
    Route::view('/roles/create', 'auth.role-form')
        ->middleware('permission:role.create')
        ->name('roles.create');
    Route::get('/roles/{role}/permissions', function (string $role) {
        return view('auth.role-permissions', ['roleCode' => $role]);
    })->middleware('permission:role.update')->name('roles.permissions.edit');
    Route::get('/roles/{role}/edit', function (string $role) {
        return view('auth.role-form', ['roleCode' => $role]);
    })->middleware('permission:role.update')->name('roles.edit');
    Route::view('/activity-logs', 'auth.activity-logs')
        ->middleware('permission:activity_log.view')
        ->name('activity-logs.index');
    Route::get('/notifications/due-invoices', DueInvoicePageController::class)
        ->middleware('permission:payment.view')
        ->name('notifications.due-invoices.index');
    Route::get('/customers', CustomerIndexPageController::class)
        ->middleware('permission:customer.view')
        ->name('customers.index');
    Route::get('/customers/create', [CustomerFormPageController::class, 'create'])
        ->middleware('permission:customer.create')
        ->name('customers.create');
    Route::get('/customers/{customer}/edit', [CustomerFormPageController::class, 'edit'])
        ->middleware('permission:customer.update')
        ->name('customers.edit');
    Route::get('/customers/{customer}', CustomerShowPageController::class)
        ->middleware('permission:customer.view')
        ->name('customers.show');
    Route::get('/products', ProductIndexPageController::class)
        ->middleware('permission:product.view')
        ->name('products.index');
    Route::view('/products/create', 'products.form')
        ->middleware('permission:product.create')
        ->name('products.create');
    Route::get('/products/{product}/edit', function (string $product) {
        return view('products.form', ['productCode' => $product]);
    })->middleware('permission:product.update')->name('products.edit');
    Route::view('/suppliers', 'suppliers.index')
        ->middleware('permission:product.view')
        ->name('suppliers.index');
    Route::view('/suppliers/create', 'suppliers.form')
        ->middleware('permission:product.create')
        ->name('suppliers.create');
    Route::get('/suppliers/{supplier}/edit', function (Supplier $supplier) {
        return view('suppliers.form', ['supplier' => $supplier]);
    })->middleware('permission:product.update')->name('suppliers.edit');
    Route::get('/invoices', InvoiceIndexPageController::class)
        ->middleware('permission:invoice.view')
        ->name('invoices.index');
    Route::view('/invoices/create', 'invoices.create')
        ->middleware('permission:invoice.create')
        ->name('invoices.create');
    Route::view('/invoices/preview', 'invoices.preview')
        ->middleware('permission:invoice.create')
        ->name('invoices.preview');
    Route::get('/invoices/{invoice:invoice_number}/edit', InvoiceEditPageController::class)
        ->middleware('permission:invoice.update')
        ->name('invoices.edit');
    Route::get('/payments/receivables', ReceivablePageController::class)
        ->middleware('permission:payment.view')
        ->name('payments.receivables.index');
    Route::get('/payments/history', PaymentHistoryPageController::class)
        ->middleware('permission:payment.view')
        ->name('payments.history.index');
    Route::view('/cash-bank', 'cash-bank.index')
        ->middleware('permission:cash_bank.view')
        ->name('cash-bank.index');
    Route::get('/payments/invoices/{invoice:invoice_number}', InvoicePaymentPageController::class)
        ->middleware('permission:invoice.view')
        ->name('payments.invoices.show');
    Route::patch('/api/invoices/{invoice:invoice_number}/production-status', [InvoiceProductionStatusController::class, 'update'])
        ->middleware('permission:invoice.update')
        ->name('api.invoices.production-status.update');
    Route::view('/purchase-orders', 'purchase-orders.index')
        ->middleware('permission:purchase_order.view')
        ->name('purchase-orders.index');
    Route::view('/purchase-orders/create', 'purchase-orders.create')
        ->middleware('permission:purchase_order.create')
        ->name('purchase-orders.create');
    Route::view('/goods-receipts', 'goods-receipts.index')
        ->middleware('permission:goods_receipt.view')
        ->name('goods-receipts.index');
    Route::view('/goods-receipts/create', 'goods-receipts.create')
        ->middleware('permission:goods_receipt.create')
        ->name('goods-receipts.create');
    Route::view('/supplier-prices', 'supplier-prices.index')
        ->middleware('permission:supplier_price.view')
        ->name('supplier-prices.index');
    Route::view('/supplier-prices/create', 'supplier-prices.create')
        ->middleware('permission:supplier_price.create')
        ->name('supplier-prices.create');
    Route::view('/expenses', 'expenses.index')
        ->middleware('permission:expense.view')
        ->name('expenses.index');
    Route::get('/expenses/create', function () {
        return view('expenses.form', [
            'expenseId' => null,
            'defaultExpenseDate' => now()->toDateString(),
            'categoryOptions' => Expense::categoryOptions(),
            'employeeSubcategoryOptions' => Expense::employeeSubcategoryOptions(),
            'paymentMethodOptions' => Expense::paymentMethodOptions(),
        ]);
    })->middleware('permission:expense.create')->name('expenses.create');
    Route::get('/expenses/{expense}/edit', function (Expense $expense) {
        return view('expenses.form', [
            'expenseId' => $expense->getKey(),
            'defaultExpenseDate' => now()->toDateString(),
            'categoryOptions' => Expense::categoryOptions(),
            'employeeSubcategoryOptions' => Expense::employeeSubcategoryOptions(),
            'paymentMethodOptions' => Expense::paymentMethodOptions(),
        ]);
    })->middleware('permission:expense.update')->name('expenses.edit');
    Route::get('/api/expenses', [ExpenseController::class, 'index'])
        ->middleware('permission:expense.view')
        ->name('api.expenses.index');
    Route::post('/api/expenses', [ExpenseController::class, 'store'])
        ->middleware('permission:expense.create')
        ->name('api.expenses.store');
    // Registered before the {expense} show route below so the literal
    // "export" segment wins over route-model-binding.
    Route::get('/api/expenses/export', [ExpenseController::class, 'export'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.expenses.export');
    Route::get('/api/expenses/{expense}', [ExpenseController::class, 'show'])
        ->middleware('permission:expense.view')
        ->name('api.expenses.show');
    Route::match(['put', 'patch'], '/api/expenses/{expense}', [ExpenseController::class, 'update'])
        ->middleware('permission:expense.update')
        ->name('api.expenses.update');
    Route::delete('/api/expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->middleware('permission:expense.delete')
        ->name('api.expenses.destroy');
    Route::post('/api/expenses/{expense}/restore', [ExpenseController::class, 'restore'])
        ->middleware('permission:expense.delete')
        ->name('api.expenses.restore');
    Route::get('/api/expenses/{expense}/proof', [ExpenseController::class, 'downloadProof'])
        ->middleware('permission:expense.view')
        ->name('api.expenses.proof.download');
    Route::view('/reports/sales', 'reports.sales')
        ->middleware('permission:report.view')
        ->name('reports.sales.index');
    Route::get('/reports/customer-sales', CustomerSalesReportPageController::class)
        ->middleware('permission:report.view')
        ->name('reports.customer-sales.index');
    Route::get('/reports/profit-loss', ProfitLossReportPageController::class)
        ->middleware('permission:report.view')
        ->name('reports.profit-loss.index');
    Route::get('/api/reports/profit-loss', [ProfitLossReportController::class, 'show'])
        ->middleware('permission:report.view')
        ->name('api.reports.profit-loss.show');
    Route::get('/api/reports/profit-loss/pdf', [ProfitLossReportController::class, 'pdf'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.profit-loss.pdf');
    Route::get('/api/reports/profit-loss/excel', [ProfitLossReportController::class, 'excel'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.profit-loss.excel');
    Route::view('/settings/company-profile', 'settings.company-profile')
        ->middleware('permission:setting.view')
        ->name('settings.company-profile.edit');
});
