<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Services\StockAlertService;

class DashboardController extends Controller
{
    public function index(StockAlertService $stockAlerts)
    {
        $todayRevenue = (int) Order::query()
            ->where('order_status', Order::STATUS_COMPLETED)
            ->whereDate('created_at', today())
            ->sum('total_amount');

        $todayOrders = Order::query()->whereDate('created_at', today())->count();
        $processing = Order::query()->where('order_status', Order::STATUS_PROCESSING)->count();
        $completed = Order::query()->where('order_status', Order::STATUS_COMPLETED)->count();

        $recentOrders = Order::query()->withCount('items')->latest()->take(5)->get();

        $newAlerts = $stockAlerts->sync();

        return view('admin.dashboard', compact(
            'todayRevenue',
            'todayOrders',
            'processing',
            'completed',
            'recentOrders',
            'newAlerts',
        ));
    }
}
