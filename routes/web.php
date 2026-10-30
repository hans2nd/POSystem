<?php

use App\Http\Controllers\ApprovalRuleController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MasterExportImportController;
use App\Http\Controllers\PaymentTermController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\POPaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TaxReviewController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Installation Setup Wizard (No Login Required)
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::post('/check-db', [InstallController::class, 'checkDb'])->name('check-db');
    Route::post('/save-config', [InstallController::class, 'saveConfig'])->name('save-config');
    Route::post('/run-step', [InstallController::class, 'runStep'])->name('run-step');
    Route::post('/unlock', [InstallController::class, 'unlock'])->name('unlock');
});

// Root redirect
Route::get('/', function () {
    if (!InstallController::isInstalled()) {
        return redirect()->route('install.index');
    }
    return auth()->check() 
        ? redirect()->route('dashboard') 
        : redirect()->route('login');
});

// Guest routes (Authentication)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// External Approval Routes (Direct Approval via Email Magic Link - No Login Required)
Route::get('/po-approval/{token}', [PurchaseOrderController::class, 'externalApproval'])->name('po.external-approval');
Route::post('/po-approval/{token}/approve', [PurchaseOrderController::class, 'processExternalApprove'])->name('po.external-approve');
Route::post('/po-approval/{token}/reject', [PurchaseOrderController::class, 'processExternalReject'])->name('po.external-reject');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Management (Semua User Login)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Master Data (Khusus HR dan Administrator)
    Route::middleware('master.data')->group(function () {
        // Categories & Asset Types
        Route::resource('categories', CategoryController::class);
        Route::get('/api/asset-types', [AssetTypeController::class, 'getActiveList'])->name('api.asset-types.index');
        Route::resource('asset-types', AssetTypeController::class);

        // Products
        Route::resource('products', ProductController::class);

        // Vendors
        Route::resource('vendors', VendorController::class);

        // Branches
        Route::resource('branches', BranchController::class);

        // Payment Terms
        Route::resource('payment-terms', PaymentTermController::class);

        // Departments
        Route::resource('departments', DepartmentController::class);

        // Excel Import & Export
        Route::get('/master-data/{type}/export', [MasterExportImportController::class, 'export'])->name('master-data.export');
        Route::get('/master-data/{type}/template', [MasterExportImportController::class, 'template'])->name('master-data.template');
        Route::post('/master-data/{type}/import', [MasterExportImportController::class, 'import'])->name('master-data.import');

        // Approval Rules
        Route::resource('approval-rules', ApprovalRuleController::class)->except(['create', 'edit']);
        Route::post('/approval-rules/{approvalRule}/toggle', [ApprovalRuleController::class, 'toggle'])->name('approval-rules.toggle');
    });

    // Purchase Requests
    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index')->middleware('can:view purchase-requests');
    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create')->middleware('can:create purchase-requests');
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store')->middleware('can:create purchase-requests');
    Route::get('/purchase-requests/{purchase_request}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show')->middleware('can:view purchase-requests');
    Route::get('/purchase-requests/{purchase_request}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase-requests.edit')->middleware('can:edit purchase-requests');
    Route::put('/purchase-requests/{purchase_request}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update')->middleware('can:edit purchase-requests');
    Route::delete('/purchase-requests/{purchase_request}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy')->middleware('can:delete purchase-requests');
    Route::get('/purchase-requests/{purchase_request}/download', [PurchaseRequestController::class, 'downloadAttachment'])->name('purchase-requests.download')->middleware('can:view purchase-requests');
    Route::post('/purchase-requests/{purchase_request}/attach-po', [PurchaseRequestController::class, 'attachPo'])->name('purchase-requests.attach-po')->middleware('can:create purchase-orders');
    Route::post('/purchase-requests/{purchase_request}/detach-po/{purchaseOrder}', [PurchaseRequestController::class, 'detachPo'])->name('purchase-requests.detach-po')->middleware('can:create purchase-orders');

    // Purchase Orders
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index')->middleware('can:view purchase-orders');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create')->middleware('can:create purchase-orders');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store')->middleware('can:create purchase-orders');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show')->middleware('can:view purchase-orders');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit')->middleware('can:edit purchase-orders');
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update')->middleware('can:edit purchase-orders');
    Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy')->middleware('can:delete purchase-orders');
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit')->middleware('can:submit purchase-orders');
    Route::post('/purchase-orders/{purchaseOrder}/revise', [PurchaseOrderController::class, 'revise'])->name('purchase-orders.revise');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve')->middleware('can:approve purchase-orders');
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-orders.reject')->middleware('can:reject purchase-orders');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive')->middleware('can:receive goods-receipts');
    Route::get('/purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print')->middleware('can:print purchase-orders');
    Route::get('/purchase-orders/{purchaseOrder}/preview', [PurchaseOrderController::class, 'preview'])->name('purchase-orders.preview')->middleware('can:print purchase-orders');

    // Goods Receipts (Daftar Barang Masuk)
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index')->middleware('can:view goods-receipts');
    Route::get('/goods-receipts/export', [GoodsReceiptController::class, 'export'])->name('goods-receipts.export')->middleware('can:view goods-receipts');

    // Tax Review (Reviewer Pajak PO)
    Route::get('/tax-reviews', [TaxReviewController::class, 'index'])->name('tax-reviews.index');
    Route::get('/tax-reviews/{purchaseOrder}', [TaxReviewController::class, 'show'])->name('tax-reviews.show');
    Route::put('/tax-reviews/{purchaseOrder}', [TaxReviewController::class, 'update'])->name('tax-reviews.update');
    Route::post('/purchase-orders/{purchaseOrder}/send-tax-review', [TaxReviewController::class, 'sendTaxReviewRequest'])->name('purchase-orders.send-tax-review');

    // PO Payments (Installments)
    Route::post('/purchase-orders/{purchaseOrder}/payments', [POPaymentController::class, 'store'])->name('po-payments.store');
    Route::post('/purchase-orders/{purchaseOrder}/payments/quick', [POPaymentController::class, 'quickCreate'])->name('po-payments.quick');
    Route::post('/po-payments/{payment}/record', [POPaymentController::class, 'recordPayment'])->name('po-payments.record');
    Route::delete('/po-payments/{payment}', [POPaymentController::class, 'destroy'])->name('po-payments.destroy');

    // Asset Management (Daftar Barang Inventaris - Terintegrasi Opsi A dari Penerimaan Barang PO)
    Route::get('/asset-management', [AssetController::class, 'index'])->name('assets.index');
    Route::post('/asset-management/reset-all-qr', [AssetController::class, 'resetAllQr'])->name('assets.reset-all-qr');
    Route::post('/asset-management/{code}/generate-qr', [AssetController::class, 'generateQr'])->name('assets.generate-qr');
    Route::post('/asset-management/{code}/reset-qr', [AssetController::class, 'resetQr'])->name('assets.reset-qr');
    Route::put('/asset-management/{code}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/asset-management/{code}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::get('/daftar-barang', function () {
        return redirect()->route('assets.index');
    });

    // User Management (Admin only)
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    // API for Products (AJAX)
    Route::get('/api/products/{product}', [ProductController::class, 'getProduct'])->name('api.products.show');
    Route::get('/api/products', [ProductController::class, 'getProducts'])->name('api.products.index');

    // Settings (Admin only)
    Route::get('/settings/email', [SettingController::class, 'email'])->name('settings.email');
    Route::post('/settings/email', [SettingController::class, 'updateEmail'])->name('settings.email.update');
    Route::post('/settings/email/test', [SettingController::class, 'testEmail'])->name('settings.email.test');
    Route::get('/settings/utilization', [SettingController::class, 'utilization'])->name('settings.utilization');
    Route::post('/settings/utilization/run', [SettingController::class, 'runUtilization'])->name('settings.utilization.run');
});

// Public PO External Approval (Token-based, No Login Required)
Route::get('/po-approval/{token}', [PurchaseOrderController::class, 'externalApproval'])->name('po.external-approval');
Route::post('/po-approval/{token}/approve', [PurchaseOrderController::class, 'processExternalApprove'])->name('po.external-approve');
Route::post('/po-approval/{token}/reject', [PurchaseOrderController::class, 'processExternalReject'])->name('po.external-reject');
Route::get('/po-approval/{token}/preview-pdf', [PurchaseOrderController::class, 'externalPreviewPdf'])->name('po.external-preview-pdf');
