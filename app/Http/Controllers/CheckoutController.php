<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\Payment\MidtransService;
use App\Services\RajaOngkirService;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly RajaOngkirService $rajaOngkir
    ) {}

    private const PICKUP_OPTIONS = [
        'Secepatnya (± 15 menit)',
        '30 menit lagi',
        '1 jam lagi',
    ];

    public function index(CartService $cart)
    {
        $rows = $cart->rows();
        $subtotal = $cart->subtotal();
        // Ongkir dihitung dinamis setelah tujuan & layanan dipilih di halaman checkout.
        $ongkir = 0;
        $pickupOptions = self::PICKUP_OPTIONS;
        $originLabel = config('services.rajaongkir.origin_label', '');

        // Token sekali pakai untuk mencegah dua submit membuat dua pesanan.
        // Disimpan juga di session agar token buatan sembarangan ditolak.
        $checkoutToken = (string) Str::uuid();
        $tokens = session('checkout_tokens', []);
        $tokens[] = $checkoutToken;
        session(['checkout_tokens' => array_values(array_unique(array_slice($tokens, -5)))]);

        return view('checkout', compact(
            'rows',
            'subtotal',
            'ongkir',
            'pickupOptions',
            'checkoutToken',
            'originLabel'
        ));
    }

    public function store(Request $request, CartService $cart, StockService $stockService)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'type' => ['required', 'in:antar,ambil'],
            'address' => ['required_if:type,antar', 'nullable', 'string', 'max:500'],
            'pickup' => ['nullable', 'string', 'max:100'],
            'shipping_destination_id' => ['required_if:type,antar', 'nullable', 'string', 'max:100'],
            'shipping_destination_label' => ['nullable', 'string', 'max:255'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_courier' => ['required_if:type,antar', 'nullable', 'string', 'max:50'],
            'shipping_service' => ['required_if:type,antar', 'nullable', 'string', 'max:50'],
            'checkout_token' => ['required', 'uuid'],
            'payment_method' => ['required', 'in:' . implode(',', [
                Order::PAYMENT_METHOD_CASH,
                Order::PAYMENT_METHOD_QRIS,
                Order::PAYMENT_METHOD_BANK_TRANSFER,
            ])],
        ], [
            'name.required' => 'Nama penerima wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'address.required_if' => 'Alamat wajib diisi untuk pesanan diantar.',
            'shipping_destination_id.required_if' => 'Tujuan pengiriman wajib dipilih.',
            'shipping_courier.required_if' => 'Kurir wajib dipilih.',
            'shipping_service.required_if' => 'Layanan pengiriman wajib dipilih.',
            'checkout_token.required' => 'Token checkout tidak ditemukan. Muat ulang halaman checkout.',
            'checkout_token.uuid' => 'Token checkout tidak valid.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ]);

        $token = $data['checkout_token'];

        // Submit kedua (atau reload setelah submit berhasil) langsung diarahkan
        // ke pesanan yang sudah dibuat, bukan membuat pesanan duplikat.
        $existingOrder = Order::where('checkout_token', $token)->first();

        if ($existingOrder) {
            return $this->redirectToExistingOrder($existingOrder, $request);
        }

        if (! in_array($token, session('checkout_tokens', []), true)) {
            return back()->with('error', 'Sesi checkout tidak valid. Silakan muat ulang halaman checkout.');
        }

        $rows = $cart->rows();

        if (empty($rows)) {
            return back()->with('error', 'Keranjang masih kosong. Pilih menu terlebih dahulu.');
        }

        // Hitung ulang harga di server (tidak mempercayai harga dari session/klien)
        $itemsData = [];
        $subtotal = 0;
        $totalWeightGrams = 0;

        foreach ($rows as $row) {
            $product = Product::with('variants')->find($row['product_id']);

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'cart' => 'Produk "' . $row['name'] . '" sudah tidak tersedia.',
                ]);
            }

            $variants = $product->variants;
            $variant = null;

            if (! empty($row['variant_id'])) {
                $variant = $variants->firstWhere('id', $row['variant_id']);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'cart' => 'Varian untuk produk "' . $product->name . '" tidak ditemukan.',
                    ]);
                }
            }

            if ($variants->isNotEmpty() && ! $variant) {
                throw ValidationException::withMessages([
                    'cart' => 'Pilih varian untuk produk "' . $product->name . '".',
                ]);
            }

            if ($variants->isEmpty() && $variant) {
                throw ValidationException::withMessages([
                    'cart' => 'Produk "' . $product->name . '" tidak memiliki varian.',
                ]);
            }

            $unitPrice = (int) $product->base_price + ($variant ? (int) $variant->additional_price : 0);
            $quantity = (int) $row['qty'];
            $subtotal += $unitPrice * $quantity;

            // Berat dihitung server-side dari data produk; klien tidak boleh menentukannya.
            // Produk makanan/minuman memakai fallback 500 gram per satuan.
            $unitWeight = (int) ($product->weight_grams ?: 500);
            $totalWeightGrams += max(1, $unitWeight) * $quantity;

            // Menyiapkan data item dengan sweetness dan note dari keranjang
            $itemsData[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
                'sweetness' => $row['sweetness'] ?? $row['sweet'] ?? null,
                'note' => $row['note'] ?? null,
                'unit_price' => $unitPrice,
            ];
        }

        // Metadata pengiriman selalu berasal dari server. Nilai shipping_* dari
        // klien hanya dipakai sebagai kunci pencocokan tarif, bukan sebagai harga.
        $shippingFee = 0;
        $shippingData = [
            'shipping_courier' => null,
            'shipping_service' => null,
            'shipping_etd' => null,
            'shipping_destination_id' => null,
            'shipping_destination_label' => null,
            'shipping_weight_grams' => 0,
            'shipping_postal_code' => null,
        ];

        if ($data['type'] === 'antar') {
            $courier = Str::lower(trim((string) $data['shipping_courier']));
            $service = Str::lower(trim((string) $data['shipping_service']));

            try {
                $rates = $this->rajaOngkir->getRates(
                    (string) $data['shipping_destination_id'],
                    $totalWeightGrams,
                    [$courier]
                );
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil tarif RajaOngkir saat checkout', [
                    'destination' => $data['shipping_destination_id'],
                    'weight_grams' => $totalWeightGrams,
                    'error' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'shipping_service' => 'Ongkir sementara tidak dapat dihitung. Silakan coba lagi.',
                ]);
            }

            $matchedRate = collect($rates)->first(function (array $rate) use ($courier, $service) {
                return Str::lower((string) ($rate['courier'] ?? '')) === $courier
                    && Str::lower((string) ($rate['service'] ?? '')) === $service;
            });

            if (! $matchedRate) {
                throw ValidationException::withMessages([
                    'shipping_service' => 'Layanan pengiriman tidak tersedia untuk tujuan ini. Silakan pilih layanan lain.',
                ]);
            }

            $shippingFee = max(0, (int) ($matchedRate['cost'] ?? 0));
            $shippingData = [
                'shipping_courier' => (string) ($matchedRate['courier'] ?? $courier),
                'shipping_service' => (string) ($matchedRate['service'] ?? $service),
                'shipping_etd' => (string) ($matchedRate['etd'] ?? '') ?: null,
                'shipping_destination_id' => (string) $data['shipping_destination_id'],
                'shipping_destination_label' => $data['shipping_destination_label'] ?? null,
                'shipping_weight_grams' => $totalWeightGrams,
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
            ];
        }

        $totalAmount = $subtotal + $shippingFee;

        $shippingAddress = $data['type'] === 'antar'
            ? $data['address']
            : 'Ambil di tempat (Dine-in/Takeaway)' . (! empty($data['pickup']) ? ' · Ambil: ' . $data['pickup'] : '');

        $isCash = $data['payment_method'] === Order::PAYMENT_METHOD_CASH;

        try {
            $order = DB::transaction(function () use (
                $request,
                $data,
                $token,
                $itemsData,
                $shippingFee,
                $shippingData,
                $subtotal,
                $totalAmount,
                $shippingAddress,
                $isCash,
                $stockService
            ) {
                // Generate order number unik, misal: MATCHA-YYYYMMDD-XXXX
                $orderNumber = 'MATCHA-' . date('Ymd') . '-' . strtoupper(Str::random(5));

                $order = Order::create([
                    'user_id' => $request->user()?->id,
                    'order_number' => $orderNumber,
                    'checkout_token' => $token,
                    'customer_name' => $data['name'],
                    'customer_phone' => $data['phone'],
                    'shipping_address' => $shippingAddress,
                    'shipping_fee' => $shippingFee,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'total_amount' => $totalAmount,
                    'delivery_type' => $data['type'], // 'antar' atau 'ambil'
                    'payment_method' => $data['payment_method'],
                    // Tunai langsung diproses di gerai; QRIS/transfer menunggu pembayaran.
                    'order_status' => $isCash ? Order::STATUS_PROCESSING : Order::STATUS_PENDING,
                    'payment_status' => 'UNPAID',
                    'expires_at' => $isCash ? null : now()->addMinutes(config('services.midtrans.expiry_minutes', 15)),
                    ...$shippingData,
                ]);

                $order->items()->createMany($itemsData);

                // Stok dipotong di dalam transaksi yang sama dengan pembuatan order.
                // Jika stok ternyata tidak cukup, ValidationException menggulung
                // seluruh transaksi sehingga tidak ada order setengah jadi.
                $stockService->deductForOrder($order);

                return $order;
            });
        } catch (QueryException $e) {
            // Dua request bersamaan: yang kalah kena unique constraint checkout_token.
            $existingOrder = Order::where('checkout_token', $token)->first();

            if ($existingOrder) {
                return $this->redirectToExistingOrder($existingOrder, $request);
            }

            throw $e;
        }

        // Tamu: simpan id pesanan di session (pengganti localStorage FE lama)
        $this->rememberGuestOrder($order, $request);

        $tokens = array_values(array_diff(session('checkout_tokens', []), [$token]));
        session(['checkout_tokens' => $tokens]);

        $cart->clear();
        session()->flash('new_order_id', $order->id);

        // Pesanan tunai tidak memakai Midtrans: tidak ada token maupun baris
        // pembayaran. Admin menandai lunas setelah uang diterima.
        if ($isCash) {
            return redirect()
                ->route('payment.show', $order)
                ->with('success', 'Pesanan berhasil dibuat. Silakan bayar tunai saat pesanan diambil/diterima.');
        }

        // Buat transaksi Snap Midtrans. Jika gagal (mis. jaringan/konfigurasi),
        // pesanan tetap tersimpan dan pengguna bisa mencoba lagi dari halaman pembayaran.
        try {
            app(MidtransService::class)->createSnapTransaction($order);
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat transaksi Midtrans saat checkout', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('payment.show', $order)
                ->with('error', 'Pesanan berhasil dibuat, tetapi token pembayaran gagal dibuat. Silakan klik "Buat Pembayaran" untuk mencoba lagi.');
        }

        return redirect()
            ->route('payment.show', $order)
            ->with('success', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran.');
    }

    private function rememberGuestOrder(Order $order, Request $request): void
    {
        if ($request->user()) {
            return;
        }

        $guestOrders = session('guest_orders', []);
        $guestOrders[] = $order->id;
        session(['guest_orders' => array_values(array_unique($guestOrders))]);
    }

    private function redirectToExistingOrder(Order $order, Request $request)
    {
        if ($request->user() && (int) $order->user_id !== (int) $request->user()->id) {
            return back()->with('error', 'Token checkout tidak valid untuk akun ini.');
        }

        // Pastikan tamu tetap bisa membuka halaman pembayaran pesanannya.
        $this->rememberGuestOrder($order, $request);

        return redirect()
            ->route('payment.show', $order)
            ->with('success', 'Pesanan ini sudah dibuat sebelumnya.');
    }
}
