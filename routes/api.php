<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('admin')->group(function (): void {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('/products/import', [ProductController::class, 'import']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);

    Route::get('/attributes', [AttributeController::class, 'index']);
    Route::post('/attributes', [AttributeController::class, 'store']);
    Route::put('/attributes/{attribute}', [AttributeController::class, 'update']);
});

Route::prefix('catalog')->group(function (): void {
    Route::get('/categories/{slug}', [ProductController::class, 'categoryListing']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
});