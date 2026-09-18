<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Iniciar consulta cargando relaciones necesarias (evita el problema N+1)
        $query = Order::with(['customer', 'details.product']);

        // 2. Si no es administrador, filtrar únicamente sus órdenes
        // (Ajusta la condición según tu modelo: $user->is_admin, $user->role === 'admin', etc.)
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        // 3. Ordenar descendentemente por fecha/ID (última orden primero)
        $orders = $query->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $orders
        ], 200);
    }

    public function store(
        StoreOrderRequest $request
    ): JsonResponse {
        
        $order = $this->orderService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Venta registrada correctamente.',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(
            'customer',
            'user',
            'details.product',
            'payments'
        );

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }
}