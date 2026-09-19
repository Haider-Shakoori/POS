<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customers\CustomerCollectionController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerSearchController;
use App\Http\Controllers\Cash\CashDrawerController;
use App\Http\Controllers\Cash\ManualCashMovementController;
use App\Http\Controllers\Cash\OperatingEntryController;
use App\Http\Controllers\Cash\ShiftOpeningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\CatalogController;
use App\Http\Controllers\Inventory\OpeningStockController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseReturnController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Purchasing\SupplierPaymentController;
use App\Http\Controllers\Sales\HeldSaleController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\ProductSearchController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleReturnController;
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
        Route::post('/customers', [CustomerController::class, 'store'])
            ->middleware('permission:customers.quick_create')
            ->name('customers.store');

        Route::get('/held-sales', [HeldSaleController::class, 'index'])
            ->middleware('permission:sales.hold')
            ->name('held.index');
        Route::post('/held-sales', [HeldSaleController::class, 'store'])
            ->middleware('permission:sales.hold')
            ->name('held.store');
        Route::post('/held-sales/{heldSale}/resume', [HeldSaleController::class, 'resume'])
            ->middleware('permission:sales.hold')
            ->name('held.resume');
        Route::post('/held-sales/{heldSale}/release', [HeldSaleController::class, 'release'])
            ->middleware('permission:sales.hold')
            ->name('held.release');
    });

    Route::prefix('cash')->name('cash.')->group(function () {
        Route::get('/', CashDrawerController::class)
            ->middleware('permission:cash.view')
            ->name('index');
        Route::post('/shifts', [ShiftOpeningController::class, 'store'])
            ->middleware('permission:shifts.open')
            ->name('shifts.store');
        Route::post('/operating-entries', [OperatingEntryController::class, 'store'])
            ->middleware('permission:expenses.create')
            ->name('operating-entries.store');
        Route::post('/shifts/{cashierShift}/movements', [ManualCashMovementController::class, 'store'])
            ->middleware('permission:cash.manage')
            ->name('movements.store');
    });

    Route::get('/sales', [SaleController::class, 'index'])
        ->middleware('permission:sales.view')
        ->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])
        ->middleware('permission:sales.view')
        ->name('sales.show');
    Route::post('/sales/{sale}/returns', [SaleReturnController::class, 'store'])
        ->middleware('permission:sales.return')
        ->name('sales.returns.store');
    Route::post('/sales/{sale}/void', [SaleReturnController::class, 'void'])
        ->middleware('permission:sales.void')
        ->name('sales.void');

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])
            ->middleware('permission:customers.view')
            ->name('index');
        Route::get('/search', CustomerSearchController::class)
            ->middleware('permission:customers.view')
            ->name('search');
        Route::post('/', [CustomerController::class, 'store'])
            ->middleware('permission:customers.manage')
            ->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])
            ->middleware('permission:customers.view')
            ->name('show');
        Route::post('/{customer}/collections', [CustomerCollectionController::class, 'store'])
            ->middleware('permission:customers.collect')
            ->name('collections.store');
    });

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
        Route::post('/suppliers/{supplier}/payments', [SupplierPaymentController::class, 'store'])
            ->middleware('permission:suppliers.pay')
            ->name('suppliers.payments.store');

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
        Route::post('/receipts/{goodsReceipt}/returns', [PurchaseReturnController::class, 'store'])
            ->middleware('permission:purchases.return')
            ->name('receipts.returns.store');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
