<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\SiteSetting;
use App\Models\ActivityLog;
use App\Models\PasswordResetRequest;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Today's sales
        $todaySales = Order::whereDate('order_date', $today)
            ->where('order_status', 'completed')
            ->sum('total_order_amount');

        // Yesterday's sales
        $yesterdaySales = Order::whereDate('order_date', $yesterday)
            ->where('order_status', 'completed')
            ->sum('total_order_amount');

        // Sales change percentage
        $salesChange = $yesterdaySales > 0
            ? round(($todaySales - $yesterdaySales) / $yesterdaySales * 100, 2)
            : ($todaySales > 0 ? 100 : 0);

        // New orders today
        $newOrders = Order::whereDate('order_date', $today)->count();

        // Low stock count
        $lowStock = Product::whereRaw(
            '(SELECT COALESCE(SUM(instock_quantity), 0) FROM product_movements WHERE product_id = products.id AND movement_type IN ("current", "stored") AND (expired_date IS NULL OR expired_date > CURDATE())) <= minimum_quantity'
        )->count();

        // Expiring soon (30 days)
        $expiringSoon = ProductMovement::where('instock_quantity', '>', 0)
            ->whereIn('movement_type', ['current', 'stored'])
            ->whereBetween('expired_date', [now(), now()->addDays(30)])
            ->distinct('product_id')
            ->count('product_id');

        // Pending password reset requests
        $pendingResets = PasswordResetRequest::where('status', 'pending')->count();

        // Recent orders
        $recentOrders = Order::with('user')
            ->latest('order_date')
            ->take(5)
            ->get();

        return response()->json([
            'today_sales' => $todaySales,
            'yesterday_sales' => $yesterdaySales,
            'sales_change' => $salesChange,
            'new_orders' => $newOrders,
            'low_stock' => $lowStock,
            'expiring_soon' => $expiringSoon,
            'pending_password_resets' => $pendingResets,
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * Get site settings.
     */
    public function getSettings()
    {
        $settings = SiteSetting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * Update site settings (branding).
     */
    public function updateSettings(Request $request)
    {
        $allowedKeys = ['site_name', 'site_logo', 'primary_color', 'accent_color'];

        foreach ($allowedKeys as $key) {
            if ($request->has($key)) {
                SiteSetting::set($key, $request->$key);
            }
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            SiteSetting::set('site_logo', $path);
        }

        ActivityLog::log('updated', 'SiteSetting', null, 'Site settings updated');

        return response()->json([
            'message' => 'Settings updated',
            'settings' => SiteSetting::all()->pluck('value', 'key')
        ]);
    }

    /**
     * Activity logs.
     */
    public function activityLogs(Request $request)
    {
        return response()->json(
            ActivityLog::with('user')
                ->latest('created_at')
                ->paginate($request->get('per_page', 20))
        );
    }
}
