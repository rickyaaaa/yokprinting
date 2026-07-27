<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
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
    Route::view('/dashboard', 'dashboard')->name('dashboard');
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
    Route::view('/customers', 'customers.index')->name('customers.index');
    Route::view('/customers/create', 'customers.form')->name('customers.create');
    Route::get('/customers/{customer}/edit', function (string $customer) {
        return view('customers.form', ['customerCode' => $customer]);
    })->name('customers.edit');
    Route::get('/customers/{customer}', function (string $customer) {
        return view('customers.show', ['customerCode' => $customer]);
    })->name('customers.show');
    Route::view('/products', 'products.index')->name('products.index');
    Route::view('/products/create', 'products.form')->name('products.create');
    Route::get('/products/{product}/edit', function (string $product) {
        return view('products.form', ['productCode' => $product]);
    })->name('products.edit');
    Route::view('/invoices', 'invoices.index')->name('invoices.index');
    Route::view('/invoices/create', 'invoices.create')->name('invoices.create');
    Route::view('/invoices/preview', 'invoices.preview')->name('invoices.preview');
    Route::view('/payments/receivables', 'payments.receivables')->name('payments.receivables.index');
    Route::view('/payments/history', 'payments.history')->name('payments.history.index');
    Route::view('/payments/invoices/{invoice}', 'payments.invoice-detail')->name('payments.invoices.show');
    Route::view('/reports/sales', 'reports.sales')->name('reports.sales.index');
    Route::view('/settings/company-profile', 'settings.company-profile')->name('settings.company-profile.edit');
});
