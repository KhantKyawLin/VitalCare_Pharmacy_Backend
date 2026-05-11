<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'pictures', 'unit', 'promotions', 'latestMovement'])
            ->where('is_published', true)
            ->notExpired();
        
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type')) {
            if ($request->type === 'top_sellers') {
                $query->latest(); 
            } elseif ($request->type === 'promotions') {
                $query->whereHas('promotions');
            }
        }

        $products = $query->paginate(12);
        $this->applyMarkupToCollection($products->getCollection());
        
        return response()->json($products);
    }

    public function topSellers()
    {
        $products = Product::with(['category', 'pictures', 'unit', 'promotions', 'latestMovement'])
            ->where('is_published', true)
            ->notExpired()
            ->latest()
            ->take(10)
            ->get();
        
        $this->applyMarkupToCollection($products);
        return response()->json($products);
    }

    public function specialOffers()
    {
        $products = Product::with(['category', 'pictures', 'unit', 'promotions', 'latestMovement'])
            ->where('is_published', true)
            ->notExpired()
            ->whereHas('promotions')
            ->take(10)
            ->get();
            
        $this->applyMarkupToCollection($products);
        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::with(['category', 'pictures', 'unit', 'promotions', 'latestMovement'])
            ->where('is_published', true)
            ->notExpired()
            ->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $this->applyMarkup($product);

        // Fetch related products
        $related = Product::with(['category', 'pictures', 'unit', 'promotions', 'latestMovement'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->notExpired()
            ->take(5)
            ->get();
        
        $this->applyMarkupToCollection($related);

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

    private function applyMarkupToCollection($products)
    {
        foreach ($products as $product) {
            $product->price = $product->getEffectivePrice();
        }
    }

    private function applyMarkup($product)
    {
        $product->price = $product->getEffectivePrice();
    }
}
