<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\CashBankController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\CustomerActivityAlertController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerOptionController;
use App\Http\Controllers\Api\CustomerStatementController;
use App\Http\Controllers\Api\CustomerSalesProfitReportController;
use App\Http\Controllers\Api\CustomerTransactionHistoryController;
use App\Http\Controllers\Api\DueInvoiceNotificationController;
use App\Http\Controllers\Api\FinancialSummaryController;
use App\Http\Controllers\Api\GoodsReceiptCancellationController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\GoodsReceiptPostingController;
use App\Http\Controllers\Api\GrossProfitReportController;
use App\Http\Controllers\Api\InvoiceCancellationController;
use App\Http\Controllers\Api\InvoiceDeliveryController;
use App\Http\Controllers\Api\InvoiceDeliveryNotePdfController;
use App\Http\Controllers\Api\InvoiceDraftController;
use App\Http\Controllers\Api\InvoiceFollowUpController;
use App\Http\Controllers\Api\InvoicePaymentController;
use App\Http\Controllers\Api\InvoicePaymentDetailController;
use App\Http\Controllers\Api\InvoiceListExportController;
use App\Http\Controllers\Api\InvoicePdfController;
use App\Http\Controllers\Api\InvoicePreviewPdfController;
use App\Http\Controllers\Api\PaymentHistoryController;
use App\Http\Controllers\Api\ProductBulkStockController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductLowStockSummaryController;
use App\Http\Controllers\Api\ProductOptionController;
use App\Http\Controllers\Api\PurchaseOrderApprovalController;
use App\Http\Controllers\Api\PurchaseOrderCancellationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderSubmissionController;
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
use App\Http\Controllers\Api\StockReportExportController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPriceActiveController;
use App\Http\Controllers\Api\SupplierPriceListController;
use App\Http\Controllers\Api\ThemeDefaultSettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', fn () => response()->json([
    'message' => 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.',
], 403));

Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['web', 'throttle:login'])
    ->name('api.auth.login');

Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['web', 'auth'])
    ->name('api.auth.logout');

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/dashboard/financial-summary', FinancialSummaryController::class)
        ->middleware('permission:dashboard.view')
        ->name('api.dashboard.financial-summary');

    Route::get('/dashboard/revenue-chart', RevenueChartController::class)
        ->middleware('permission:dashboard.view')
        ->name('api.dashboard.revenue-chart');

    Route::get('/dashboard/activities', RecentActivitiesController::class)
        ->middleware('permission:dashboard.view')
        ->name('api.dashboard.activities');

    Route::get('/dashboard/customer-activity-alerts', CustomerActivityAlertController::class)
        ->middleware('permission:dashboard.view')
        ->name('api.dashboard.customer-activity-alerts');

    Route::get('/cash-bank/summary', [CashBankController::class, 'summary'])
        ->middleware('permission:cash_bank.view')
        ->name('api.cash-bank.summary');
    Route::get('/cash-bank/transactions', [CashBankController::class, 'index'])
        ->middleware('permission:cash_bank.view')
        ->name('api.cash-bank.transactions.index');
    Route::get('/cash-bank/transactions/export', [CashBankController::class, 'export'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.cash-bank.transactions.export');
    Route::post('/cash-bank/transactions', [CashBankController::class, 'store'])
        ->middleware('permission:cash_bank.create')
        ->name('api.cash-bank.transactions.store');
    Route::match(['put', 'patch'], '/cash-bank/transactions/{transaction}', [CashBankController::class, 'update'])
        ->middleware('permission:cash_bank.update')
        ->name('api.cash-bank.transactions.update');
    Route::delete('/cash-bank/transactions/{transaction}', [CashBankController::class, 'destroy'])
        ->middleware('permission:cash_bank.delete')
        ->name('api.cash-bank.transactions.destroy');
    Route::patch('/cash-bank/account', [CashBankController::class, 'updateAccount'])
        ->middleware('permission:cash_bank.update')
        ->name('api.cash-bank.account.update');

    Route::get('/company-profile', [CompanyProfileController::class, 'show'])
        ->middleware('permission:setting.view')
        ->name('api.company-profile.show');

    Route::match(['put', 'patch'], '/company-profile', [CompanyProfileController::class, 'update'])
        ->middleware('permission:setting.update')
        ->name('api.company-profile.update');

    Route::post('/company-profile/logo', [CompanyProfileController::class, 'uploadLogo'])
        ->middleware('permission:setting.update')
        ->name('api.company-profile.logo.store');

    Route::get('/settings/theme-defaults', [ThemeDefaultSettingController::class, 'show'])
        ->middleware('permission:setting.view')
        ->name('api.settings.theme-defaults.show');

    Route::match(['put', 'patch'], '/settings/theme-defaults', [ThemeDefaultSettingController::class, 'update'])
        ->middleware('permission:setting.update')
        ->name('api.settings.theme-defaults.update');

    Route::get('/customers', [CustomerOptionController::class, 'index'])
        ->middleware('permission:customer.view')
        ->name('api.customers.index');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:customer.create')
        ->name('api.customers.store');

    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('permission:customer.view')
        ->name('api.customers.show');

    Route::get('/customers/{customer}/transactions', [CustomerTransactionHistoryController::class, 'index'])
        ->middleware('permission:customer.view')
        ->name('api.customers.transactions.index');

    Route::get('/customers/{customer}/statement', CustomerStatementController::class)
        ->middleware('permission:customer.view')
        ->name('api.customers.statement.show');

    Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customer.update')
        ->name('api.customers.update');

    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:customer.delete')
        ->name('api.customers.destroy');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:product.view')
        ->name('api.products.index');

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:product.create')
        ->name('api.products.store');

    Route::get('/products/low-stock-summary', ProductLowStockSummaryController::class)
        ->middleware('permission:product.view')
        ->name('api.products.low-stock-summary');

    Route::get('/products/options', [ProductOptionController::class, 'index'])
        ->middleware('permission:product.view')
        ->name('api.products.options.index');

    Route::patch('/products/bulk-stock', ProductBulkStockController::class)
        ->middleware('permission:product.update')
        ->name('api.products.bulk-stock.update');

    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:product.view')
        ->name('api.products.show');

    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:product.update')
        ->name('api.products.update');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:product.delete')
        ->name('api.products.destroy');

    Route::get('/product-categories', [ProductCategoryController::class, 'index'])
        ->middleware('permission:product.view')
        ->name('api.product-categories.index');

    Route::apiResource('suppliers', SupplierController::class)
        ->middlewareFor(['index', 'show'], 'permission:product.view')
        ->middlewareFor('store', 'permission:product.create')
        ->middlewareFor('update', 'permission:product.update')
        ->middlewareFor('destroy', 'permission:product.delete')
        ->names('api.suppliers');

    Route::apiResource('purchase-orders', PurchaseOrderController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->middlewareFor(['index', 'show'], 'permission:purchase_order.view')
        ->middlewareFor('store', 'permission:purchase_order.create')
        ->middlewareFor('update', 'permission:purchase_order.update')
        ->names('api.purchase-orders');

    Route::post('/purchase-orders/{purchase_order}/submit', [PurchaseOrderSubmissionController::class, 'store'])
        ->middleware('permission:purchase_order.submit')
        ->name('api.purchase-orders.submit');

    Route::post('/purchase-orders/{purchase_order}/approve', [PurchaseOrderApprovalController::class, 'store'])
        ->middleware('permission:purchase_order.approve')
        ->name('api.purchase-orders.approve');

    Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderCancellationController::class, 'store'])
        ->middleware('permission:purchase_order.cancel')
        ->name('api.purchase-orders.cancel');

    Route::post('/purchase-orders/{purchase_order}/goods-receipts', [GoodsReceiptController::class, 'store'])
        ->middleware('permission:goods_receipt.create')
        ->name('api.purchase-orders.goods-receipts.store');

    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])
        ->middleware('permission:goods_receipt.view')
        ->name('api.goods-receipts.index');

    Route::get('/goods-receipts/{goods_receipt}', [GoodsReceiptController::class, 'show'])
        ->middleware('permission:goods_receipt.view')
        ->name('api.goods-receipts.show');

    Route::post('/goods-receipts/{goods_receipt}/post', [GoodsReceiptPostingController::class, 'store'])
        ->middleware('permission:goods_receipt.post')
        ->name('api.goods-receipts.post');

    Route::post('/goods-receipts/{goods_receipt}/cancel', [GoodsReceiptCancellationController::class, 'store'])
        ->middleware('permission:goods_receipt.cancel')
        ->name('api.goods-receipts.cancel');

    // Registered before the apiResource below so the literal "active"
    // segment wins over the resource's {supplier_price} show route.
    Route::get('/supplier-prices/active', [SupplierPriceActiveController::class, 'show'])
        ->middleware('permission:supplier_price.view')
        ->name('api.supplier-prices.active');

    Route::apiResource('supplier-prices', SupplierPriceListController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->middlewareFor(['index', 'show'], 'permission:supplier_price.view')
        ->middlewareFor('store', 'permission:supplier_price.create')
        ->middlewareFor('update', 'permission:supplier_price.update')
        ->names('api.supplier-prices');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:role.view')
        ->name('api.roles.index');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:role.create')
        ->name('api.roles.store');

    Route::get('/roles/{role:code}', [RoleController::class, 'show'])
        ->middleware('permission:role.view')
        ->name('api.roles.show');

    Route::match(['put', 'patch'], '/roles/{role:code}', [RoleController::class, 'update'])
        ->middleware('permission:role.update')
        ->name('api.roles.update');

    Route::delete('/roles/{role:code}', [RoleController::class, 'destroy'])
        ->middleware('permission:role.delete')
        ->name('api.roles.destroy');

    Route::get('/roles/{role:code}/permissions', [RolePermissionController::class, 'show'])
        ->middleware('permission:role.view')
        ->name('api.roles.permissions.show');

    Route::match(['put', 'patch'], '/roles/{role:code}/permissions', [RolePermissionController::class, 'update'])
        ->middleware('permission:role.update')
        ->name('api.roles.permissions.update');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:activity_log.view')
        ->name('api.activity-logs.index');

    Route::get('/notifications/due-invoices', [DueInvoiceNotificationController::class, 'index'])
        ->middleware('permission:payment.view')
        ->name('api.notifications.due-invoices.index');

    Route::post('/invoices/drafts', [InvoiceDraftController::class, 'store'])
        ->middleware('permission:invoice.create')
        ->name('api.invoices.drafts.store');
    Route::get('/invoices/export/csv', [InvoiceListExportController::class, 'csv'])
        ->middleware(['permission:invoice.export', 'throttle:report-export'])
        ->name('api.invoices.export.csv');
    Route::get('/invoices/export/pdf', [InvoiceListExportController::class, 'pdf'])
        ->middleware(['permission:invoice.export', 'throttle:report-export'])
        ->name('api.invoices.export.pdf');

    Route::post('/stock-movements', [StockMovementController::class, 'store'])
        ->middleware('permission:product.update')
        ->name('api.stock-movements.store');

    Route::post('/invoices/{invoice}/send-whatsapp', [InvoiceDeliveryController::class, 'sendWhatsApp'])
        ->middleware('permission:invoice.update')
        ->name('api.invoices.send-whatsapp');

    Route::post('/invoices/{invoice:invoice_number}/payments', [InvoicePaymentController::class, 'store'])
        ->middleware('permission:payment.create')
        ->name('api.invoices.payments.store');

    Route::get('/invoices/{invoice:invoice_number}/payment-detail', [InvoicePaymentDetailController::class, 'show'])
        ->middleware('permission:invoice.view')
        ->name('api.invoices.payment-detail.show');

    Route::post('/invoices/{invoice:invoice_number}/follow-up', [InvoiceFollowUpController::class, 'store'])
        ->middleware('permission:invoice.update')
        ->name('api.invoices.follow-up.store');

    Route::post('/invoices/{invoice:invoice_number}/cancel', [InvoiceCancellationController::class, 'store'])
        ->middleware('permission:invoice.update')
        ->name('api.invoices.cancel.store');

    Route::match(['put', 'patch'], '/invoices/{invoice:invoice_number}', [InvoiceDraftController::class, 'update'])
        ->middleware('permission:invoice.update')
        ->name('api.invoices.update');

    Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'download'])
        ->middleware(['permission:invoice.export', 'throttle:invoice-pdf'])
        ->name('api.invoices.pdf.download');

    Route::get('/invoices/{invoice}/delivery-note/pdf', [InvoiceDeliveryNotePdfController::class, 'download'])
        ->middleware(['permission:invoice.export', 'throttle:invoice-pdf'])
        ->name('api.invoices.delivery-note.pdf.download');

    Route::post('/invoices/preview/pdf', InvoicePreviewPdfController::class)
        ->middleware(['permission:invoice.export', 'throttle:invoice-pdf'])
        ->name('api.invoices.preview.pdf.download');

    Route::get('/payments/receivables', [ReceivableController::class, 'index'])
        ->middleware('permission:payment.view')
        ->name('api.payments.receivables.index');

    Route::get('/payments/history', [PaymentHistoryController::class, 'index'])
        ->middleware('permission:payment.view')
        ->name('api.payments.history.index');

    Route::get('/reports/sales/summary', SalesReportSummaryController::class)
        ->middleware('permission:report.view')
        ->name('api.reports.sales.summary');

    Route::get('/reports/sales/invoices', [SalesReportInvoiceController::class, 'index'])
        ->middleware('permission:report.view')
        ->name('api.reports.sales.invoices.index');

    Route::get('/reports/sales/revenue-chart', SalesReportRevenueChartController::class)
        ->middleware('permission:report.view')
        ->name('api.reports.sales.revenue-chart');

    Route::get('/reports/sales/export', SalesReportExportController::class)
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.sales.export');

    Route::get('/reports/gross-profit', [GrossProfitReportController::class, 'index'])
        ->middleware('permission:report.view')
        ->name('api.reports.gross-profit.index');

    Route::get('/reports/gross-profit/export', [GrossProfitReportController::class, 'export'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.gross-profit.export');

    Route::get('/reports/customer-sales', [CustomerSalesProfitReportController::class, 'index'])
        ->middleware('permission:report.view')
        ->name('api.reports.customer-sales.index');
    Route::get('/reports/customer-sales/export', [CustomerSalesProfitReportController::class, 'export'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.customer-sales.export');
    Route::get('/reports/customer-sales/pdf', [CustomerSalesProfitReportController::class, 'pdf'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.customer-sales.pdf');

    Route::get('/reports/outstanding-payments', [ReceivableController::class, 'index'])
        ->middleware('permission:report.view')
        ->name('api.reports.outstanding-payments.index');

    Route::get('/reports/outstanding-payments/export', [ReportExportController::class, 'outstandingPayments'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.outstanding-payments.export');

    Route::get('/reports/inactive-customers', CustomerActivityAlertController::class)
        ->middleware('permission:report.view')
        ->name('api.reports.inactive-customers.index');

    Route::get('/reports/inactive-customers/export', [ReportExportController::class, 'inactiveCustomers'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.inactive-customers.export');

    Route::get('/reports/low-stock', ProductLowStockSummaryController::class)
        ->middleware('permission:report.view')
        ->name('api.reports.low-stock.index');

    Route::get('/reports/low-stock/export', [ReportExportController::class, 'lowStock'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.low-stock.export');

    Route::get('/reports/stock-movements', StockMovementReportController::class)
        ->middleware('permission:report.view')
        ->name('api.reports.stock-movements.index');

    Route::get('/reports/stock-mutations', StockMovementReportController::class)
        ->middleware('permission:report.view')
        ->name('api.reports.stock-mutations.index');

    Route::get('/reports/stock-mutations/export', [StockReportExportController::class, 'csv'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.stock-mutations.export');
    Route::get('/reports/stock-mutations/csv', [StockReportExportController::class, 'csv'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.stock-mutations.csv');
    Route::get('/reports/stock-mutations/pdf', [StockReportExportController::class, 'pdf'])
        ->middleware(['permission:report.export', 'throttle:report-export'])
        ->name('api.reports.stock-mutations.pdf');
});
