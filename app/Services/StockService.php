<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan stok varian untuk pesanan.
 *
 * Semua method di sini WAJIB dipanggil di dalam DB::transaction.
 * Penguncian baris memakai lockForUpdate() agar dua checkout bersamaan tidak
 * sama-sama melihat stok yang masih tersedia.
 */
class StockService
{
    public function __construct(private readonly StockAlertService $stockAlertService)
    {
    }

    /**
     * Potong stok sesuai item pesanan dan tandai bahwa order sudah memotong stok.
     *
     * @throws ValidationException jika stok tidak mencukupi
     */
    public function deductForOrder(Order $order): void
    {
        $requirements = $this->requirements($order);

        if ($requirements->isEmpty()) {
            // Produk tanpa varian tidak menyimpan stok di database, jadi tidak
            // ada yang perlu dipotong ataupun dikembalikan nantinya.
            return;
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $requirements->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requirements as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'cart' => 'Varian salah satu menu sudah tidak tersedia.',
                ]);
            }

            if ((int) $variant->stock < $quantity) {
                $name = $variant->product?->name ?? 'Menu';
                $label = $variant->variant_name ? " ({$variant->variant_name})" : '';

                throw ValidationException::withMessages([
                    'cart' => "Stok {$name}{$label} hanya tersisa {$variant->stock}.",
                ]);
            }
        }

        foreach ($requirements as $variantId => $quantity) {
            $variants->get($variantId)->decrement('stock', $quantity);
        }

        $order->forceFill(['stock_deducted_at' => now()])->save();
        $this->stockAlertService->sync();
    }

    /**
     * Kembalikan stok saat pesanan dibatalkan/kedaluwarsa.
     *
     * Mengembalikan false bila stok tidak pernah dipotong (order lama) atau
     * sudah dikembalikan, sehingga pemanggilan ganda tidak menaikkan stok dua kali.
     */
    public function restoreForOrder(Order $order): bool
    {
        if (! $order->stock_deducted_at || $order->stock_restored_at) {
            return false;
        }

        $requirements = $this->requirements($order);

        if (! $requirements->isEmpty()) {
            $variants = ProductVariant::query()
                ->whereIn('id', $requirements->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requirements as $variantId => $quantity) {
                $variants->get($variantId)?->increment('stock', $quantity);
            }
        }

        $order->forceFill(['stock_restored_at' => now()])->save();

        return true;
    }

    /**
     * Agregasi quantity per varian agar baris keranjang dengan varian sama
     * (misal beda tingkat kemanisan) tetap dihitung sebagai satu total stok.
     *
     * @return Collection<int|string, int>
     */
    private function requirements(Order $order): Collection
    {
        return $order->items()
            ->whereNotNull('product_variant_id')
            ->select('product_variant_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('product_variant_id')
            ->pluck('qty', 'product_variant_id')
            ->map(fn ($qty) => (int) $qty);
    }
}
