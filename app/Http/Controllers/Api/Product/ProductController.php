<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        $query = Product::query();

        // 🔍 Recherche par nom ou description
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // 🗂️ Filtre par catégorie
        // 🗂️ Filtre par catégorie (insensible à la casse et accents)
        if ($request->has('category') && $request->category != '') {
            $category = $request->category;
            $query->whereRaw("LOWER(category) = ?", [strtolower($category)]);
        }

        // 💰 Filtre par prix min
        if ($request->has('price_min') && $request->price_min != '') {
            $query->where('price', '>=', $request->price_min);
        }

        // 💰 Filtre par prix max
        if ($request->has('price_max') && $request->price_max != '') {
            $query->where('price', '<=', $request->price_max);
        }

        // 🔃 Tri
        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // 📄 Pagination (obligatoire !)
        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json($products, 200);
    }
    

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);
        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);
        return response()->json($product, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $product->delete();
        return response()->json(['message' => 'Product deleted'], 200);
    }
}
