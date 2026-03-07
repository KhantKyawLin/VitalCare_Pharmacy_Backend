<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'pictures', 'unit'])->get();
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
