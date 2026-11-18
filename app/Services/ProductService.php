<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->with('category', 'brand');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('brand', function ($b) use ($search) {
                        $b->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['low_stock']) && $filters['low_stock'] === true) {
            $query->whereColumn('stock', '<=', 'minimum_stock');
        }

        return $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function getById(int $id): Product
    {
        return Product::with(['category', 'brand'])->findOrFail($id);
    }

    public function generateNextSku(?int $categoryId = null, ?int $brandId = null): string
    {
        $catPrefix = 'PRD';
        if ($categoryId && ($category = Category::find($categoryId))) {
            $cleanCat = preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($category->name)));
            $catPrefix = substr($cleanCat ?: 'PRD', 0, 3);
        }

        $brandPrefix = '';
        if ($brandId && ($brand = Brand::find($brandId))) {
            $cleanBrand = preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($brand->name)));
            if ($cleanBrand) {
                $brandPrefix = '-' . substr($cleanBrand, 0, 3);
            }
        }

        $basePrefix = "{$catPrefix}{$brandPrefix}-";

        $lastCode = Product::where('code', 'like', "{$basePrefix}%")
            ->orderByDesc('id')
            ->value('code');

        $nextNumber = 1;
        if ($lastCode && preg_match('/(\d+)$/', $lastCode, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = $basePrefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Product::where('code', $candidate)->exists());

        return $candidate;
    }

    public function create(array $data): Product
    {
        $imageFile = $data['image'] ?? null;
        $imageUrl  = $data['image_url'] ?? null;
        unset($data['image'], $data['image_url']);

        // Generar SKU automáticamente si no fue enviado
        if (empty($data['code'])) {
            $data['code'] = $this->generateNextSku(
                $data['category_id'] ?? null,
                $data['brand_id'] ?? null
            );
        }

        $data['stock'] = $data['stock'] ?? 0;

        $product = Product::create($data);

        // 1. Subir imagen local o pegada con Ctrl + V a S3
        if ($imageFile instanceof UploadedFile) {
            $this->uploadImage($product, $imageFile);
        }
        // 2. O descargar imagen sugerida por el escáner y guardarla en S3
        elseif (!empty($imageUrl)) {
            $this->uploadImageFromUrl($product, $imageUrl);
        }

        return $product->refresh()->load(['category', 'brand']);
    }

    public function update(Product $product, array $data): Product
    {
        unset($data['image'], $data['image_url']);
        $product->update($data);

        return $product->refresh()->load(['category', 'brand']);
    }

    public function changeStatus(Product $product, bool $status): Product
    {
        $product->update(['status' => $status]);

        return $product->refresh()->load(['category', 'brand']);
    }

    public function uploadImage(Product $product, UploadedFile $file): Product
    {
        try {
            // Subir archivo a S3
            $path = $file->store('products', 's3');

            if (!$path) {
                throw new \Exception('No se pudo obtener la ruta del archivo subido.');
            }

            // Obtener la URL pública de S3
            $url = Storage::disk('s3')->url($path);

            // Guardar en la columna image_path de la base de datos
            $product->update([
                'image_path' => $url
            ]);

            return $product->refresh()->load(['category', 'brand']);

        } catch (\Throwable $e) {
            $awsError = $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();

            Log::error('Error nativo de AWS S3:', ['detail' => $awsError]);

            throw new \Exception('Error S3: ' . $awsError);
        }
    }

    private function uploadImageFromUrl(Product $product, string $url): void
    {
        try {
            $response = Http::timeout(5)->get($url);

            if (!$response->successful()) {
                return;
            }

            $contentType = $response->header('Content-Type', '');
            if (!str_starts_with($contentType, 'image/')) {
                return;
            }

            $ext = match (true) {
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'webp') => 'webp',
                default                            => 'jpg',
            };

            $path = 'products/' . Str::uuid() . '.' . $ext;
            Storage::disk('s3')->put($path, $response->body());

            $product->update([
                'image_path' => Storage::disk('s3')->url($path),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo migrar imagen externa a S3:', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }
}