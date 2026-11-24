<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->withCount('variants')
            ->with(['variants', 'lowStockAlert'])
            ->orderBy('id')
            ->get();

        $stats = [
            'total' => $products->count(),
            'active' => $products->where('is_active', true)->count(),
            // Produk dengan minimal satu varian di bawah ambang batas stok.
            'attention' => $products->filter(function (Product $product) {
                $threshold = $product->lowStockAlert?->min_threshold ?? 0;

                return $product->variants->contains(fn (ProductVariant $variant) => $variant->stock < $threshold);
            })->count(),
        ];

        return view('admin.products.index', compact('products', 'stats'));
    }

    public function create()
    {
        $product = new Product(['is_active' => true]);
        $minThreshold = 10;

        return view('admin.products.form', compact('product', 'minThreshold'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'cost_price' => $data['cost_price'] ?? 0,
                'image_url' => $data['image_url'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);

            foreach ($data['variants'] ?? [] as $variant) {
                if (empty($variant['variant_name'])) {
                    continue;
                }

                $product->variants()->create([
                    'variant_name' => $variant['variant_name'],
                    'additional_price' => $variant['additional_price'] ?? 0,
                    'stock' => $variant['stock'] ?? 0,
                ]);
            }

            $product->lowStockAlert()->create([
                'min_threshold' => $data['min_threshold'] ?? 10,
                'is_notified' => false,
            ]);
        });

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        $product->load(['variants', 'lowStockAlert']);
        $minThreshold = $product->lowStockAlert?->min_threshold ?? 10;

        return view('admin.products.form', compact('product', 'minThreshold'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $product) {
            $product->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'],
                'cost_price' => $data['cost_price'] ?? 0,
                'image_url' => $data['image_url'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);

            foreach ($data['variants'] ?? [] as $variant) {
                if (! empty($variant['id'])) {
                    $existing = $product->variants()->whereKey($variant['id'])->first();

                    if ($existing && ! empty($variant['variant_name'])) {
                        $existing->update([
                            'variant_name' => $variant['variant_name'],
                            'additional_price' => $variant['additional_price'] ?? 0,
                            'stock' => $variant['stock'] ?? 0,
                        ]);
                    }

                    continue;
                }

                if (! empty($variant['variant_name'])) {
                    $product->variants()->create([
                        'variant_name' => $variant['variant_name'],
                        'additional_price' => $variant['additional_price'] ?? 0,
                        'stock' => $variant['stock'] ?? 0,
                    ]);
                }
            }

            $product->lowStockAlert()->updateOrCreate(
                ['product_id' => $product->id],
                ['min_threshold' => $data['min_threshold'] ?? 10],
            );
        });

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        try {
            $product->delete();
        } catch (QueryException) {
            return back()->with('error', 'Produk masih berelasi dengan pesanan sehingga tidak bisa dihapus. Nonaktifkan produk ini saja.');
        }

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'min_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.variant_name' => ['nullable', 'string', 'max:255'],
            'variants.*.additional_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Nama produk wajib diisi.',
            'base_price.required' => 'Harga produk wajib diisi.',
            'base_price.numeric' => 'Harga produk harus berupa angka.',
            'image_url.url' => 'URL gambar tidak valid.',
            'variants.*.additional_price.numeric' => 'Harga tambahan varian harus berupa angka.',
            'variants.*.stock.integer' => 'Stok varian harus berupa bilangan bulat.',
        ]);
    }
}
