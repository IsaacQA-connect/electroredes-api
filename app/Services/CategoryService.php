<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * Create a new class instance.
     */
    //public function __construct(){ // }
    public function getAll(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function getById(int $id): Category
    {
        return Category::findOrFail($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function changeStatus(
        Category $category,
        bool $status
    ): Category {
        $category->update([
            'status' => $status,
        ]);

        return $category->refresh();
    }
}
