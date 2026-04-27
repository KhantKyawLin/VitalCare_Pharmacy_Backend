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
            $query->where('order_status', $request->status);
        }
        if ($request->has('date')) {
            $query->whereDate('order_date', $request->date);
        }

        return response()->json($query->latest('order_date')->paginate($request->get('per_page', 15)));
    }

    public function show($id)
    {
        $order = Order::with(['user', 'orderProducts.product.pictures'])->findOrFail($id);
        return response()->json($order);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $old = $order->toArray();

        $request->validate([
            'order_status' => 'sometimes|in:pending,completed,cancelled',
            'deliver_status' => 'sometimes|in:pending,shipped,delivered,returned',
            'payment_status' => 'sometimes|in:pending,paid,refunded',
        ]);

        $order->update($request->only(['order_status', 'deliver_status', 'payment_status']));

        ActivityLog::log('updated', 'Order', $id, "Order #$id status updated", $old, $order->toArray());

        return response()->json(['message' => 'Order updated', 'order' => $order]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();
        ActivityLog::log('deleted', 'Order', $id, "Order #$id deleted");

        return response()->json(['message' => 'Order deleted']);
    }
}
