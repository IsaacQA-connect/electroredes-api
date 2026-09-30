<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    // GET /api/brands
    public function index()
    {
        $brands = Brand::where('status', true)->orderBy('name')->get();
        return response()->json(['data' => $brands]);
    }

    // POST /api/brands (Creación rápida desde el modal)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name'
        ]);

        $brand = Brand::create($validated);

        return response()->json(['data' => $brand], 201);
    }
}
