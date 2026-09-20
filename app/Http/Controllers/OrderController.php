<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    #[OA\Get(
        path: '/api/auth/orders',
        summary: 'List current user orders',
        tags: ['Orders (Customer)'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of past orders with products'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index()
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $orders = Order::where('user_id', $user->id)
            ->with(['orderProducts.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    #[OA\Get(
        path: '/api/auth/orders/{id}',
        summary: 'Get order details by ID',
        tags: ['Orders (Customer)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order details with order products'),
            new OA\Response(response: 404, description: 'Order not found'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function show($id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with(['orderProducts.product'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    #[OA\Post(
        path: '/api/auth/checkout',
        summary: 'Checkout cart and place order',
        tags: ['Orders (Customer)'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['delivery_address', 'contact_phone', 'payment_method'],
                    properties: [
                        new OA\Property(property: 'delivery_address', type: 'string', example: '123 Main St, Yangon'),
                        new OA\Property(property: 'contact_phone', type: 'string', example: '+959123456789'),
                        new OA\Property(property: 'payment_method', type: 'string', enum: ['Cash', 'Online'], example: 'Cash'),
                        new OA\Property(property: 'payment_proof', type: 'string', format: 'binary', description: 'Required if Online payment'),
                        new OA\Property(property: 'prescription_image', type: 'string', format: 'binary', description: 'Required if prescription drugs in cart'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order placed successfully'),
            new OA\Response(response: 400, description: 'Cart empty or missing prescription image'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function checkout(Request $request, \App\Actions\CheckoutAction $checkoutAction)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'delivery_address' => 'required|string',
            'contact_phone' => 'required|string',
            'payment_method' => 'required|string|in:Cash,Online',
            'payment_proof' => 'required_if:payment_method,Online|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $requiresPrescription = $cart->items->contains(function ($item) {
            return $item->product && $item->product->requires_prescription;
        });

        if ($requiresPrescription && !$request->hasFile('prescription_image')) {
            return response()->json(['message' => 'A prescription image is required for one or more items in your cart.'], 400);
        }

        $prescriptionImagePath = null;
        if ($requiresPrescription && $request->hasFile('prescription_image')) {
            $prescriptionImagePath = $request->file('prescription_image')->store('prescriptions', 'public');
        }

        $slipImagePath = null;
        if ($request->payment_method === 'Online' && $request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            $imagePath = $file->path();
            $mime = $file->getMimeType();
            $image = null;
            
            if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                $image = @imagecreatefromjpeg($imagePath);
            } elseif ($mime == 'image/png') {
                $image = @imagecreatefrompng($imagePath);
            }
            
            if ($image) {
                $filename = 'payment_slips/' . uniqid() . '_' . time() . '.jpg';
                $fullPath = storage_path('app/public/' . $filename);
                
                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }
                
                // Compress to 70% quality
                imagejpeg($image, $fullPath, 70);
                imagedestroy($image);
                $slipImagePath = $filename;
            } else {
                $slipImagePath = $file->store('payment_slips', 'public');
            }
        }

        try {
            $order = $checkoutAction->execute(
                $user,
                $request->only(['delivery_address', 'contact_phone', 'payment_method']),
                $prescriptionImagePath,
                $slipImagePath
            );

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order->load('orderProducts.product')
            ]);
        } catch (\Exception $e) {
            $stockErrors = isset($e->stock_errors) ? $e->stock_errors : null;
            if ($stockErrors) {
                return response()->json([
                    'message' => 'Inventory/Promotion validation failed:',
                    'stock_errors' => $stockErrors
                ], 422);
            }

            return response()->json([
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/orders/{id}/reorder',
        summary: 'Re-add all non-gift items from a previous order into the current cart',
        tags: ['Orders (Customer)'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Items successfully re-added to cart'),
            new OA\Response(response: 404, description: 'Order not found'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function reorder($id)
    {
        $user = auth('api')->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $order = Order::where('user_id', $user->id)
            ->where('id', $id)
            ->with('orderProducts')
            ->first();
        
        if (!$order) return response()->json(['message' => 'Order not found'], 404);
        
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        
        $addedCount = 0;
        foreach($order->orderProducts as $op) {
            // Only reorder actual products, not gifts from previous promotions
            if (!$op->is_gift) {
                $cartItem = \App\Models\CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $op->product_id)
                    ->first();
                
                if ($cartItem) {
                    $cartItem->increment('quantity', $op->quantity);
                } else {
                    \App\Models\CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $op->product_id,
                        'quantity' => $op->quantity
                    ]);
                }
                $addedCount++;
            }
        }
        
        return response()->json([
            'message' => "Successfully re-added {$addedCount} items to your cart.",
            'cart_count' => \App\Models\CartItem::where('cart_id', $cart->id)->sum('quantity')
        ]);
    }
}
