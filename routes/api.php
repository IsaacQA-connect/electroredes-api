<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderReturnController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\BrandController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use App\Http\Controllers\Api\V1\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // =======================================================
    // 1. RUTAS PÚBLICAS (Accesibles sin autenticación)
    // =======================================================
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);    
    Route::get('/brands', [BrandController::class, 'index']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::post('payments/webhook', [PaymentController::class, 'handleWebhook']);

    // 1. Olvidó contraseña (Solicitar token)
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
    // 2. Cambiar contraseña con token
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    // 3. Verificar correo electrónico desde la URL firmada
    Route::get('/email/verify/{id}/{hash}', function (Request $request,$id, $hash) {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        // Validar que el hash del correo coincida
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false, 
                'message' => 'El enlace de verificación no es válido.'
            ], 400);
        }

        // Comprobar si ya estaba verificado
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true, 
                'message' => 'El correo electrónico ya ha sido verificado previamente.'
            ]);
        }

        // Marcar el correo como verificado y disparar el evento de Laravel
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'success' => true,
            'message' => '¡Correo electrónico verificado con éxito!'
        ]);
    })->middleware(['signed'])->name('verification.verify');
    // 4. Reenviar correo de verificación
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['success' => true, 'message' => 'Enlace de verificación enviado.']);    
    })->middleware(['auth:sanctum', 'throttle:6,1']);

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
    });

    // =======================================================
    // 2. RUTAS PROTEGIDAS (Requieren Token / auth:sanctum)
    // =======================================================
    Route::middleware('auth:sanctum')->group(function () {

        // Autenticación de Usuario
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        // Reenviar correo de verificación (máximo 6 intentos por minuto)
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1');

        // Categorías (Gestión administrativa: excluye index y show para no bloquear el catálogo público)
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show', 'destroy']);
        Route::patch('categories/{category}/status', [CategoryController::class, 'changeStatus']);

        // Productos (Gestión administrativa)
        Route::apiResource('products', ProductController::class)->except(['index', 'show', 'destroy']);
        Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);
        Route::patch('products/{product}/status', [ProductController::class, 'changeStatus']);

        // Clientes
        Route::apiResource('customers', CustomerController::class)->except(['destroy']);
        Route::patch('customers/{customer}/status', [CustomerController::class, 'changeStatus']);

        // Compras
        Route::get('purchases', [PurchaseController::class, 'index']);
        Route::post('purchases', [PurchaseController::class, 'store']);
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show']);

        // Inventario
        Route::get('inventory/stock', [InventoryController::class, 'stock']);
        Route::get('inventory/low-stock', [InventoryController::class, 'lowStock']);
        Route::get('inventory/movements', [InventoryController::class, 'movements']);
        Route::get('inventory/movements/{movement}', [InventoryController::class, 'showMovement']);
        Route::post('inventory/initial-stock', [InventoryController::class, 'initialStock']);
        Route::get('inventory/valuation', [InventoryController::class, 'valuation']);
        Route::post('inventory/adjustments/entry', [InventoryController::class, 'adjustmentEntry']);
        Route::post('inventory/adjustments/exit', [InventoryController::class, 'adjustmentExit']);
        Route::get('inventory/kardex/{product}', [InventoryController::class, 'kardex']);

        // Pedidos y Devoluciones
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/returns', [OrderReturnController::class, 'store']);
        Route::get('orders/{order}/returns', [OrderReturnController::class, 'index']);
        Route::get('returns/{orderReturn}', [OrderReturnController::class, 'show']);
        Route::put('orders/{order}', [OrderController::class, 'update']);

        // Proveedores
        Route::apiResource('suppliers', SupplierController::class);

        // Control de Cajas
        Route::get('cash-registers/current', [CashRegisterController::class, 'current']);
        Route::post('cash-registers/open', [CashRegisterController::class, 'open']);
        Route::post('cash-registers/{cashRegister}/close', [CashRegisterController::class, 'close']);
        Route::post('cash-registers/{cashRegister}/movements', [CashRegisterController::class, 'addMovement']);

        // Pasarela de Pagos
        Route::post('payments/create-preference', [PaymentController::class, 'createPreference']);
        Route::post('payments/confirm', [PaymentController::class, 'confirmPayment']);

        // Dashboard Metrics
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

        Route::prefix('admin')->group(function () {
            Route::get('/users/roles', [UserController::class, 'roles']);
            Route::patch('/users/{user}/toggle-verification', [UserController::class, 'toggleVerification']);
            Route::apiResource('/users', UserController::class);
        });

        // Consulta Ruc
        Route::get('/lookup/{type}/{number}', [App\Http\Controllers\CustomerLookupController::class, 'lookup']);

        // Facturación Electrónica
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('/invoices/{invoice}/ticket', [InvoiceController::class, 'streamTicket']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'streamPdf']);
        Route::post('/invoices/{invoice}/resend-sunat', [InvoiceController::class, 'resendSunat']);

    });
});