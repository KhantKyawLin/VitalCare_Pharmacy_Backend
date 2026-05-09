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
        $todaySales = Order::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Yesterday's sales
        $yesterdaySales = Order::whereDate('created_at', $yesterday)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Sales change percentage
        $salesChange = $yesterdaySales > 0
            ? round(($todaySales - $yesterdaySales) / $yesterdaySales * 100, 2)
            : ($todaySales > 0 ? 100 : 0);

        // New orders today
        $newOrders = Order::whereDate('created_at', $today)->count();

        // Low stock count (Optimized: single query with aggregation)
        $lowStock = Product::withSum('movements as total_stock', 'instock_quantity')
            ->get()
            ->filter(function($p) {
                return $p->total_stock <= $p->minimum_quantity;
            })->count();

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
            ->latest('created_at')
            ->take(5)
            ->get();

        // Health Tips stats
        $healthTipsCount = \App\Models\HealthTip::count();
        $publishedTips = \App\Models\HealthTip::where('is_published', true)->count();

        // Product stats
        $totalProducts = Product::count();

        // Recent activity logs (Audit List)
        $recentActivity = ActivityLog::with('user')
            ->latest('created_at')
            ->take(5)
            ->get();
            
        // Sales trend (last 7 days)
        $salesTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $amount = Order::whereDate('created_at', $date)
                ->where('status', 'completed')
                ->sum('total_amount');
            $salesTrend[] = [
                'day' => now()->subDays($i)->format('D'),
                'amount' => (float)$amount
            ];
        }

        // Category distribution
        $categoryDistribution = \App\Models\Category::withCount(['products as sales_count' => function($query) {
            $query->whereHas('orderItems.order', function($q) {
                $q->where('status', 'completed');
            });
        }])->get()->map(function($cat) {
            return [
                'name' => $cat->name,
                'value' => $cat->sales_count
            ];
        });

        return response()->json([
            'today_sales' => $todaySales,
            'yesterday_sales' => $yesterdaySales,
            'sales_change' => $salesChange,
            'new_orders' => $newOrders,
            'low_stock' => $lowStock,
            'expiring_soon' => $expiringSoon,
            'pending_password_resets' => $pendingResets,
            'recent_orders' => $recentOrders,
            'recent_activity' => $recentActivity,
            'total_products' => $totalProducts,
            'health_tips_count' => $healthTipsCount,
            'published_tips' => $publishedTips,
            'sales_trend' => $salesTrend,
            'category_distribution' => $categoryDistribution,
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
        $allowedKeys = [
            'site_name', 
            'primary_color', 
            'accent_color',
            'about_title',
            'about_description',
            'about_mission_title',
            'about_mission_desc',
            'about_vision_title',
            'about_vision_desc'
        ];

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

        // Handle About Us Hero Image
        if ($request->hasFile('about_hero_image')) {
            $path = $request->file('about_hero_image')->store('about', 'public');
            SiteSetting::set('about_hero_image', $path);
        }

        // Handle About Us Story Image
        if ($request->hasFile('about_story_image')) {
            $path = $request->file('about_story_image')->store('about', 'public');
            SiteSetting::set('about_story_image', $path);
        }

        ActivityLog::log('updated', 'SiteSetting', null, 'Site settings updated');

        return response()->json([
            'message' => 'Settings updated',
            'settings' => SiteSetting::all()->pluck('value', 'key')
        ]);
    }
}
