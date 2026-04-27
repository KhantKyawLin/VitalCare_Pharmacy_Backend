<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global product search (authenticated users + admin).
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['error' => 'Search query must be at least 2 characters'], 422);
        }

        $products = Product::with(['category', 'pictures'])
            ->where('name', 'like', "%$query%")
            ->orWhere('description', 'like', "%$query%")
            ->orWhere('dosage', 'like', "%$query%")
            ->limit(20)
            ->get();

        return response()->json($products);
    }
}
