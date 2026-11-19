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
        $query = Purchase::with(['supplier', 'user', 'details.product']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'LIKE', "%{$search}%")
                ->orWhereHas('supplier', fn($s) => $s->where('business_name', 'LIKE', "%{$search}%")
                    ->orWhere('document_number', 'LIKE', "%{$search}%"));
            });
        }

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($docType = $request->query('document_type')) {
            $query->where('document_type', $docType);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $purchases = $query->latest('purchase_date')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => PurchaseResource::collection($purchases),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page'    => $purchases->lastPage(),
                'per_page'     => $purchases->perPage(),
                'total'        => $purchases->total(),
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
