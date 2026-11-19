<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'category_id', 'brand_id', 'low_stock']);

        if (isset($filters['low_stock'])) {
            $filters['low_stock'] = filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN);
        }

        $products = $this->productService->getAll($filters);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product = $this->productService->getById($product->id);

        return new ProductResource($product);
    }

    public function lookupBarcode(string $barcode): JsonResponse
    {
        $barcode = preg_replace('/\D/', '', $barcode);

        if (strlen($barcode) < 8) {
            return response()->json([
                'found'   => false,
                'message' => 'El código de barras debe tener al menos 8 dígitos.'
            ], 422);
        }

        // 1. Verificar si ya está registrado en el inventario local
        $existing = Product::where('barcode', $barcode)->first();
        if ($existing) {
            return response()->json([
                'found'          => true,
                'already_exists' => true,
                'product_id'     => $existing->id,
                'name'           => $existing->name,
                'message'        => "Este código ya pertenece al producto: {$existing->name}",
            ]);
        }

        // 2. Consultar APIs públicas con caché de 30 días
        $data = Cache::remember("barcode_lookup_{$barcode}", now()->addDays(30), function () use ($barcode) {
            // Fuente A: UPCitemdb (Tecnología, hardware y retail)
            try {
                $upcRes = Http::timeout(5)
                    ->get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $barcode]);

                if ($upcRes->successful() && !empty($upcRes->json('items.0'))) {
                    $item = $upcRes->json('items.0');
                    return [
                        'found'       => true,
                        'source'      => 'UPCitemdb',
                        'name'        => $item['title'] ?? '',
                        'brand'       => $item['brand'] ?? '',
                        'category'    => $item['category'] ?? '',
                        'description' => $item['description'] ?? '',
                        'image_url'   => $item['images'][0] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // Continúa al siguiente proveedor si hay timeout
            }

            // Fuente B: Open Food Facts (Bebidas, snacks y consumo masivo)
            try {
                $offRes = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'ElectroRedesPOS/1.0 (contacto@soportecenter.com)'])
                    ->get("https://world.openfoodfacts.org/api/v2/product/{$barcode}.json");

                if ($offRes->successful() && $offRes->json('status') === 1) {
                    $product = $offRes->json('product', []);
                    return [
                        'found'       => true,
                        'source'      => 'OpenFoodFacts',
                        'name'        => $product['product_name_es'] ?? $product['product_name'] ?? '',
                        'brand'       => $product['brands'] ?? '',
                        'category'    => $product['categories'] ?? '',
                        'description' => $product['generic_name_es'] ?? $product['quantity'] ?? '',
                        'image_url'   => $product['image_front_url'] ?? $product['image_url'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // Silencioso
            }

            return ['found' => false];
        });

        return response()->json($data);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data'    => new ProductResource($product),
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update($product, $request->validated());

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data'    => new ProductResource($product),
        ]);
    }

    public function changeStatus(Product $product): JsonResponse
    {
        $product = $this->productService->changeStatus($product, !$product->status);

        return response()->json([
            'message' => 'Estado del producto actualizado.',
            'data'    => new ProductResource($product),
        ]);
    }

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $product = $this->productService->uploadImage($product, $request->file('image'));

        return response()->json([
            'message' => 'Imagen del producto actualizada.',
            'data'    => new ProductResource($product),
        ]);
    }

    public function nextSku(Request $request): JsonResponse
    {
        $sku = $this->productService->generateNextSku(
            $request->integer('category_id') ?: null,
            $request->integer('brand_id') ?: null
        );

        return response()->json(['code' => $sku]);
    }
}