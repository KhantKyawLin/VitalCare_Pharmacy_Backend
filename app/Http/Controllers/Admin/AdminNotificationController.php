<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminNotificationController extends Controller
{
    /**
     * Get alerts for low stock and expiring items.
     */
    public function getAlerts()
    {
        // 1. Low Stock Alerts
        $lowStock = Product::withSum('movements as total_stock', 'instock_quantity')
            ->where('is_published', true)
            ->get()
            ->filter(function($p) {
                return $p->total_stock <= $p->minimum_quantity;
            })
            ->map(function($p) {
                return [
                    'id' => 'low_' . $p->id,
                    'type' => 'low_stock',
                    'title' => 'Low Stock Alert',
                    'message' => "{$p->name} is running low ({$p->total_stock} left).",
                    'product_id' => $p->id,
                    'severity' => 'warning',
                    'created_at' => now()
                ];
            });

        // 2. Expiring Items Alerts (within next 30 days)
        $expiring = \App\Models\ProductMovement::with('product')
            ->where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [now(), now()->addDays(30)])
            ->get()
            ->map(function($m) {
                $daysLeft = (int)floor(now()->diffInDays($m->expired_date, false));
                return [
                    'id' => 'exp_' . $m->id,
                    'type' => 'expiring',
                    'title' => 'Expiry Warning',
                    'message' => "{$m->product->name} (Batch #{$m->id}) expires in {$daysLeft} days.",
                    'product_id' => $m->product_id,
                    'severity' => 'danger',
                    'created_at' => now()
                ];
            });

        // 3. Unread Contact Messages
        $unreadMessages = \App\Models\ContactMessage::where('status', 'unread')->latest()->get()->map(function($m) {
            return [
                'id' => 'msg_' . $m->id,
                'type' => 'contact_message',
                'title' => 'New Contact Message',
                'message' => "{$m->name} sent a message: {$m->subject}",
                'severity' => 'info',
                'created_at' => $m->created_at
            ];
        });

        // 4. Pending Online Orders
        $pendingOrders = \App\Models\Order::where('order_type', 'online')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function($o) {
                return [
                    'id' => 'ord_' . $o->id,
                    'type' => 'online_order',
                    'title' => 'New Online Order',
                    'message' => "Order #{$o->receipt_number} is pending approval.",
                    'severity' => 'primary',
                    'created_at' => $o->created_at
                ];
            });

        $allAlerts = $lowStock->values()
            ->concat($expiring->values())
            ->concat($unreadMessages->values())
            ->concat($pendingOrders->values())
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'count' => $allAlerts->count(),
            'alerts' => $allAlerts
        ]);
    }
}
