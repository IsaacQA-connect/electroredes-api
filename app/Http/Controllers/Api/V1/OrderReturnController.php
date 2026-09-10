<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderReturnRequest;
use App\Http\Resources\OrderReturnResource;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;

class OrderReturnController extends Controller
{
    public function __construct(
        protected OrderReturnService $orderReturnService
    ) {
    }

    public function store(
        StoreOrderReturnRequest $request,
        Order $order
    ): JsonResponse {

        $orderReturn = $this->orderReturnService->create(
            $order,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Devolución registrada correctamente.',
            'data' => new OrderReturnResource($orderReturn),
        ], 201);
    }

    public function index(Order $order): JsonResponse
    {
        $returns = $order->returns()
            ->with([
                'user',
                'details.orderDetail.product',
            ])
            ->latest('return_date')
            ->paginate(20);

        return response()->json([
            'data' => OrderReturnResource::collection($returns),
        ]);
    }

    public function show(OrderReturn $orderReturn): JsonResponse
    {
        $orderReturn->load([
            'order',
            'user',
            'details.orderDetail.product',
        ]);

        return response()->json([
            'data' => new OrderReturnResource($orderReturn),
        ]);
    }
}