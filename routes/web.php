<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\CatalogController;
use App\Http\Controllers\Inventory\OpeningStockController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\LocaleController;
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

    Route::view('/pos', 'pos.index')
        ->middleware('permission:pos.access')
        ->name('pos.index');

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

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
