<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use App\Models\Purchase;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchaseService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'user', 'details.product'])
            ->latest('purchase_date')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => PurchaseResource::collection($purchases),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->purchaseService->register(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Compra registrada e ingresada al inventario correctamente.',
            'data' => new PurchaseResource($purchase),
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['supplier', 'user', 'details.product']);

        return response()->json([
            'data' => new PurchaseResource($purchase),
        ]);
    }
}
