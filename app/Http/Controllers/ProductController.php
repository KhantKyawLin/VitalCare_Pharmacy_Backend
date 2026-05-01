<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->where('is_published', true);
        
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type')) {
            if ($request->type === 'top_sellers') {
                // For showcase, order by latest or add specific top seller logic
                $query->latest(); 
            } elseif ($request->type === 'promotions') {
                $query->whereHas('promotions');
            }
        }

        $products = $query->paginate(12);
        return response()->json($products);
    }

    public function topSellers()
    {
        // For showcase, we order by ID or order_products count if available
        // Assuming orderProducts relationship exists in Product model
        $products = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->where('is_published', true)
            ->latest()
            ->take(10)
            ->get();
        return response()->json($products);
    }

    public function specialOffers()
    {
        // Products that have at least one promotion
        $products = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->where('is_published', true)
            ->whereHas('promotions')
            ->take(10)
            ->get();
        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->where('is_published', true)
            ->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Fetch related products from the same category
        $related = Product::with(['category', 'pictures', 'unit', 'promotions'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->take(5)
            ->get();

        return response()->json([
            'product' => $product,
            'related' => $related
        ]);
    }

    public function categories()
    {
        $categories = Category::all();
        return response()->json($categories);
    }
}
