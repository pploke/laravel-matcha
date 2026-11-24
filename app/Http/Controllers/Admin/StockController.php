<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\StockAlertService;

class StockController extends Controller
{
    public function index(StockAlertService $stockAlerts)
    {
        $stockAlerts->sync();

        $variants = ProductVariant::query()
            ->with(['product.lowStockAlert'])
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.name')
            ->orderBy('product_variants.id')
            ->select('product_variants.*')
            ->get();

        $rows = $variants->map(function (ProductVariant $variant) {
            $threshold = $variant->product?->lowStockAlert?->min_threshold ?? 0;

            $status = match (true) {
                $variant->stock <= 0 => 'Habis',
                $threshold > 0 && $variant->stock < $threshold => 'Menipis',
                default => 'Aman',
            };

            return [
                'variant' => $variant,
                'threshold' => $threshold,
                'status' => $status,
            ];
        });

        $stats = [
            'low' => $rows->where('status', 'Menipis')->count(),
            'out' => $rows->where('status', 'Habis')->count(),
            'safe' => $rows->where('status', 'Aman')->count(),
            'total' => $rows->count(),
        ];

        return view('admin.stock.index', compact('rows', 'stats'));
    }
}
