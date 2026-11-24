<?php

namespace Database\Seeders;

use App\Models\LowStockAlert;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed data mengikuti skema baru Matcha API.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('payments')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('low_stock_alerts')->truncate();
        DB::table('product_variants')->truncate();
        DB::table('products')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 1. Users
        $users = collect([
            ['name' => 'Admin Matcha', 'email' => 'admin@matcha.com', 'password' => bcrypt('admin123'), 'role' => 'ADMIN'],
            ['name' => 'Achmad Fauzan', 'email' => 'fauzan@gmail.com', 'password' => bcrypt('password123'), 'role' => 'CUSTOMER'],
            ['name' => 'Vanka Rahma', 'email' => 'vanka@gmail.com', 'password' => bcrypt('password123'), 'role' => 'CUSTOMER'],
            ['name' => 'Muhammad Arfian', 'email' => 'arfian@gmail.com', 'password' => bcrypt('password123'), 'role' => 'CUSTOMER'],
            ['name' => 'Siti Aminah', 'email' => 'siti@gmail.com', 'password' => bcrypt('password123'), 'role' => 'CUSTOMER'],
        ])->map(fn (array $data) => User::create($data));

        // 2. Menu Matcha and Me (14 menu) + varian ukuran + low stock alert
        // Foto diambil otomatis dari public/images/menu sesuai nama menu (image_url dikosongkan).
        $menu = [
            ['Original Usucha', 10000, 'Original', 'latte', 'Americano-nya matcha. Pahit alami karena hanya pure matcha dan air. Tanpa gula dan susu.'],
            ['Signature Authentic Matcha Latte', 15000, 'Signature', 'latte', 'Pure matcha di-cold whisk pakai susu dan gula (optional). Creamy, manis, umami dengan hint pahit.'],
            ['Signature Cheezy Matcha Latte', 18000, 'Signature', 'milk', 'Matcha latte dengan signature cheezy cream.'],
            ['Coffe Dirty Matcha', 17000, 'Coffee & Matcha', 'choco', 'Matcha latte ditabrak dengan coffee-shot blend Arabica dan Robusta khas Mandailing.'],
            ['Creamy Pink Matcha', 18000, 'Creamy', 'berry', 'Matcha latte dengan pink cream bunga Sakura yang floral aromatic.'],
            ['Creamy Ubee Matcha', 18000, 'Creamy', 'latte', 'Matcha latte dengan manis dan creamy-nya ubee.'],
            ['Creamy Choco Matcha', 18000, 'Creamy', 'choco', 'Matcha latte dengan rich chocolate.'],
            ['Refreshing Ichigo Matcha', 18000, 'Refreshing', 'berry', 'Matcha latte dengan selai buah strawberry asli yang asem, manis dan segar.'],
            ['Refreshing Pistacio Matcha', 18000, 'Refreshing', 'milk', 'Perpaduan creamy pistachio dan matcha autentik, menghadirkan rasa yang lembut, nutty, dan berkelas.'],
            ['Non Matcha Strawberry Milk', 15000, 'Non Matcha', 'berry', 'Strawberry Milk dibuat dari selai stroberi pilihan yang menghasilkan rasa creamy dan menyegarkan.'],
            ['Non Matcha Ubee Milk', 13000, 'Non Matcha', 'berry', 'Ube Milk dengan cita rasa ubi ungu yang khas, creamy, manis, dan lembut di setiap tegukan.'],
            ['Non Matcha Coklat Milk', 13000, 'Non Matcha', 'choco', 'Perpaduan cokelat dan susu yang creamy, dengan sensasi cokelat yang hangat dan akrab.'],
            ['Non Matcha Kopi Gula Aren', 10000, 'Non Matcha', 'choco', 'Coffee dengan sentuhan gula aren. Manisnya lembut, pahitnya seimbang, dengan aroma caramel yang bikin nagih.'],
            ['Special Series Brown Sugar Matcha', 17000, 'Special Series', 'choco', 'Perpaduan gula aren dan matcha autentik, menghadirkan rasa yang lembut dan berkelas.'],
        ];

        // Ukuran minuman (sama dengan FE): S +0, M +3.000, L +6.000
        $sizes = [
            ['variant_name' => 'Size S', 'additional_price' => 0, 'stock' => 50],
            ['variant_name' => 'Size M', 'additional_price' => 3000, 'stock' => 40],
            ['variant_name' => 'Size L', 'additional_price' => 6000, 'stock' => 30],
        ];
        $minThreshold = 10;

        $createdProducts = collect($menu)->map(function (array $row) use ($sizes, $minThreshold) {
            [$name, $price, $tag, $tone, $description] = $row;

            $product = Product::create([
                'name' => $name,
                'description' => $description,
                'base_price' => $price,
                'cost_price' => 0, // isi harga modal lewat Admin > Produk > Edit
                'image_url' => null,
                'is_active' => true,
            ]);

            // tag & tone di luar $fillable model, jadi diisi lewat forceFill
            $product->forceFill(['tag' => $tag, 'tone' => $tone])->save();

            $product->variants()->createMany($sizes);

            LowStockAlert::create([
                'product_id' => $product->id,
                'min_threshold' => $minThreshold,
                'is_notified' => collect($sizes)->contains(fn ($v) => $v['stock'] < $minThreshold),
            ]);

            return $product->load('variants');
        });

        // 3. Orders dummy dengan skema baru & Payment dummy
        $customers = $users->where('role', 'CUSTOMER')->values();
        $paymentMethods = [
            Order::PAYMENT_METHOD_CASH,
            Order::PAYMENT_METHOD_QRIS,
            Order::PAYMENT_METHOD_BANK_TRANSFER,
            Order::PAYMENT_METHOD_CASH,
            Order::PAYMENT_METHOD_QRIS,
        ];

        for ($i = 0; $i < 5; $i++) {
            $customer = $customers[$i % $customers->count()];
            $product = $createdProducts[$i % $createdProducts->count()];
            $variant = $product->variants->first();

            $unitPrice = (float) $product->base_price + (float) $variant->additional_price;
            $quantity = 2;
            $subtotal = $unitPrice * $quantity;
            $shippingFee = ($i % 2 === 0) ? 10000 : 0; // Kombinasi antar / ambil
            $deliveryType = ($shippingFee > 0) ? 'antar' : 'ambil';
            $totalAmount = $subtotal + $shippingFee;

            $paymentMethod = $paymentMethods[$i % count($paymentMethods)];
            $isCash = $paymentMethod === Order::PAYMENT_METHOD_CASH;

            // Variasi status: tunai belum lunas, tunai lunas, dan QRIS selesai.
            $isCompleted = ($i === 4);
            $isPaid = $isCompleted || ($isCash && $i === 3);
            $orderStatus = $isCompleted
                ? Order::STATUS_COMPLETED
                : (($isCash || $isPaid) ? Order::STATUS_PROCESSING : Order::STATUS_PENDING);
            $paymentStatus = $isPaid ? 'PAID' : 'UNPAID';
            $expiresAt = (! $isCash && ! $isPaid) ? now()->addMinutes(15) : null;

            $order = Order::create([
                'user_id' => $customer->id,
                'order_number' => 'MATCHA-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'customer_name' => $customer->name,
                'customer_phone' => '08123456789' . $i,
                'shipping_address' => $deliveryType === 'antar' ? 'Jl. Melati No. ' . ($i + 1) . ', Malang, Jawa Timur' : 'Ambil di tempat (Dine-in)',
                'shipping_fee' => $shippingFee,
                'subtotal' => (int) $subtotal,
                'discount_amount' => 0,
                'total_amount' => $totalAmount,
                'delivery_type' => $deliveryType,
                'payment_method' => $paymentMethod,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'paid_at' => $isPaid ? now() : null,
                'expires_at' => $expiresAt,
                'completed_at' => $isCompleted ? now() : null,
                'notes' => 'Catatan pesanan ke-' . ($i + 1),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'sweetness' => '50%',
                'note' => 'Less ice',
                'unit_price' => $unitPrice,
            ]);

            // Pesanan tunai tidak memakai baris pembayaran Midtrans.
            if ($isCash) {
                continue;
            }

            Payment::create([
                'order_id' => $order->id,
                'provider' => 'midtrans',
                'midtrans_order_id' => 'MID-' . strtoupper(Str::random(8)),
                'transaction_id' => $isPaid ? 'TRX-' . rand(100000, 999999) : null,
                'transaction_status' => $isPaid ? 'settlement' : 'pending',
                'fraud_status' => $isPaid ? 'accept' : null,
                'payment_type' => $paymentMethod === Order::PAYMENT_METHOD_QRIS ? 'qris' : 'bank_transfer',
                'payment_status' => $isPaid ? 'PAID' : 'PENDING',
                'gross_amount' => $totalAmount,
                'snap_token' => 'dummy_snap_token_' . Str::random(10),
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/dummy',
                'raw_response' => json_encode(['status_message' => 'Simulated midtrans response', 'status' => $paymentStatus]),
                'paid_at' => $isPaid ? now() : null,
                'expires_at' => $isPaid ? null : now()->addMinutes(15),
            ]);
        }
    }
}