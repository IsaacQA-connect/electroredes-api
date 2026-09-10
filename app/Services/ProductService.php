<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Create a new class instance.
     */
    //public function __construct(){// }
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('category');

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where(
                'category_id',
                $filters['category_id']
            );
        }

        if (
            isset($filters['low_stock'])
            && $filters['low_stock'] === true
        ) {
            $query->whereColumn(
                'stock',
                '<=',
                'minimum_stock'
            );
        }

        return $query
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    public function getById(int $id): Product
    {
        return Product::with('category')
            ->findOrFail($id);
    }

    public function create(array $data): Product
    {
        return Product::create($data)
            ->load('category');
    }

    public function update(
        Product $product,
        array $data
    ): Product {
        $product->update($data);

        return $product->refresh()
            ->load('category');
    }

    public function changeStatus(
        Product $product,
        bool $status
    ): Product {
        $product->update([
            'status' => $status,
        ]);

        return $product->refresh()
            ->load('category');
    }
}
