<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        summary: 'Browse catalog products',
        description: 'Returns published non-expired products with pagination, category filter, and search.',
        tags: ['Products (Catalog)'],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', description: 'Filter by Category ID', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Search by Product Name', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of products',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'total', type: 'integer', example: 62),
                    ]
                )
            )
        ]
    )]
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

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Get product details by ID',
        tags: ['Products (Catalog)'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'Product ID', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details and related recommendations'),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
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
