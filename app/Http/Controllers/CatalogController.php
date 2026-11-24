<?php

namespace App\Http\Controllers;

use App\Models\Product;

class CatalogController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->active()
            ->withCount('variants')
            ->with('variants')
            ->orderBy('id')
            ->get();

        return view('katalog.index', compact('products'));
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load('variants');

        $sweetness = ['25%', '50%', '100%'];

        return view('katalog.show', compact('product', 'sweetness'));
    }
}
