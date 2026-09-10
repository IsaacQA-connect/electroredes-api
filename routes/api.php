<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderReturnController;
use App\Http\Controllers\Api\V1\SupplierController;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {

        Route::post('/login', [
            AuthController::class,
            'login'
        ]);

        Route::middleware('auth:sanctum')->group(function () {

            Route::get('/me', [
                AuthController::class,
                'me'
            ]);

            Route::post('/logout', [
                AuthController::class,
                'logout'
            ]);

            Route::apiResource('categories', CategoryController::class)
                ->except(['destroy']);

            Route::patch(
                'categories/{category}/status',
                [CategoryController::class, 'changeStatus']
            );

            Route::apiResource('categories', CategoryController::class)
                ->except(['destroy']);

            Route::patch(
                'categories/{category}/status',
                [CategoryController::class, 'changeStatus']
            );

            Route::apiResource('products', ProductController::class)
                ->except(['destroy']);

            Route::patch(
                'products/{product}/status',
                [ProductController::class, 'changeStatus']
            );

            Route::apiResource('customers', CustomerController::class)
                ->except(['destroy']);

            Route::patch(
                'customers/{customer}/status',
                [CustomerController::class, 'changeStatus']
            );

            Route::get('purchases', [
                PurchaseController::class,
                'index'
            ]);

            Route::post('purchases', [
                PurchaseController::class,
                'store'
            ]);

            Route::get('purchases/{purchase}', [
                PurchaseController::class,
                'show'
            ]);

            Route::get(
                'inventory/stock',
                [InventoryController::class, 'stock']
            );

            Route::get(
                'inventory/low-stock',
                [InventoryController::class, 'lowStock']
            );

            Route::get(
                'inventory/movements',
                [InventoryController::class, 'movements']
            );

            Route::get(
                'inventory/movements/{movement}',
                [InventoryController::class, 'showMovement']
            );

            Route::post(
                'inventory/initial-stock',
                [InventoryController::class, 'initialStock']
            );

            Route::get(
                'inventory/valuation',
                [InventoryController::class, 'valuation']
            );

            Route::post(
                'inventory/adjustments/entry',
                [InventoryController::class, 'adjustmentEntry']
            );

            Route::post(
                'inventory/adjustments/exit',
                [InventoryController::class, 'adjustmentExit']
            );

            Route::post('orders', [
                OrderController::class,
                'store'
            ]);

            Route::get('orders/{order}', [
                OrderController::class,
                'show'
            ]);

            Route::post(
                'orders/{order}/returns',
                [OrderReturnController::class, 'store']
            );

            Route::get(
                'orders/{order}/returns',
                [OrderReturnController::class, 'index']
            );

            Route::get(
                'returns/{orderReturn}',
                [OrderReturnController::class, 'show']
            );

            //Route::get('suppliers', [SupplierController::class, 'index']);
            //Route::post('suppliers', [SupplierController::class, 'store']);
            //Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
            //Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            //Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
            Route::apiResource('suppliers', SupplierController::class);

        });

    });

});
