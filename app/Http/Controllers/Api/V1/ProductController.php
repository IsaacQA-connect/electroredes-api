<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {
    }

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $filters = $request->only([
            'search',
            'category_id',
            'low_stock',
        ]);

        if (isset($filters['low_stock'])) {
            $filters['low_stock'] =
                filter_var(
                    $filters['low_stock'],
                    FILTER_VALIDATE_BOOLEAN
                );
        }

        $products = $this->productService
            ->getAll($filters);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product = $this->productService
            ->getById($product->id);

        return new ProductResource($product);
    }

    public function store(
        StoreProductRequest $request
    ): JsonResponse {
        $product = $this->productService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ): JsonResponse {
        $product = $this->productService->update(
            $product,
            $request->validated()
        );

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => new ProductResource($product),
        ]);
    }

    public function changeStatus(
        Product $product
    ): JsonResponse {
        $product = $this->productService->changeStatus(
            $product,
            ! $product->status
        );

        return response()->json([
            'message' => 'Estado del producto actualizado.',
            'data' => new ProductResource($product),
        ]);
    }
}
