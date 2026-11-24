<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    /**
     * Mengambil ringkasan penjualan harian.
     */
    public function daily(Carbon $date): array
    {
        $orders = Order::query()
            ->where('order_status', Order::STATUS_COMPLETED)
            ->whereDate('completed_at', $date->toDateString())
            ->with('items')
            ->get();

        $totalTransactions = $orders->count();

        $totalItemsSold = $orders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $totalRevenue = $orders->sum('total_amount');

        $averageTransaction = $totalTransactions > 0
            ? $totalRevenue / $totalTransactions
            : 0;

        return [
            'date' => $date->toDateString(),
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'total_items_sold' => $totalItemsSold,
            'average_transaction' => $averageTransaction,
        ];
    }

    /**
     * Mengambil jumlah produk dan varian yang terjual
     * dalam rentang tanggal tertentu.
     */
    public function productsSold(
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin(
                'product_variants',
                'order_items.product_variant_id',
                '=',
                'product_variants.id'
            )
            ->where('orders.order_status', Order::STATUS_COMPLETED)
            ->whereBetween('orders.completed_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.id as variant_id',
                'product_variants.variant_name as variant_name',
                DB::raw('SUM(order_items.quantity) as quantity_sold')
            )
            ->groupBy(
                'products.id',
                'products.name',
                'product_variants.id',
                'product_variants.variant_name'
            )
            ->orderByDesc('quantity_sold')
            ->get();
    }

    /**
     * Mengambil ringkasan penjualan berdasarkan jam.
     */
    public function hourly(Carbon $date): Collection
    {
        $sales = DB::table('orders')
            ->where('order_status', Order::STATUS_COMPLETED)
            ->whereDate('completed_at', $date->toDateString())
            ->selectRaw('
                HOUR(completed_at) as hour,
                COUNT(*) as total_transactions,
                SUM(total_amount) as total_revenue
            ')
            ->groupByRaw('HOUR(completed_at)')
            ->get()
            ->keyBy('hour');

        return collect(range(0, 23))->map(function ($hour) use ($sales) {
            $sale = $sales->get($hour);

            return [
                'hour' => $hour,
                'label' => str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00',
                'total_transactions' => (int) ($sale->total_transactions ?? 0),
                'total_revenue' => (int) ($sale->total_revenue ?? 0),
            ];
        });
    }

    /**
     * Rekap penjualan satu bulan penuh 
     */
    public function monthly(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return $this->period($start, $start->copy()->endOfMonth());
    }

    /**
     * Rekap penjualan satu minggu ISO (Senin - Minggu).
     */
    public function weekly(int $isoYear, int $isoWeek): array
    {
        $start = Carbon::now()->setISODate($isoYear, $isoWeek, 1)->startOfDay();

        return $this->period($start, $start->copy()->addDays(6));
    }

    /**
     * Inti agregasi: satu baris per hari dalam rentang, termasuk hari tanpa
     * penjualan (nilai 0). Total dihitung dari penjumlahan baris harian,
     * sehingga total periode selalu sama dengan jumlah rekap harian.
     */
    private function period(Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $orderRows = DB::table('orders')
            ->where('order_status', Order::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('
                DATE(completed_at) as sale_date,
                COUNT(*) as total_transactions,
                SUM(total_amount) as total_revenue
            ')
            ->groupByRaw('DATE(completed_at)')
            ->get()
            ->keyBy('sale_date');

        $itemRows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.order_status', Order::STATUS_COMPLETED)
            ->whereBetween('orders.completed_at', [$start, $end])
            ->selectRaw('
                DATE(orders.completed_at) as sale_date,
                SUM(order_items.quantity) as total_items
            ')
            ->groupByRaw('DATE(orders.completed_at)')
            ->get()
            ->keyBy('sale_date');

        $days = collect();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->toDateString();

            $days->push([
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'total_revenue' => (int) ($orderRows->get($key)->total_revenue ?? 0),
                'total_transactions' => (int) ($orderRows->get($key)->total_transactions ?? 0),
                'total_items_sold' => (int) ($itemRows->get($key)->total_items ?? 0),
            ]);
        }

        $totalRevenue = $days->sum('total_revenue');
        $totalTransactions = $days->sum('total_transactions');

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $end->copy()->startOfDay(),
            'total_revenue' => $totalRevenue,
            'total_transactions' => (int) $totalTransactions,
            'total_items_sold' => (int) $days->sum('total_items_sold'),
            'average_transaction' => $totalTransactions > 0
                ? $totalRevenue / $totalTransactions
                : 0,
            'days' => $days,
        ];
    }
}
