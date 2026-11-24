<?php

namespace App\Services;

use App\Models\LowStockAlert;

/**
 * Notifikasi stok menipis berbasis kolom low_stock_alerts.is_notified.
 *
 * - Stok di bawah batas & is_notified = false -> ditandai true, dikembalikan sebagai "alert baru".
 * - Stok di bawah batas & is_notified = true  -> sudah diberi tahu, tidak diulang (cooldown).
 * - Stok kembali aman & is_notified = true    -> di-reset ke false.
 */
class StockAlertService
{
    /**
     * @return \Illuminate\Support\Collection<int, string> nama produk yang BARU masuk status menipis
     */
    public function sync()
    {
        $newlyLow = collect();

        LowStockAlert::query()
            ->with('product.variants')
            ->get()
            ->each(function (LowStockAlert $alert) use ($newlyLow) {
                $product = $alert->product;

                if (! $product || $alert->min_threshold <= 0) {
                    return;
                }

                $isLow = $product->variants->contains(
                    fn ($variant) => $variant->stock < $alert->min_threshold
                );

                if ($isLow && ! $alert->is_notified) {
                    $alert->update(['is_notified' => true]);
                    $newlyLow->push($product->name);
                } elseif (! $isLow && $alert->is_notified) {
                    $alert->update(['is_notified' => false]);
                }
            });

        return $newlyLow;
    }
}