<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;
use OpenApi\Attributes as OA;

class WishlistController extends Controller
{
    #[OA\Get(
        path: '/api/auth/wishlist',
        summary: 'Get customer saved wishlist',
        tags: ['Wishlist'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of wishlist items with product pictures'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user('api');

        $wishlists = Wishlist::where('user_id', $user->id)
            ->with('product.pictures')
            ->get();

        return response()->json($wishlists);
    }

    #[OA\Post(
        path: '/api/auth/wishlist/add',
        summary: 'Add product to wishlist',
        tags: ['Wishlist'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Added to wishlist'),
            new OA\Response(response: 400, description: 'Product already in wishlist'),
            new OA\Response(response: 422, description: 'Product expired or unavailable'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function add(Request $request)
    {
        $user = $request->user('api');

        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        // Prevent adding expired products to wishlist
        $product = Product::notExpired()->find($request->product_id);
        if (!$product) {
            return response()->json(['message' => 'This product is currently unavailable or expired.'], 422);
        }

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

    #[OA\Delete(
        path: '/api/auth/wishlist/remove/{product_id}',
        summary: 'Remove product from wishlist',
        tags: ['Wishlist'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Removed from wishlist'),
            new OA\Response(response: 404, description: 'Item not found in wishlist'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function remove(Request $request, $id)
    {
        $user = $request->user('api');

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
