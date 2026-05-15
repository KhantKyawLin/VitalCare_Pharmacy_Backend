<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;

class CartController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $cart = Cart::with(['items.product.pictures'])->firstOrCreate(['user_id' => $user->id]);

        return response()->json($cart);
    }

    public function add(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // Security check: Don't allow adding expired products to cart
        $product = \App\Models\Product::notExpired()->find($request->product_id);
        if (!$product) {
            return response()->json(['message' => 'This product is currently unavailable or expired.'], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            $newQty = $cartItem->quantity + $request->quantity;
            if ($newQty > 5) {
                return response()->json(['message' => 'Maximum quantity per product is 5.'], 422);
            }
            $cartItem->quantity = $newQty;
            $cartItem->save();
        } else {
            if ($request->quantity > 5) {
                return response()->json(['message' => 'Maximum quantity per product is 5.'], 422);
            }
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json(['message' => 'Added to cart', 'cart_item' => $cartItem]);
    }

    public function remove($id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $cart = Cart::where('user_id', $user->id)->first();
        
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $id)
            ->first();

        if (!$cartItem) {
            return response()->json(['message' => 'Item not found in cart'], 404);
        }

        $cartItem->delete();

        return response()->json(['message' => 'Removed from cart']);
    }

    public function updateQuantity(Request $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1|max:5',
        ]);

        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $id)
            ->first();

        if (!$cartItem) {
            return response()->json(['message' => 'Item not found in cart'], 404);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json(['message' => 'Cart item updated', 'cart_item' => $cartItem]);
    }
}
