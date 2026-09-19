<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\CatalogController;
use App\Http\Controllers\Inventory\OpeningStockController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\ProductSearchController;
use App\Http\Controllers\Sales\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', ['en', 'fa', 'ps'])
    ->name('locale.update');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('pos')->name('pos.')->middleware('permission:pos.access')->group(function () {
        Route::get('/', PosController::class)->name('index');
        Route::get('/products/search', ProductSearchController::class)->name('products.search');
        Route::post('/sales', [SaleController::class, 'store'])
            ->middleware('permission:sales.create')
            ->name('sales.store');
    });

    Route::get('/sales/{sale}', [SaleController::class, 'show'])
        ->middleware('permission:sales.view')
        ->name('sales.show');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])
            ->middleware('permission:inventory.view')
            ->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])
            ->middleware('permission:inventory.products.manage')
            ->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('permission:inventory.products.manage')
            ->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])
            ->middleware('permission:inventory.view')
            ->name('products.show');
        Route::post('/products/{product}/opening-stock', [OpeningStockController::class, 'store'])
            ->middleware('permission:inventory.opening_stock')
            ->name('products.opening-stock.store');

        Route::get('/catalog', [CatalogController::class, 'index'])
            ->middleware('permission:inventory.catalog.manage')
            ->name('catalog.index');
        Route::post('/catalog/categories', [CatalogController::class, 'storeCategory'])
            ->middleware('permission:inventory.catalog.manage')
            ->name('catalog.categories.store');
        Route::post('/catalog/brands', [CatalogController::class, 'storeBrand'])
            ->middleware('permission:inventory.catalog.manage')
            ->name('catalog.brands.store');
        Route::post('/catalog/units', [CatalogController::class, 'storeUnit'])
            ->middleware('permission:inventory.catalog.manage')
            ->name('catalog.units.store');
    });

    Route::prefix('purchasing')->name('purchasing.')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])
            ->middleware('permission:suppliers.view')
            ->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])
            ->middleware('permission:suppliers.manage')
            ->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
            ->middleware('permission:suppliers.view')
            ->name('suppliers.show');

        Route::get('/orders', [PurchaseOrderController::class, 'index'])
            ->middleware('permission:purchases.view')
            ->name('orders.index');
        Route::get('/orders/create', [PurchaseOrderController::class, 'create'])
            ->middleware('permission:purchases.create')
            ->name('orders.create');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])
            ->middleware('permission:purchases.create')
            ->name('orders.store');
        Route::get('/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->middleware('permission:purchases.view')
            ->name('orders.show');
        Route::post('/orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->middleware('permission:purchases.approve')
            ->name('orders.approve');
        Route::post('/orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware('permission:purchases.approve')
            ->name('orders.cancel');

        Route::get('/receipts', [GoodsReceiptController::class, 'index'])
            ->middleware('permission:purchases.view')
            ->name('receipts.index');
        Route::get('/orders/{purchaseOrder}/receive', [GoodsReceiptController::class, 'createForOrder'])
            ->middleware('permission:purchases.receive')
            ->name('receipts.create-for-order');
        Route::get('/receipts/direct/create', [GoodsReceiptController::class, 'createDirect'])
            ->middleware('permission:purchases.direct_receive')
            ->name('receipts.create-direct');
        Route::post('/receipts', [GoodsReceiptController::class, 'store'])
            ->middleware('permission:purchases.receive')
            ->name('receipts.store');
        Route::get('/receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])
            ->middleware('permission:purchases.view')
            ->name('receipts.show');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
