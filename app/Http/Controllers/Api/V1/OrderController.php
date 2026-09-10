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