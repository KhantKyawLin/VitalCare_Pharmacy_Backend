<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'orderProducts.product.pictures']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->has('order_type')) {
            $query->where('order_type', $request->order_type);
        }

        return response()->json($query->latest('created_at')->paginate($request->get('per_page', 15)));
    }

    public function show($id)
    {
        $order = Order::with(['user', 'orderProducts.product.pictures', 'cashier'])->findOrFail($id);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = Order::with('orderProducts')->findOrFail($id);
        $old = $order->toArray();

        $request->validate([
            'status' => 'sometimes|in:pending,completed,cancelled',
            'deliver_status' => 'sometimes|in:pending,shipped,delivered,returned',
            'payment_status' => 'sometimes|in:pending,paid,refunded',
            'refund_reason' => 'nullable|string',
        ]);

        $newPaymentStatus = $request->payment_status;
        $isNewRefund = ($newPaymentStatus === 'refunded' && $order->payment_status !== 'refunded');

        \DB::beginTransaction();
        try {
            $updateData = $request->only(['status', 'deliver_status', 'payment_status', 'refund_reason']);
            
            if ($isNewRefund) {
                $updateData['refunded_at'] = now();
                $updateData['refunded_by'] = auth()->id();

                // Reverse inventory for all products in this order
                foreach ($order->orderProducts as $item) {
                    \App\Models\ProductMovement::create([
                        'product_id' => $item->product_id,
                        'movement_type' => 'returned',
                        'instock_quantity' => $item->quantity,
                        'movement_date' => now(),
                        'created_by' => auth()->id(),
                        'sale_price' => $item->price,
                    ]);
                }
            }

            $order->update($updateData);
            
            // Send Order Shipped Email
            if ($request->deliver_status === 'shipped' && $old['deliver_status'] !== 'shipped') {
                try {
                    $order->load('user'); // Ensure user is loaded
                    if ($order->user && $order->user->email) {
                        \Illuminate\Support\Facades\Mail::to($order->user->email)
                            ->send(new \App\Mail\OrderShipped($order));
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send shipping email: " . $e->getMessage());
                }
            }

            ActivityLog::log('updated', 'Order', $id, "Order #$id status updated", $old, $order->toArray());

            \DB::commit();
            return response()->json(['message' => 'Order updated successfully', 'order' => $order]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Failed to update order: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();
        ActivityLog::log('deleted', 'Order', $id, "Order #$id deleted");

        return response()->json(['message' => 'Order deleted']);
    }
}
