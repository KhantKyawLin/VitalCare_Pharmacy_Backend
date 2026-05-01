<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\InventoryAdjustment;
use App\Models\ExternalTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminReportController extends Controller
{
    /**
     * Get financial summary stats for a given period.
     */
    public function index(Request $request)
    {
        $range = $request->get('range', 'this_month');
        $dateRange = $this->getDateRange($range, $request);

        // 1. Revenue & COGS
        $salesData = OrderProduct::whereHas('order', function($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->where('status', 'completed');
            })
            ->selectRaw('SUM(price * quantity) as gross_revenue, SUM(purchase_price * quantity) as cogs')
            ->first();

        $discounts = Order::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'completed')
            ->sum('discount_amount');

        $revenue = (float)$salesData->gross_revenue - (float)$discounts;
        $cogs = (float)$salesData->cogs;
        $grossProfit = $revenue - $cogs;

        // 2. Adjustments (Losses)
        $losses = InventoryAdjustment::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('reason', ['expired', 'damaged', 'lost'])
            ->sum('financial_value');

        $netProfit = $grossProfit - abs($losses);

        // 3. External Transactions
        $externalExpenses = ExternalTransaction::where('type', 'expense')
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        $externalIncome = ExternalTransaction::where('type', 'income')
            ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        $netProfit = $netProfit - $externalExpenses + $externalIncome;

        // 4. Trends (Previous Period)
        $prevDateRange = $this->getPreviousDateRange($range, $dateRange);
        $prevSalesData = OrderProduct::whereHas('order', function($q) use ($prevDateRange) {
                $q->whereBetween('created_at', [$prevDateRange['start'], $prevDateRange['end']])
                  ->where('status', 'completed');
            })
            ->selectRaw('SUM(price * quantity) as gross_revenue')
            ->first();
            
        $prevDiscounts = Order::whereBetween('created_at', [$prevDateRange['start'], $prevDateRange['end']])
            ->where('status', 'completed')
            ->sum('discount_amount');
        
        $prevRevenue = (float)$prevSalesData->gross_revenue - (float)$prevDiscounts;
        $revenueTrend = $prevRevenue > 0 ? (($revenue - $prevRevenue) / $prevRevenue) * 100 : 0;

        return response()->json([
            'summary' => [
                'total_revenue' => $revenue,
                'total_cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'total_losses' => (float)abs($losses),
                'external_expenses' => (float)$externalExpenses,
                'external_income' => (float)$externalIncome,
                'net_profit' => $netProfit,
                'revenue_trend' => round($revenueTrend, 1),
                'margin' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0
            ],
            'period' => [
                'start' => $dateRange['start']->toDateString(),
                'end' => $dateRange['end']->toDateString(),
                'label' => ucwords(str_replace('_', ' ', $range))
            ]
        ]);
    }

    /**
     * Get chart data for sales vs profit.
     */
    public function chartData(Request $request)
    {
        $range = $request->get('range', 'this_month');
        $dateRange = $this->getDateRange($range, $request);

        $results = OrderProduct::whereHas('order', function($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->where('status', 'completed');
            })
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->selectRaw('DATE(orders.created_at) as date')
            ->selectRaw('SUM(order_products.price * order_products.quantity) as revenue')
            ->selectRaw('SUM((order_products.price - order_products.purchase_price) * order_products.quantity) as profit')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($results);
    }

    /**
     * Get breakdown of inventory losses.
     */
    public function lossBreakdown(Request $request)
    {
        $range = $request->get('range', 'this_month');
        $dateRange = $this->getDateRange($range, $request);

        $losses = InventoryAdjustment::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('reason', ['expired', 'damaged', 'lost'])
            ->select('reason', DB::raw('SUM(ABS(financial_value)) as value'))
            ->groupBy('reason')
            ->get();

        return response()->json($losses);
    }

    /**
     * Get top profitable products.
     */
    public function topProfitableProducts(Request $request)
    {
        $range = $request->get('range', 'this_month');
        $dateRange = $this->getDateRange($range, $request);

        $products = OrderProduct::with('product')
            ->whereHas('order', function($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->where('status', 'completed');
            })
            ->select('product_id')
            ->selectRaw('SUM(quantity) as units_sold')
            ->selectRaw('SUM(price * quantity) as revenue')
            ->selectRaw('SUM((price - purchase_price) * quantity) as gross_profit')
            ->groupBy('product_id')
            ->orderBy('gross_profit', 'desc')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    /**
     * Get detailed P&L records (Unified list).
     */
    public function detailedProfitRecords(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->get('end_date', now()->endOfDay()->toDateString()))->endOfDay();
        $type = $request->get('type', 'all'); // 'sales', 'losses', 'external'

        $records = collect();

        // 1. Sales Records (Aggregated by Order/Product)
        if ($type === 'all' || $type === 'sales') {
            $sales = OrderProduct::with(['order', 'product'])
                ->whereHas('order', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
                })
                ->get()
                ->map(function($op) {
                    return [
                        'date' => $op->order->created_at->toDateTimeString(),
                        'type' => 'Sale',
                        'category' => $op->product->category->name ?? 'General',
                        'title' => $op->product->name . " (x" . $op->quantity . ")",
                        'revenue' => (float)($op->price * $op->quantity),
                        'cost' => (float)($op->purchase_price * $op->quantity),
                        'profit_impact' => (float)(($op->price - $op->purchase_price) * $op->quantity),
                        'reference' => $op->order->receipt_number
                    ];
                });
            $records = $records->concat($sales);
            
            // Fetch Order-Level Discounts
            $discounts = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->where('discount_amount', '>', 0)
                ->get()
                ->map(function($order) {
                    return [
                        'date' => $order->created_at->toDateTimeString(),
                        'type' => 'Sale Discount',
                        'category' => 'Promotion',
                        'title' => 'Order Discount Applied',
                        'revenue' => 0,
                        'cost' => (float)$order->discount_amount,
                        'profit_impact' => -(float)$order->discount_amount,
                        'reference' => $order->receipt_number
                    ];
                });
            $records = $records->concat($discounts);
        }

        // 2. Inventory Losses
        if ($type === 'all' || $type === 'losses') {
            $losses = InventoryAdjustment::with('product')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('reason', ['expired', 'damaged', 'lost'])
                ->get()
                ->map(function($ia) {
                    $impact = -abs($ia->financial_value);
                    return [
                        'date' => $ia->created_at->toDateTimeString(),
                        'type' => 'Inventory Loss',
                        'category' => $ia->reason,
                        'title' => ($ia->product->name ?? 'Unknown') . " Adjustment",
                        'revenue' => 0,
                        'cost' => abs($ia->financial_value),
                        'profit_impact' => $impact,
                        'reference' => 'ADJ-' . $ia->id
                    ];
                });
            $records = $records->concat($losses);
        }

        // 3. External Transactions
        if ($type === 'all' || $type === 'external') {
            $external = ExternalTransaction::whereBetween('transaction_date', [$startDate, $endDate])
                ->get()
                ->map(function($et) {
                    $impact = $et->type === 'income' ? (float)$et->amount : -(float)$et->amount;
                    return [
                        'date' => $et->transaction_date->toDateTimeString(),
                        'type' => 'External ' . ucfirst($et->type),
                        'category' => $et->category,
                        'title' => $et->title,
                        'revenue' => $et->type === 'income' ? (float)$et->amount : 0,
                        'cost' => $et->type === 'expense' ? (float)$et->amount : 0,
                        'profit_impact' => $impact,
                        'reference' => $et->reference_number ?? 'EXT-' . $et->id
                    ];
                });
            $records = $records->concat($external);
        }

        // Sort by date desc
        $sorted = $records->sortByDesc('date')->values();

        // Manual Pagination
        $perPage = (int)$request->get('per_page', 20);
        $page = (int)$request->get('page', 1);
        $pagedData = $sorted->forPage($page, $perPage);

        return response()->json([
            'data' => $pagedData->values(),
            'total' => $records->count(),
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($records->count() / $perPage),
            'summary' => [
                'total_profit_impact' => $records->sum('profit_impact')
            ]
        ]);
    }

    /**
     * Private helper to get carbon date objects.
     */
    private function getDateRange($range, $request)
    {
        $start = now()->startOfMonth();
        $end = now()->endOfDay();

        switch ($range) {
            case 'today':
                $start = now()->startOfDay();
                break;
            case 'last_7_days':
                $start = now()->subDays(6)->startOfDay();
                break;
            case 'this_month':
                $start = now()->startOfMonth();
                break;
            case 'last_month':
                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->endOfMonth();
                break;
            case 'year_to_date':
                $start = now()->startOfYear();
                break;
            case 'custom':
                if ($request->has('start_date') && $request->has('end_date')) {
                    $start = Carbon::parse($request->start_date)->startOfDay();
                    $end = Carbon::parse($request->end_date)->endOfDay();
                }
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function getPreviousDateRange($range, $currentRange)
    {
        $diffInDays = $currentRange['start']->diffInDays($currentRange['end']) + 1;
        $start = $currentRange['start']->copy()->subDays($diffInDays);
        $end = $currentRange['start']->copy()->subSecond();

        return ['start' => $start, 'end' => $end];
    }
}
