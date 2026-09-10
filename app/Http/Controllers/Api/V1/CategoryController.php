<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryService $categoryService
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $categories = $this->categoryService->getAll();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function store(
        StoreCategoryRequest $request
    ): JsonResponse {
        $category = $this->categoryService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $category = $this->categoryService->update(
            $category,
            $request->validated()
        );

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data' => new CategoryResource($category),
        ]);
    }

    public function changeStatus(
        Category $category
    ): JsonResponse {
        $category = $this->categoryService->changeStatus(
            $category,
            ! $category->status
        );

        return response()->json([
            'message' => 'Estado de la categoría actualizado.',
            'data' => new CategoryResource($category),
        ]);
    }
}
