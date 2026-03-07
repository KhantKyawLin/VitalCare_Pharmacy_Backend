<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;

class WishlistController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $wishlists = Wishlist::where('user_id', $user->id)
            ->with('product')
            ->get();

        return response()->json($wishlists);
    }

    public function add(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Product already in wishlist'], 400);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
        ]);

        return response()->json(['message' => 'Added to wishlist', 'wishlist' => $wishlist]);
    }

    public function remove($id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $id)
            ->first();

        if (!$wishlist) {
            return response()->json(['message' => 'Item not found in wishlist'], 404);
        }

        $wishlist->delete();

        return response()->json(['message' => 'Removed from wishlist']);
    }
}
