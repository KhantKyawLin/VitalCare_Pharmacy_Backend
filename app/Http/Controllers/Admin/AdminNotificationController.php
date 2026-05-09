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
        $lowStock = Product::where('is_published', true)
            ->whereRaw('stock <= min_quantity')
            ->get(['id', 'name', 'stock', 'min_quantity'])
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'type' => 'low_stock',
                    'title' => 'Low Stock Alert',
                    'message' => "{$p->name} is running low ({$p->stock} left).",
                    'product_id' => $p->id,
                    'severity' => 'warning',
                    'created_at' => now()
                ];
            });

        // 2. Expiring Items Alerts (within next 30 days)
        $expiring = Product::where('is_published', true)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->get(['id', 'name', 'expiry_date'])
            ->map(function($p) {
                $daysLeft = now()->diffInDays(Carbon::parse($p->expiry_date), false);
                return [
                    'id' => $p->id,
                    'type' => 'expiring',
                    'title' => 'Expiry Warning',
                    'message' => "{$p->name} expires in {$daysLeft} days.",
                    'product_id' => $p->id,
                    'severity' => 'danger',
                    'created_at' => now()
                ];
            });

        return response()->json([
            'count' => $lowStock->count() + $expiring->count(),
            'alerts' => $lowStock->concat($expiring)
        ]);
    }
}
