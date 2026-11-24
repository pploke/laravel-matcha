<?php

namespace App\Http\Controllers;

use App\Models\Product;

class HomeController extends Controller
{
    public function landing()
    {
        $products = Product::query()
            ->active()
            ->withCount('variants')
            ->with('variants')
            ->orderBy('id')
            ->take(8)
            ->get();

        return view('landing', compact('products'));
    }

    public function beranda()
    {
        $products = Product::query()
            ->active()
            ->withCount('variants')
            ->with('variants')
            ->orderBy('id')
            ->take(4)
            ->get();

        return view('beranda', compact('products'));
    }
}
