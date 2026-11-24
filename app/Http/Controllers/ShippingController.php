<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ShippingController extends Controller
{
    private const ALLOWED_COURIERS = ['jne', 'jnt', 'sicepat', 'pos', 'tiki'];

    public function __construct(
        private readonly RajaOngkirService $rajaOngkir,
        private readonly CartService $cartService
    ) {}

    public function destinations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3'],
        ]);

        try {
            $results = $this->rajaOngkir->searchDestination($validated['q']);

            return response()->json([
                'data' => $results,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil data tujuan pengiriman.',
            ], 502);
        }
    }

    public function rates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:antar,ambil'],
            'destination_id' => ['required_unless:type,ambil', 'nullable', 'string'],
            'couriers' => ['nullable', 'string'],
        ]);

        $type = strtolower((string) ($validated['type'] ?? 'antar'));

        if ($type === 'ambil') {
            return response()->json([
                'type' => 'ambil',
                'weight_grams' => 0,
                'shipping_fee' => 0,
                'label' => 'Gratis',
                'rates' => [],
            ]);
        }

        $weightGrams = $this->calculateCartWeightGrams();

        if ($weightGrams <= 0) {
            return response()->json([
                'message' => 'Keranjang kosong atau berat total tidak valid.',
                'weight_grams' => 0,
                'rates' => [],
            ], 422);
        }

        $couriers = ['jne', 'jnt', 'sicepat'];
        if (!empty($validated['couriers'])) {
            $requested = array_filter(array_map(
                fn($c) => strtolower(trim($c)),
                explode(',', $validated['couriers'])
            ));
            $filtered = array_values(array_intersect($requested, self::ALLOWED_COURIERS));
            if (!empty($filtered)) {
                $couriers = $filtered;
            }
        }

        try {
            $rates = $this->rajaOngkir->getRates(
                (string) $validated['destination_id'],
                $weightGrams,
                $couriers
            );

            return response()->json([
                'type' => 'antar',
                'weight_grams' => $weightGrams,
                'rates' => $rates,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Gagal menghitung tarif pengiriman.',
            ], 502);
        }
    }

    private function calculateCartWeightGrams(): int
    {
        $rows = $this->cartService->rows();

        if (empty($rows)) {
            return 0;
        }

        $itemsByProduct = [];
        foreach ($rows as $row) {
            $productId = is_array($row) ? ($row['product_id'] ?? null) : ($row->product_id ?? null);
            $qty = is_array($row) ? (int) ($row['qty'] ?? $row['quantity'] ?? 0) : (int) ($row->qty ?? $row->quantity ?? 0);

            if ($productId && $qty > 0) {
                $itemsByProduct[$productId] = ($itemsByProduct[$productId] ?? 0) + $qty;
            }
        }

        if (empty($itemsByProduct)) {
            return 0;
        }

        $products = Product::whereIn('id', array_keys($itemsByProduct))->get()->keyBy('id');

        $totalWeight = 0;
        foreach ($itemsByProduct as $productId => $qty) {
            $product = $products->get((int) $productId);
            if ($product) {
                $unitWeight = max(1, (int) ($product->weight_grams ?? 500));
                $totalWeight += $unitWeight * $qty;
            }
        }

        return $totalWeight;
    }
}
