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

            // Real-time WebSocket Alert for Order Status Update
            event(new \App\Events\OrderStatusUpdated($order));

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

    public function updatePrescriptionStatus(Request $request, $id)
    {
        $order = Order::with('orderProducts.product')->findOrFail($id);
        $old = $order->toArray();

        $request->validate([
            'prescription_status' => 'required|in:approved,rejected',
        ]);

        \DB::beginTransaction();
        try {
            $order->prescription_status = $request->prescription_status;
            
            if ($request->prescription_status === 'rejected') {
                $prescriptionItems = $order->orderProducts->filter(function ($item) {
                    return $item->product && $item->product->requires_prescription;
                });

                if ($prescriptionItems->count() === $order->orderProducts->count()) {
                    // Full cancellation: all items were prescription-based
                    $order->status = 'cancelled';
                    
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
                } else {
                    // Partial cancellation: keep non-prescription items
                    $deductedAmount = 0;
                    
                    foreach ($prescriptionItems as $item) {
                        // Return inventory for rejected items
                        \App\Models\ProductMovement::create([
                            'product_id' => $item->product_id,
                            'movement_type' => 'returned',
                            'instock_quantity' => $item->quantity,
                            'movement_date' => now(),
                            'created_by' => auth()->id(),
                            'sale_price' => $item->price,
                        ]);
                        
                        $deductedAmount += ($item->price * $item->quantity);
                        $item->delete(); // Remove item from order
                    }
                    
                    // Adjust total amount
                    $order->total_amount = max(0, $order->total_amount - $deductedAmount);
                    
                    // Note that a partial refund might be needed if they paid online
                    if ($order->payment_method === 'Online') {
                        $existingReason = $order->refund_reason ? $order->refund_reason . " | " : "";
                        $order->refund_reason = $existingReason . "Prescription rejected. Partial refund of {$deductedAmount} required.";
                    }
                }
            }

            $order->save();
            ActivityLog::log('updated', 'Order', $id, "Prescription status updated to {$request->prescription_status}", $old, $order->toArray());

            \DB::commit();
            return response()->json(['message' => "Prescription {$request->prescription_status} successfully.", 'order' => $order]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Failed to update prescription status: ' . $e->getMessage()], 500);
        }
    }
}
