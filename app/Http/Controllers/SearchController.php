<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\HealthTip;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SearchController extends Controller
{
    #[OA\Get(
        path: '/api/search',
        summary: 'Global autocomplete search across medicines, categories, and health tips',
        tags: ['Public Utilities'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'paracetamol'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Matching products and health tips')
        ]
    )]
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([], 200);
        }

        // Search Products (Name, Description, Category)
        $products = Product::with(['category', 'pictures', 'unit'])
            ->where('is_published', true)
            ->notExpired()
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                  ->orWhere('description', 'like', "%$query%")
                  ->orWhereHas('category', function($cq) use ($query) {
                      $cq->where('name', 'like', "%$query%");
                  });
            })
            ->limit(10)
            ->get()
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'type' => 'product',
                    'name' => $p->name,
                    'description' => substr(strip_tags($p->description), 0, 60) . '...',
                    'image' => $p->primary_image_url,
                    'category' => $p->category ? $p->category->name : null,
                    'url' => "/products/{$p->id}"
                ];
            });

        // Search Health Tips (Title, Content)
        $tips = HealthTip::where('is_published', true)
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%$query%")
                  ->orWhere('content', 'like', "%$query%");
            })
            ->limit(10)
            ->get()
            ->map(function($t) {
                return [
                    'id' => $t->id,
                    'type' => 'healthtip',
                    'name' => $t->title,
                    'description' => substr(strip_tags($t->content), 0, 60) . '...',
                    'image' => $t->image_path ? "http://127.0.0.1:8000/storage/{$t->image_path}" : null,
                    'category' => 'Health & Wellness',
                    'url' => "/health-tips/{$t->id}"
                ];
            });

        // Merge and return
        return response()->json($products->merge($tips));
    }
}
