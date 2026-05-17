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

        // 2. Refunds
        $refunds = Order::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('status', 'refunded')
            ->sum('total_amount');

        $netRevenue = $revenue - $refunds;
        $grossProfit = $netRevenue - $cogs;

        // 3. Adjustments (Losses vs Recoverable)
        $totalLosses = InventoryAdjustment::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereIn('reason', ['expired', 'damaged', 'lost'])
            ->sum('financial_value');
            
        $recoverableValue = InventoryAdjustment::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('reason', 'returned_to_supplier')
            ->sum('financial_value');

        $netProfit = $grossProfit - abs($totalLosses); // Supplier returns aren't immediate losses if credit is expected

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
                'total_revenue' => $netRevenue,
                'gross_revenue' => $revenue,
                'total_refunds' => (float)$refunds,
                'total_cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'total_losses' => (float)abs($totalLosses),
                'recoverable_returns' => (float)abs($recoverableValue),
                'external_expenses' => (float)$externalExpenses,
                'external_income' => (float)$externalIncome,
                'net_profit' => $netProfit,
                'revenue_trend' => round($revenueTrend, 1),
                'margin' => $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : 0
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
            ->whereIn('reason', ['expired', 'damaged', 'lost', 'returned_to_supplier', 'counting_error'])
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
    /**
     * Get detailed P&L records (Unified list) with high-performance DB Unions and deferred hydration.
     */
    public function detailedProfitRecords(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->get('end_date', now()->endOfDay()->toDateString()))->endOfDay();
        $type = $request->get('type', 'all'); // 'sales', 'losses', 'external'

        // 1. Build light queries mapping primary key + date only to union
        $ordersQuery = DB::table('orders')
            ->select('id as source_id', 'created_at as date', DB::raw("'order' as source_type"))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'refunded']);

        $lossesQuery = DB::table('inventory_adjustments')
            ->select('id as source_id', 'created_at as date', DB::raw("'loss' as source_type"))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('reason', ['expired', 'damaged', 'lost', 'returned_to_supplier', 'counting_error']);

        $externalQuery = DB::table('external_transactions')
            ->select('id as source_id', 'transaction_date as date', DB::raw("'external' as source_type"))
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        // 2. Select Union based on requested type filter
        $query = null;
        if ($type === 'all') {
            $query = $ordersQuery->unionAll($lossesQuery)->unionAll($externalQuery);
        } elseif ($type === 'sales') {
            $query = $ordersQuery;
        } elseif ($type === 'losses') {
            $query = $lossesQuery;
        } elseif ($type === 'external') {
            $query = $externalQuery;
        }

        // 3. Paginate inside Database using Lightweight UNION subquery
        $paginatedLight = DB::table(DB::raw("({$query->toSql()}) as combined"))
            ->mergeBindings($query)
            ->orderBy('date', 'desc');

        $perPage = (int)$request->get('per_page', 20);
        $page = (int)$request->get('page', 1);

        $totalCount = $paginatedLight->count();
        $lightItems = $paginatedLight->offset(($page - 1) * $perPage)->limit($perPage)->get();

        // 4. Deferred Hydration: Load fully loaded relations only for this page's items
        $orderIds = $lightItems->where('source_type', 'order')->pluck('source_id')->all();
        $lossIds = $lightItems->where('source_type', 'loss')->pluck('source_id')->all();
        $externalIds = $lightItems->where('source_type', 'external')->pluck('source_id')->all();

        $orders = empty($orderIds) ? collect() : Order::with(['orderProducts.product.category'])->whereIn('id', $orderIds)->get()->keyBy('id');
        $losses = empty($lossIds) ? collect() : InventoryAdjustment::with('product')->whereIn('id', $lossIds)->get()->keyBy('id');
        $externals = empty($externalIds) ? collect() : ExternalTransaction::whereIn('id', $externalIds)->get()->keyBy('id');

        // 5. Build final sorted array output matching original payload structures
        $mappedData = [];
        foreach ($lightItems as $item) {
            if ($item->source_type === 'order') {
                $order = $orders->get($item->source_id);
                if ($order) {
                    $mappedData[] = $this->mapOrderRecord($order);
                }
            } elseif ($item->source_type === 'loss') {
                $loss = $losses->get($item->source_id);
                if ($loss) {
                    $mappedData[] = $this->mapLossRecord($loss);
                }
            } elseif ($item->source_type === 'external') {
                $ext = $externals->get($item->source_id);
                if ($ext) {
                    $mappedData[] = $this->mapExternalRecord($ext);
                }
            }
        }

        // 6. Fast Lightweight Profit Summary calculations (completely avoiding in-memory totals!)
        $totalSalesProfit = 0;
        if ($type === 'all' || $type === 'sales') {
            $salesProfitData = OrderProduct::whereHas('order', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
                })
                ->selectRaw('SUM((price - purchase_price) * quantity) as gross_profit')
                ->first();
                
            $completedDiscounts = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')
                ->sum('discount_amount');

            $refundedOrdersValue = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'refunded')
                ->sum('total_amount');

            $totalSalesProfit = ($salesProfitData->gross_profit ?? 0) - $completedDiscounts - $refundedOrdersValue;
        }

        $totalLossesProfit = 0;
        if ($type === 'all' || $type === 'losses') {
            $totalLossesProfit = -abs(InventoryAdjustment::whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('reason', ['expired', 'damaged', 'lost', 'counting_error'])
                ->sum('financial_value'));
        }

        $totalExternalProfit = 0;
        if ($type === 'all' || $type === 'external') {
            $expenses = ExternalTransaction::where('type', 'expense')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');
            $income = ExternalTransaction::where('type', 'income')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');
            $totalExternalProfit = $income - $expenses;
        }

        $totalProfitImpact = $totalSalesProfit + $totalLossesProfit + $totalExternalProfit;

        return response()->json([
            'data' => $mappedData,
            'total' => $totalCount,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($totalCount / $perPage),
            'summary' => [
                'total_profit_impact' => $totalProfitImpact
            ]
        ]);
    }

    private function mapOrderRecord(Order $order): array
    {
        $isRefunded = $order->status === 'refunded';
        $items = $order->orderProducts->map(function($op) {
            $origPrice = $op->original_price ?? $op->price;
            return [
                'product_name' => $op->product->name ?? 'Unknown',
                'category' => $op->product->category->name ?? 'General',
                'quantity' => $op->quantity,
                'price' => (float)$op->price,
                'original_price' => (float)$origPrice,
                'purchase_price' => (float)$op->purchase_price,
                'subtotal' => (float)($op->price * $op->quantity),
                'original_subtotal' => (float)($origPrice * $op->quantity),
                'total_cost' => (float)($op->purchase_price * $op->quantity),
            ];
        });

        $potentialRevenue = $items->sum('original_subtotal');
        $netRevenue = (float)$order->total_amount;
        $totalDiscount = $potentialRevenue - $netRevenue;
        $totalCost = $items->sum('total_cost');
        
        $grossProfit = $isRefunded ? -$netRevenue : ($netRevenue - $totalCost);

        return [
            'id' => 'ORD-' . $order->id,
            'date' => $order->created_at->toDateTimeString(),
            'type' => $isRefunded ? 'Refunded Order' : 'Sale Order',
            'category' => $isRefunded ? 'Refund' : 'POS Sale',
            'title' => ($isRefunded ? '[REFUNDED] ' : '') . 'Order ' . ($order->receipt_number ?? $order->id),
            'reference' => $order->receipt_number ?? (string)$order->id,
            'items' => $items,
            'subtotal' => $isRefunded ? -$potentialRevenue : $potentialRevenue,
            'discount' => $isRefunded ? 0 : $totalDiscount,
            'revenue' => $isRefunded ? -$netRevenue : $netRevenue,
            'cost' => $isRefunded ? 0 : $totalCost,
            'profit_impact' => $grossProfit,
        ];
    }

    private function mapLossRecord(InventoryAdjustment $ia): array
    {
        $isReturn = $ia->reason === 'returned_to_supplier';
        $impact = $isReturn ? 0 : -abs($ia->financial_value);
        return [
            'id' => 'ADJ-' . $ia->id,
            'date' => $ia->created_at->toDateTimeString(),
            'type' => $isReturn ? 'Supplier Return' : 'Inventory Loss',
            'category' => $ia->reason,
            'title' => ($ia->product->name ?? 'Unknown') . " Adjustment",
            'revenue' => 0,
            'cost' => abs($ia->financial_value),
            'profit_impact' => $impact,
            'reference' => 'ADJ-' . $ia->id
        ];
    }

    private function mapExternalRecord(ExternalTransaction $et): array
    {
        $impact = $et->type === 'income' ? (float)$et->amount : -(float)$et->amount;
        return [
            'id' => 'EXT-' . $et->id,
            'date' => $et->transaction_date->toDateTimeString(),
            'type' => 'External ' . ucfirst($et->type),
            'category' => $et->category,
            'title' => $et->title,
            'revenue' => $et->type === 'income' ? (float)$et->amount : 0,
            'cost' => $et->type === 'expense' ? (float)$et->amount : 0,
            'profit_impact' => $impact,
            'reference' => $et->reference_number ?? 'EXT-' . $et->id
        ];
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
