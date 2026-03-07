<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Cart;

class OrderController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $orders = Order::where('user_id', $user->id)
            ->with(['products.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function show($id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['products.product'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    public function checkout(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $totalAmount = 0;
        foreach ($cart->items as $item) {
            $totalAmount += $item->quantity * $item->product->price;
        }

        $order = Order::create([
            'user_id' => $user->id,
            'address_id' => $request->address_id,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'slip_image' => null, // Can be updated later with payment proof upload
        ]);

        foreach ($cart->items as $item) {
            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->price,
            ]);
        }

        // Clear cart after successful checkout
        $cart->items()->delete();

        return response()->json(['message' => 'Order placed successfully', 'order' => $order]);
    }
}
