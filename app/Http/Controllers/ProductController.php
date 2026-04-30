<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'pictures', 'unit', 'promotions']);
        
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->paginate(12);
        return response()->json($products);
    }

    public function topSellers()
    {
        // For showcase, we order by ID or order_products count if available
        // Assuming orderProducts relationship exists in Product model
        $products = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->latest()
            ->take(8)
            ->get();
        return response()->json($products);
    }

    public function specialOffers()
    {
        // Products that have at least one promotion
        $products = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->whereHas('promotions')
            ->take(8)
            ->get();
        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'pictures', 'unit'])->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product);
    }

    public function categories()
    {
        $categories = Category::all();
        return response()->json($categories);
    }
}
