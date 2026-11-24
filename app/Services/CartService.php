<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Keranjang belanja berbasis session (pengganti CartContext + localStorage di FE lama).
 * Baris keranjang digabung berdasarkan produk + varian + tingkat gula + catatan,
 * persis perilaku CartContext lama.
 */
class CartService
{
    private const KEY = 'cart';

    public function items(): array
    {
        return Session::get(self::KEY, []);
    }

    /** Baris keranjang siap tampil (dilengkapi row_id, subtotal, dan sisa stok). */
    public function rows(): array
    {
        $items = $this->items();

        $variantIds = collect($items)
            ->pluck('variant_id')
            ->filter()
            ->unique()
            ->values();

        $stocks = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->pluck('stock', 'id');

        return collect($items)
            ->map(function (array $item, string $rowId) use ($stocks) {
                $variantId = $item['variant_id'] ?? null;

                $item['row_id'] = $rowId;
                $item['unit_price'] = (int) ($item['unit_price'] ?? 0);
                $item['qty'] = (int) ($item['qty'] ?? 0);
                $item['subtotal'] = $item['unit_price'] * $item['qty'];
                $item['stock'] = $variantId ? (int) ($stocks[$variantId] ?? 0) : null;

                return $item;
            })
            ->values()
            ->all();
    }

    public function count(): int
    {
        return (int) collect($this->items())->sum('qty');
    }

    public function subtotal(): int
    {
        return (int) collect($this->items())
            ->sum(fn (array $item) => (int) ($item['unit_price'] ?? 0) * (int) ($item['qty'] ?? 0));
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }

    /**
     * @throws ValidationException jika varian wajib tidak dipilih atau stok tidak cukup
     */
    public function add(int $productId, ?int $variantId, string $sweet, string $note, int $qty = 1): void
    {
        $product = Product::with('variants')->find($productId);

        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages([
                'cart' => 'Produk tidak ditemukan atau sudah tidak aktif.',
            ]);
        }

        $variants = $product->variants;
        $variant = null;

        // Produk bervarian tidak boleh masuk keranjang tanpa varian;
        // sebaliknya produk tanpa varian tidak boleh menerima varian asing.
        if ($variants->isNotEmpty() && ! $variantId) {
            throw ValidationException::withMessages([
                'cart' => 'Pilih varian untuk produk "'.$product->name.'".',
            ]);
        }

        if ($variants->isEmpty() && $variantId) {
            throw ValidationException::withMessages([
                'cart' => 'Produk "'.$product->name.'" tidak memiliki varian.',
            ]);
        }

        if ($variantId) {
            $variant = $variants->firstWhere('id', $variantId);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'cart' => 'Varian produk tidak ditemukan.',
                ]);
            }

            if ((int) $variant->stock <= 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Stok varian ini sedang habis.',
                ]);
            }
        }

        $qty = max(1, $qty);
        $cart = $this->items();

        if ($variant) {
            $inCart = collect($cart)
                ->filter(fn (array $item) => (int) ($item['variant_id'] ?? 0) === (int) $variant->id)
                ->sum(fn (array $item) => (int) ($item['qty'] ?? 0));

            if ($inCart + $qty > (int) $variant->stock) {
                throw ValidationException::withMessages([
                    'cart' => 'Stok '.$product->name.($variant->variant_name ? ' ('.$variant->variant_name.')' : '').' tersisa '.$variant->stock.'.',
                ]);
            }
        }

        $unitPrice = (int) $product->base_price + ($variant ? (int) $variant->additional_price : 0);

        $rowId = md5($product->id.'|'.($variant?->id ?? 'null').'|'.$sweet.'|'.$note);

        if (isset($cart[$rowId])) {
            $cart[$rowId]['qty'] += $qty;
        } else {
            $cart[$rowId] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->name,
                'variant_name' => $variant?->variant_name,
                'tone' => $product->tone,
                'image' => $product->image,
                'sweet' => $sweet,
                'note' => $note,
                'unit_price' => $unitPrice,
                'qty' => $qty,
            ];
        }

        Session::put(self::KEY, $cart);
    }

    /**
     * @throws ValidationException jika qty melebihi stok varian
     */
    public function update(string $rowId, int $qty): void
    {
        $cart = $this->items();

        if (! isset($cart[$rowId])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$rowId]);
            Session::put(self::KEY, $cart);

            return;
        }

        $variantId = $cart[$rowId]['variant_id'] ?? null;

        if ($variantId) {
            $variant = ProductVariant::find($variantId);

            if ($variant) {
                $inCart = collect($cart)
                    ->except([$rowId])
                    ->filter(fn (array $item) => (int) ($item['variant_id'] ?? 0) === (int) $variant->id)
                    ->sum(fn (array $item) => (int) ($item['qty'] ?? 0));

                if ($inCart + $qty > (int) $variant->stock) {
                    throw ValidationException::withMessages([
                        'cart' => 'Stok varian ini tersisa '.$variant->stock.'.',
                    ]);
                }
            }
        }

        $cart[$rowId]['qty'] = $qty;

        Session::put(self::KEY, $cart);
    }

    public function remove(string $rowId): void
    {
        $cart = $this->items();
        unset($cart[$rowId]);

        Session::put(self::KEY, $cart);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }
}
