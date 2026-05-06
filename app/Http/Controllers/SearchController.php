<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\HealthTip;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Unified Global Search (Products & Health Tips).
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([], 200);
        }

        // Search Products
        $products = Product::with(['category', 'pictures', 'unit'])
            ->where('is_published', true)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                  ->orWhere('description', 'like', "%$query%");
            })
            ->limit(10)
            ->get()
            ->map(function($p) {
                $p->type = 'product';
                $p->image = $p->primary_image_url;
                return $p;
            });

        // Search Health Tips
        $tips = HealthTip::where('title', 'like', "%$query%")
            ->orWhere('content', 'like', "%$query%")
            ->where('is_published', true)
            ->limit(10)
            ->get()
            ->map(function($t) {
                $t->type = 'healthtip';
                $t->name = $t->title;
                $t->image = $t->image_path ? "http://127.0.0.1:8000/storage/{$t->image_path}" : null;
                return $t;
            });

        // Merge and return
        return response()->json($products->merge($tips));
    }
}
