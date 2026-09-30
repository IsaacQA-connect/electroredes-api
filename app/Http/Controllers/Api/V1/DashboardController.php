<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use App\Models\Order;

class DashboardController extends Controller
{
    public function metrics(Request $request)
    {
        try {
            $validStatuses = ['COMPLETADO', 'COMPLETED', 'completed', 'Completado'];

            // 1. Determinar el Rango de Fechas según el filtro recibido
            $period = $request->get('period', '7days');
            $endDate = now()->endOfDay();

            switch ($period) {
                case 'today':
                    $startDate = now()->startOfDay();
                    break;
                case 'this_month':
                    $startDate = now()->startOfMonth();
                    break;
                case 'last_month':
                    $startDate = now()->subMonth()->startOfMonth();
                    $endDate = now()->subMonth()->endOfMonth();
                    break;
                case '30days':
                    $startDate = now()->subDays(30)->startOfDay();
                    break;
                case 'custom':
                    $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date'))->startOfDay() : now()->subDays(7)->startOfDay();
                    $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date'))->endOfDay() : now()->endOfDay();
                    break;
                case '7days':
                default:
                    $startDate = now()->subDays(7)->startOfDay();
                    break;
            }

            // 2. Ventas Totales en el periodo
            $totalSales = (float) Order::whereIn('status', $validStatuses)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total');

            // 3. Costo Total en el periodo
            $totalCost = (float) DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->whereIn('orders.status', $validStatuses)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->sum(DB::raw('order_details.quantity * IFNULL(products.cost, 0)'));

            // 4. Ganancia Bruta y Margen %
            $grossProfit = $totalSales - $totalCost;
            $profitMargin = $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 2) : 0;

            // 5. Top 5 Productos del periodo
            $topProducts = DB::table('order_details')
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereIn('orders.status', $validStatuses)
                ->whereBetween('orders.created_at', [$startDate, $endDate])
                ->select(
                    'products.name',
                    DB::raw('SUM(order_details.quantity) as total_qty'),
                    DB::raw('SUM(order_details.quantity * order_details.unit_price) as total_revenue')
                )
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $item->total_revenue = (float) $item->total_revenue;
                    $item->total_qty = (int) $item->total_qty;
                    return $item;
                });

            // 6. Ventas por Canal en el periodo
            $salesByChannel = [];
            if (Schema::hasColumn('orders', 'channel')) {
                $salesByChannel = Order::select('channel', DB::raw('SUM(total) as total_amount'))
                    ->whereIn('status', $validStatuses)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupBy('channel')
                    ->get()
                    ->map(function ($item) {
                        $item->total_amount = (float) $item->total_amount;
                        return $item;
                    });
            }

            // 7. Tendencia Diaria en el periodo
            $salesTrend = Order::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total) as daily_total')
                )
                ->whereIn('status', $validStatuses)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'ASC')
                ->get()
                ->map(function ($item) {
                    $item->daily_total = (float) $item->daily_total;
                    return $item;
                });

            return response()->json([
                'status' => 'success',
                'period' => $period,
                'range' => [
                    'start' => $startDate->toDateTimeString(),
                    'end' => $endDate->toDateTimeString()
                ],
                'kpis' => [
                    'total_sales' => $totalSales,
                    'gross_profit' => $grossProfit,
                    'profit_margin_percentage' => $profitMargin,
                ],
                'top_products' => $topProducts,
                'sales_by_channel' => $salesByChannel,
                'sales_trend' => $salesTrend
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al calcular métricas: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}