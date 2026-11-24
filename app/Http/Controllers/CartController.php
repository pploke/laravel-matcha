<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index()
    {
        $rows = $this->cart->rows();
        $subtotal = $this->cart->subtotal();

        return view('keranjang', compact('rows', 'subtotal'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'sweet' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ], [
            'product_id.required' => 'Produk tidak valid.',
        ]);

        $this->cart->add(
            (int) $data['product_id'],
            isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null,
            $data['sweet'] ?? '50%',
            $data['note'] ?? '',
            (int) ($data['qty'] ?? 1),
        );

        return back()->with('success', 'Menu ditambahkan ke keranjang.');
    }

    public function update(Request $request, string $row)
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->cart->update($row, (int) $data['qty']);

        return back()->with('success', 'Jumlah pesanan diperbarui.');
    }

    public function remove(string $row)
    {
        $this->cart->remove($row);

        return back()->with('success', 'Item dihapus dari keranjang.');
    }
}
