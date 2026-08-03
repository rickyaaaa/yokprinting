<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\CustomerActivityAlertController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerOptionController;
use App\Http\Controllers\Api\CustomerStatementController;
use App\Http\Controllers\Api\CustomerTransactionHistoryController;
use App\Http\Controllers\Api\DueInvoiceNotificationController;
use App\Http\Controllers\Api\FinancialSummaryController;
use App\Http\Controllers\Api\GrossProfitReportController;
use App\Http\Controllers\Api\InvoiceDeliveryController;
use App\Http\Controllers\Api\InvoiceDraftController;
use App\Http\Controllers\Api\InvoicePaymentController;
use App\Http\Controllers\Api\InvoicePaymentDetailController;
use App\Http\Controllers\Api\InvoicePdfController;
use App\Http\Controllers\Api\InvoicePreviewPdfController;
use App\Http\Controllers\Api\PaymentHistoryController;
use App\Http\Controllers\Api\ProductBulkStockController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductLowStockSummaryController;
use App\Http\Controllers\Api\ProductOptionController;
use App\Http\Controllers\Api\ReceivableController;
use App\Http\Controllers\Api\RecentActivitiesController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\RevenueChartController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SalesReportExportController;
use App\Http\Controllers\Api\SalesReportInvoiceController;
use App\Http\Controllers\Api\SalesReportRevenueChartController;
use App\Http\Controllers\Api\SalesReportSummaryController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockMovementReportController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ThemeDefaultSettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', fn () => response()->json([
    'message' => 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.',
], 403));

Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])
    ->name('api.auth.login');

Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('api.auth.logout');

Route::get('/dashboard/financial-summary', FinancialSummaryController::class)
    ->name('api.dashboard.financial-summary');

Route::get('/dashboard/revenue-chart', RevenueChartController::class)
    ->name('api.dashboard.revenue-chart');

Route::get('/dashboard/activities', RecentActivitiesController::class)
    ->name('api.dashboard.activities');

Route::get('/dashboard/customer-activity-alerts', CustomerActivityAlertController::class)
    ->name('api.dashboard.customer-activity-alerts');

Route::get('/company-profile', [CompanyProfileController::class, 'show'])
    ->name('api.company-profile.show');

Route::match(['put', 'patch'], '/company-profile', [CompanyProfileController::class, 'update'])
    ->name('api.company-profile.update');

Route::post('/company-profile/logo', [CompanyProfileController::class, 'uploadLogo'])
    ->name('api.company-profile.logo.store');

Route::get('/settings/theme-defaults', [ThemeDefaultSettingController::class, 'show'])
    ->name('api.settings.theme-defaults.show');

Route::match(['put', 'patch'], '/settings/theme-defaults', [ThemeDefaultSettingController::class, 'update'])
    ->name('api.settings.theme-defaults.update');

Route::get('/customers', [CustomerOptionController::class, 'index'])
    ->name('api.customers.index');

Route::post('/customers', [CustomerController::class, 'store'])
    ->name('api.customers.store');

Route::get('/customers/{customer}', [CustomerController::class, 'show'])
    ->name('api.customers.show');

Route::get('/customers/{customer}/transactions', [CustomerTransactionHistoryController::class, 'index'])
    ->name('api.customers.transactions.index');

Route::get('/customers/{customer}/statement', CustomerStatementController::class)
    ->middleware('auth')
    ->name('api.customers.statement.show');

Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])
    ->name('api.customers.update');

Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
    ->name('api.customers.destroy');

Route::get('/products', [ProductController::class, 'index'])
    ->name('api.products.index');

Route::post('/products', [ProductController::class, 'store'])
    ->name('api.products.store');

Route::get('/products/low-stock-summary', ProductLowStockSummaryController::class)
    ->name('api.products.low-stock-summary');

Route::get('/products/options', [ProductOptionController::class, 'index'])
    ->name('api.products.options.index');

Route::patch('/products/bulk-stock', ProductBulkStockController::class)
    ->middleware(['auth', 'permission:product.update'])
    ->name('api.products.bulk-stock.update');

Route::get('/products/{product}', [ProductController::class, 'show'])
    ->name('api.products.show');

Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])
    ->name('api.products.update');

Route::delete('/products/{product}', [ProductController::class, 'destroy'])
    ->name('api.products.destroy');

Route::get('/product-categories', [ProductCategoryController::class, 'index'])
    ->name('api.product-categories.index');

Route::apiResource('suppliers', SupplierController::class)
    ->names('api.suppliers');

Route::get('/roles', [RoleController::class, 'index'])
    ->middleware(['auth', 'permission:role.view'])
    ->name('api.roles.index');

Route::post('/roles', [RoleController::class, 'store'])
    ->middleware(['auth', 'permission:role.create'])
    ->name('api.roles.store');

Route::get('/roles/{role:code}', [RoleController::class, 'show'])
    ->middleware(['auth', 'permission:role.view'])
    ->name('api.roles.show');

Route::match(['put', 'patch'], '/roles/{role:code}', [RoleController::class, 'update'])
    ->middleware(['auth', 'permission:role.update'])
    ->name('api.roles.update');

Route::delete('/roles/{role:code}', [RoleController::class, 'destroy'])
    ->middleware(['auth', 'permission:role.delete'])
    ->name('api.roles.destroy');

Route::get('/roles/{role:code}/permissions', [RolePermissionController::class, 'show'])
    ->middleware(['auth', 'permission:role.view'])
    ->name('api.roles.permissions.show');

Route::match(['put', 'patch'], '/roles/{role:code}/permissions', [RolePermissionController::class, 'update'])
    ->middleware(['auth', 'permission:role.update'])
    ->name('api.roles.permissions.update');

Route::get('/activity-logs', [ActivityLogController::class, 'index'])
    ->middleware(['auth', 'permission:activity_log.view'])
    ->name('api.activity-logs.index');

Route::get('/notifications/due-invoices', [DueInvoiceNotificationController::class, 'index'])
    ->middleware('auth')
    ->name('api.notifications.due-invoices.index');

Route::post('/invoices/drafts', [InvoiceDraftController::class, 'store'])
    ->name('api.invoices.drafts.store');

Route::post('/stock-movements', [StockMovementController::class, 'store'])
    ->middleware('auth')
    ->name('api.stock-movements.store');

Route::post('/invoices/{invoice}/send', [InvoiceDeliveryController::class, 'send'])
    ->middleware(['auth', 'permission:invoice.update'])
    ->name('api.invoices.send');

Route::post('/invoices/{invoice:invoice_number}/payments', [InvoicePaymentController::class, 'store'])
    ->name('api.invoices.payments.store');

Route::get('/invoices/{invoice:invoice_number}/payment-detail', [InvoicePaymentDetailController::class, 'show'])
    ->name('api.invoices.payment-detail.show');

Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'download'])
    ->middleware(['auth', 'permission:invoice.export', 'throttle:invoice-pdf'])
    ->name('api.invoices.pdf.download');

Route::post('/invoices/preview/pdf', InvoicePreviewPdfController::class)
    ->middleware(['auth', 'permission:invoice.export', 'throttle:invoice-pdf'])
    ->name('api.invoices.preview.pdf.download');

Route::get('/payments/receivables', [ReceivableController::class, 'index'])
    ->name('api.payments.receivables.index');

Route::get('/payments/history', [PaymentHistoryController::class, 'index'])
    ->name('api.payments.history.index');

Route::get('/reports/sales/summary', SalesReportSummaryController::class)
    ->middleware(['auth', 'permission:report.view'])
    ->name('api.reports.sales.summary');

Route::get('/reports/sales/invoices', [SalesReportInvoiceController::class, 'index'])
    ->middleware(['auth', 'permission:report.view'])
    ->name('api.reports.sales.invoices.index');

Route::get('/reports/sales/revenue-chart', SalesReportRevenueChartController::class)
    ->middleware(['auth', 'permission:report.view'])
    ->name('api.reports.sales.revenue-chart');

Route::get('/reports/sales/export', SalesReportExportController::class)
    ->middleware(['auth', 'permission:report.export', 'throttle:report-export'])
    ->name('api.reports.sales.export');

Route::get('/reports/gross-profit', [GrossProfitReportController::class, 'index'])
    ->name('api.reports.gross-profit.index');

Route::get('/reports/gross-profit/export', [GrossProfitReportController::class, 'export'])
    ->middleware('throttle:report-export')
    ->name('api.reports.gross-profit.export');

Route::get('/reports/outstanding-payments', [ReceivableController::class, 'index'])
    ->name('api.reports.outstanding-payments.index');

Route::get('/reports/outstanding-payments/export', [ReportExportController::class, 'outstandingPayments'])
    ->middleware('throttle:report-export')
    ->name('api.reports.outstanding-payments.export');

Route::get('/reports/inactive-customers', CustomerActivityAlertController::class)
    ->name('api.reports.inactive-customers.index');

Route::get('/reports/inactive-customers/export', [ReportExportController::class, 'inactiveCustomers'])
    ->middleware('throttle:report-export')
    ->name('api.reports.inactive-customers.export');

Route::get('/reports/low-stock', ProductLowStockSummaryController::class)
    ->name('api.reports.low-stock.index');

Route::get('/reports/low-stock/export', [ReportExportController::class, 'lowStock'])
    ->middleware('throttle:report-export')
    ->name('api.reports.low-stock.export');

Route::get('/reports/stock-movements', StockMovementReportController::class)
    ->name('api.reports.stock-movements.index');

Route::get('/reports/stock-mutations', StockMovementReportController::class)
    ->name('api.reports.stock-mutations.index');

Route::get('/reports/stock-mutations/export', [ReportExportController::class, 'stockMutations'])
    ->middleware('throttle:report-export')
    ->name('api.reports.stock-mutations.export');
