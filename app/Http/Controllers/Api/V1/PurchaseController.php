<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchaseService
    ) {
    }

    public function index()
    {
        return PurchaseResource::collection(
            $this->purchaseService->getAll()
        );
    }

    public function store(
        StorePurchaseRequest $request
    ): JsonResponse {

        $purchase = $this->purchaseService->register(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Compra registrada correctamente.',
            'data' => new PurchaseResource($purchase),
        ], 201);
    }

    public function show($id)
    {
        $purchase = \App\Models\Purchase::findOrFail($id);

        return new PurchaseResource(
            $this->purchaseService->getById($purchase)
        );
    }
}
