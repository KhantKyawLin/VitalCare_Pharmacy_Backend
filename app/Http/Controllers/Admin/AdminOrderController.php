<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminOrderController extends Controller
{
    #[OA\Get(
        path: '/api/admin/orders',
        summary: 'List customer and POS orders with status filtering (Admin/Staff)',
        tags: ['Admin Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'completed', 'cancelled'])),
            new OA\Parameter(name: 'order_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Online', 'POS'])),
            new OA\Parameter(name: 'prescription_status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-09-20')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated order collection'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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
        if ($request->has('prescription_status')) {
            $query->where('prescription_status', $request->prescription_status);
        }

        return response()->json($query->latest('created_at')->paginate($request->get('per_page', 15)));
    }

    #[OA\Get(
        path: '/api/admin/orders/{id}',
        summary: 'Get order details with items, batches and prescription image',
        tags: ['Admin Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order details'),
            new OA\Response(response: 404, description: 'Order not found')
        ]
    )]
    public function show($id)
    {
        $order = Order::with(['user', 'orderProducts.product.pictures', 'orderProducts.batches.productMovement', 'cashier'])->findOrFail($id);
        return response()->json($order);
    }

    #[OA\Put(
        path: '/api/admin/orders/{id}',
        summary: 'Update order fulfillment, shipping or refund status',
        tags: ['Admin Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'cancelled'], example: 'completed'),
                    new OA\Property(property: 'deliver_status', type: 'string', enum: ['pending', 'shipped', 'delivered', 'returned'], example: 'delivered'),
                    new OA\Property(property: 'payment_status', type: 'string', enum: ['pending', 'paid', 'refunded'], example: 'paid'),
                    new OA\Property(property: 'refund_reason', type: 'string', nullable: true, example: 'Customer requested cancellation')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Order updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Order not found')
        ]
    )]
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

    #[OA\Delete(
        path: '/api/admin/orders/{id}',
        summary: 'Delete order record (Admin only)',
        tags: ['Admin Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order deleted'),
            new OA\Response(response: 404, description: 'Order not found')
        ]
    )]
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        $order->delete();
        ActivityLog::log('deleted', 'Order', $id, "Order #$id deleted");

        return response()->json(['message' => 'Order deleted']);
    }

    #[OA\Patch(
        path: '/api/admin/orders/{id}/prescription',
        summary: 'Approve or reject customer prescription upload',
        tags: ['Admin Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['prescription_status'],
                properties: [
                    new OA\Property(property: 'prescription_status', type: 'string', enum: ['approved', 'rejected'], example: 'approved')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Prescription status updated (if rejected, prescription items cancelled & refunded)'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Order not found')
        ]
    )]
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

            // Real-time WebSocket Alert for Prescription/Order Status Update
            event(new \App\Events\OrderStatusUpdated($order->load('orderProducts.product', 'user')));

            // Send Email Notification for Rejection
            if ($request->prescription_status === 'rejected') {
                try {
                    if ($order->user && $order->user->email) {
                        // Assuming you have a PrescriptionRejected mailable
                        // If not, we can just send a general status update email
                        \Illuminate\Support\Facades\Mail::to($order->user->email)
                            ->send(new \App\Mail\OrderUpdated($order));
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to send prescription rejection email: " . $e->getMessage());
                }
            }

            return response()->json(['message' => "Prescription {$request->prescription_status} successfully.", 'order' => $order]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Failed to update prescription status: ' . $e->getMessage()], 500);
        }
    }
}
