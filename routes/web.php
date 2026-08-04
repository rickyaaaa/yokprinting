<?php

use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceProductionStatusController;
use App\Http\Controllers\Api\ProfitLossReportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerFormPageController;
use App\Http\Controllers\CustomerIndexPageController;
use App\Http\Controllers\CustomerShowPageController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\InvoiceIndexPageController;
use App\Http\Controllers\InvoicePaymentPageController;
use App\Http\Controllers\PaymentHistoryPageController;
use App\Http\Controllers\ProductIndexPageController;
use App\Http\Controllers\ProfitLossReportPageController;
use App\Http\Controllers\ReceivablePageController;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => response()
    ->view('auth.login')
    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
    ->header('Pragma', 'no-cache')
    ->header('Expires', '0'))
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
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
    Route::get('/dashboard', DashboardPageController::class)->name('dashboard');
    Route::view('/roles', 'auth.roles')->name('roles.index');
    Route::view('/roles/create', 'auth.role-form')->name('roles.create');
    Route::get('/roles/{role}/permissions', function (string $role) {
        return view('auth.role-permissions', ['roleCode' => $role]);
    })->name('roles.permissions.edit');
    Route::get('/roles/{role}/edit', function (string $role) {
        return view('auth.role-form', ['roleCode' => $role]);
    })->name('roles.edit');
    Route::view('/activity-logs', 'auth.activity-logs')->name('activity-logs.index');
    Route::view('/notifications/due-invoices', 'auth.due-invoices')->name('notifications.due-invoices.index');
    Route::get('/customers', CustomerIndexPageController::class)->name('customers.index');
    Route::get('/customers/create', [CustomerFormPageController::class, 'create'])->name('customers.create');
    Route::get('/customers/{customer}/edit', [CustomerFormPageController::class, 'edit'])->name('customers.edit');
    Route::get('/customers/{customer}', CustomerShowPageController::class)->name('customers.show');
    Route::get('/products', ProductIndexPageController::class)->name('products.index');
    Route::view('/products/create', 'products.form')->name('products.create');
    Route::get('/products/{product}/edit', function (string $product) {
        return view('products.form', ['productCode' => $product]);
    })->name('products.edit');
    Route::get('/invoices', InvoiceIndexPageController::class)->name('invoices.index');
    Route::view('/invoices/create', 'invoices.create')->name('invoices.create');
    Route::view('/invoices/preview', 'invoices.preview')->name('invoices.preview');
    Route::get('/payments/receivables', ReceivablePageController::class)->name('payments.receivables.index');
    Route::get('/payments/history', PaymentHistoryPageController::class)->name('payments.history.index');
    Route::get('/payments/invoices/{invoice:invoice_number}', InvoicePaymentPageController::class)
        ->middleware('permission:invoice.view')
        ->name('payments.invoices.show');
    Route::patch('/api/invoices/{invoice:invoice_number}/production-status', [InvoiceProductionStatusController::class, 'update'])
        ->middleware('permission:invoice.update')
        ->name('api.invoices.production-status.update');
    Route::view('/expenses', 'expenses.index')
        ->middleware('permission:expense.view')
        ->name('expenses.index');
    Route::get('/expenses/create', function () {
        return view('expenses.form', [
            'expenseId' => null,
            'defaultExpenseDate' => now()->toDateString(),
            'categoryOptions' => Expense::categoryOptions(),
            'employeeSubcategoryOptions' => Expense::employeeSubcategoryOptions(),
        ]);
    })->middleware('permission:expense.create')->name('expenses.create');
    Route::get('/expenses/{expense}/edit', function (Expense $expense) {
        return view('expenses.form', [
            'expenseId' => $expense->getKey(),
            'defaultExpenseDate' => now()->toDateString(),
            'categoryOptions' => Expense::categoryOptions(),
            'employeeSubcategoryOptions' => Expense::employeeSubcategoryOptions(),
        ]);
    })->middleware('permission:expense.update')->name('expenses.edit');
    Route::get('/api/expenses', [ExpenseController::class, 'index'])
        ->middleware('permission:expense.view')
        ->name('api.expenses.index');
    Route::post('/api/expenses', [ExpenseController::class, 'store'])
        ->middleware('permission:expense.create')
        ->name('api.expenses.store');
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
    Route::view('/settings/company-profile', 'settings.company-profile')->name('settings.company-profile.edit');
});
