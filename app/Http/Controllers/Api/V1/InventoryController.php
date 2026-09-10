<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreInitialStockRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Services\InventoryService;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreInventoryAdjustmentRequest;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService
    ) {
    }

    public function initialStock(
        StoreInitialStockRequest $request
    ) {
        $movement = $this->inventoryService
            ->registerInitialStock(
                $request->validated(),
                $request->user()
            );

        return response()->json([
            'message' => 'Inventario inicial registrado correctamente.',
            'data' => new InventoryMovementResource($movement),
        ], 201);
    }

    public function stock(Request $request)
    {
        $products = $this->inventoryService->getStock(
            $request->only([
                'search',
                'category_id',
            ])
        );

        return ProductResource::collection($products);
    }

    public function lowStock()
    {
        return ProductResource::collection(
            $this->inventoryService->getLowStock()
        );
    }

    public function movements(Request $request)
    {
        $movements = $this->inventoryService->getMovements(
            $request->only([
                'type',
                'product_id',
            ])
        );

        return InventoryMovementResource::collection(
            $movements
        );
    }

    public function showMovement(
        \App\Models\InventoryMovement $movement
    ) {
        $movement->load([
            'user',
            'details.product',
        ]);

        return new InventoryMovementResource($movement);
    }

    public function valuation()
    {
        return response()->json(
            $this->inventoryService->getInventoryValuation()
        );
    }

    public function adjustmentEntry(
        StoreInventoryAdjustmentRequest $request
    ) {
        $movement = $this->inventoryService
            ->registerAdjustmentEntry(
                $request->validated(),
                $request->user()
            );

        return response()->json([
            'message' => 'Ajuste de entrada registrado correctamente.',
            'data' => new InventoryMovementResource($movement),
        ], 201);
    }

    public function adjustmentExit(
        StoreInventoryAdjustmentRequest $request
    ) {
        $movement = $this->inventoryService
            ->registerAdjustmentExit(
                $request->validated(),
                $request->user()
            );

        return response()->json([
            'message' => 'Ajuste de salida registrado correctamente.',
            'data' => new InventoryMovementResource($movement),
        ], 201);
    }
}
