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
        // Si quieres traer todas las órdenes (perfil administrador):
        $orders = Order::all();
        
        // O si prefieres filtrar solo las órdenes del usuario logueado en Vue:
        $orders = Order::where('user_id', $request->user()->id)->get();

        return response()->json($orders, 200);
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