<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SalesReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request, SalesReportService $reportService)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::parse(
            $validated['date'] ?? today()->toDateString()
        );

        // Ringkasan penjualan harian
        $summary = $reportService->daily($date);

        // Daftar transaksi yang selesai pada tanggal terpilih
        $transactions = Order::query()
            ->where('order_status', Order::STATUS_COMPLETED)
            ->whereDate('completed_at', $date->toDateString())
            ->with(['items.product', 'items.variant'])
            ->latest('completed_at')
            ->paginate(10)
            ->withQueryString();

        // Rekap produk dan varian terjual
        $productsSold = $reportService->productsSold(
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay()
        );

        // Rekap penjualan berdasarkan jam
        $hourlySales = $reportService->hourly($date);

        return view('admin.reports.daily', compact(
            'date',
            'summary',
            'transactions',
            'productsSold',
            'hourlySales'
        ));
    }

    
    public function monthly(Request $request, SalesReportService $reportService)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat(
            'Y-m-d',
            ($validated['month'] ?? today()->format('Y-m')) . '-01'
        )->startOfDay();

        $report = $reportService->monthly($month->year, $month->month);

        return view('admin.reports.monthly', [
            'period' => 'monthly',
            'title' => 'Rekap Penjualan Bulanan',
            'periodLabel' => $month->copy()->locale('id')->translatedFormat('F Y'),
            'inputValue' => $month->format('Y-m'),
            'report' => $report,
        ]);
    }

    public function weekly(Request $request, SalesReportService $reportService)
    {
        $validated = $request->validate([
            'week' => ['nullable', 'regex:/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/'],
        ]);

        $week = $validated['week'] ?? today()->format('o-\WW');
        [$year, $weekNumber] = array_map('intval', explode('-W', $week));

        $report = $reportService->weekly($year, $weekNumber);

        $periodLabel = 'Minggu ke-' . $weekNumber . ' · '
            . $report['start']->copy()->locale('id')->translatedFormat('d M')
            . ' – '
            . $report['end']->copy()->locale('id')->translatedFormat('d M Y');

        return view('admin.reports.monthly', [
            'period' => 'weekly',
            'title' => 'Rekap Penjualan Mingguan',
            'periodLabel' => $periodLabel,
            'inputValue' => $week,
            'report' => $report,
        ]);
    }
}
