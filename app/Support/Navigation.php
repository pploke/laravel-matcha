<?php

namespace App\Support;

/**
 * Menu sidebar. Setiap item memakai kunci: label, icon, url, active (pola routeIs).
 * Item yang punya 'children' ditampilkan sebagai grup dropdown (seperti di FE).
 */
class Navigation
{
    public static function konsumen(): array
    {
        return [
            ['label' => 'Beranda', 'icon' => 'bi-house', 'url' => route('beranda'), 'active' => 'beranda'],
            ['label' => 'Katalog Menu', 'icon' => 'bi-cup-straw', 'url' => route('katalog.index'), 'active' => 'katalog.*'],
            ['label' => 'Keranjang', 'icon' => 'bi-bag', 'url' => route('cart.index'), 'active' => 'cart.*'],
            ['label' => 'Checkout', 'icon' => 'bi-credit-card', 'url' => route('checkout.index'), 'active' => 'checkout.*'],
            ['label' => 'Riwayat Pesanan', 'icon' => 'bi-clock-history', 'url' => route('orders.history'), 'active' => 'orders.history'],
        ];
    }

   
    public static function admin(): array
    {
        return [
            ['label' => 'Dashboard', 'icon' => 'bi-grid', 'url' => route('admin.dashboard'), 'active' => 'admin.dashboard'],
            [
                'label' => 'Produk & Menu',
                'icon' => 'bi-box-seam',
                'children' => [
                    ['label' => 'Daftar Produk', 'url' => route('admin.products.index'), 'active' => 'admin.products.*'],
                    ['label' => 'Stok & Persediaan', 'url' => route('admin.stock.index'), 'active' => 'admin.stock.*'],
                ],
            ],
            ['label' => 'Pesanan', 'icon' => 'bi-receipt', 'url' => route('admin.orders.index'), 'active' => 'admin.orders.*'],
            ['label' => 'Pengeluaran', 'icon' => 'bi-wallet2', 'url' => route('admin.expenses.index'), 'active' => 'admin.expenses.*'],
            [
                'label' => 'Rekap Penjualan',
                'icon' => 'bi-bar-chart-line',
                'children' => [
                    ['label' => 'Harian', 'url' => route('admin.reports.daily'), 'active' => 'admin.reports.daily'],
                    ['label' => 'Mingguan', 'url' => route('admin.reports.weekly'), 'active' => 'admin.reports.weekly'],
                    ['label' => 'Bulanan', 'url' => route('admin.reports.monthly'), 'active' => 'admin.reports.monthly'],
                ],
            ],
        ];
    }
}
