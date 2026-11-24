<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\StockService;
use Midtrans\Config;
use Midtrans\Snap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    private const LATE_PAYMENT_NOTE = 'Pembayaran diterima setelah pesanan kedaluwarsa/dibatalkan — perlu refund.';

    public function __construct(private readonly StockService $stockService)
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Logging tidak boleh menggagalkan pemrosesan webhook/pembayaran.
     * ( Mis. storage/logs tidak writable oleh user web server. )
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable $e) {
            // Sengaja diabaikan: status pembayaran lebih penting daripada log.
        }
    }

    /**
     * Membuat transaksi Snap Midtrans.
     */
    public function createSnapTransaction(Order $order): array
    {
        $order->load(['items.product', 'payment']);

        if ($order->isCashPayment()) {
            throw new \Exception('Pesanan dengan metode tunai tidak diproses melalui Midtrans.');
        }

        if ($order->payment_status === 'PAID') {
            throw new \Exception('Order sudah dibayar.');
        }

        if ($order->order_status === Order::STATUS_CANCELLED) {
            throw new \Exception('Order sudah dibatalkan.');
        }

        // Pakai kembali token yang masih berlaku agar midtrans_order_id tidak
        // berubah-ubah dan transaksi lama tidak menjadi yatim.
        $existingPayment = $order->payment;

        if (
            $existingPayment
            && $existingPayment->snap_token
            && $existingPayment->payment_status === 'PENDING'
            && (! $existingPayment->expires_at || $existingPayment->expires_at->isFuture())
        ) {
            return [
                'token' => $existingPayment->snap_token,
                'redirect_url' => $existingPayment->redirect_url,
            ];
        }

        $expiryMinutes = (int) config('services.midtrans.expiry_minutes', 15);

        // ID dibuat unik per percobaan, tetapi deterministik untuk order ini.
        $midtransOrderId = 'TRX-' . $order->id . '-' . now()->format('YmdHis');

        $itemDetails = [];
        $calculatedTotal = 0;

        foreach ($order->items as $item) {
            $itemName = $item->product->name ?? 'Produk Matcha';

            if ($item->variant) {
                $itemName .= ' - ' . $item->variant->variant_name;
            }

            $itemName = mb_strimwidth($itemName, 0, 50, '');

            $itemPrice = (int) $item->unit_price;
            $quantity = (int) $item->quantity;

            $itemDetails[] = [
                'id' => 'ITEM-' . $item->id,
                'price' => $itemPrice,
                'quantity' => $quantity,
                'name' => $itemName,
            ];

            $calculatedTotal += $itemPrice * $quantity;
        }

        if ($order->shipping_fee > 0) {
            $shippingFee = (int) $order->shipping_fee;

            $itemDetails[] = [
                'id' => 'ONGKIR',
                'price' => $shippingFee,
                'quantity' => 1,
                'name' => 'Biaya Pengiriman (Ongkir)',
            ];

            $calculatedTotal += $shippingFee;
        }

        $grossAmount = $calculatedTotal;

        if ($grossAmount <= 0) {
            throw new \Exception('Total pembayaran tidak valid.');
        }

        $params = [
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $grossAmount,
            ],

            'item_details' => $itemDetails,

            'customer_details' => [
                'first_name' => $order->customer_name,
                'phone' => $order->customer_phone,
            ],

            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit' => 'minute',
                'duration' => $expiryMinutes,
            ],

            'callbacks' => [
                'finish' => route('payment.show', $order),
            ],
        ];

        // Batasi kanal Snap sesuai metode yang dipilih pelanggan. Untuk data
        // lama (MIDTRANS) tidak dibatasi agar perilaku lama tetap berjalan.
        $enabledPayments = config('services.midtrans.enabled_payments.' . $order->payment_method);

        if (! empty($enabledPayments)) {
            $params['enabled_payments'] = array_values((array) $enabledPayments);
        }

        $snapResponse = Snap::createTransaction($params);

        $order->payment()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'provider' => 'midtrans',
                'midtrans_order_id' => $midtransOrderId,
                'gross_amount' => $grossAmount,
                'snap_token' => $snapResponse->token,
                'redirect_url' => $snapResponse->redirect_url,
                'transaction_status' => 'pending',
                'payment_status' => 'PENDING',
                'expires_at' => now()->addMinutes($expiryMinutes),
                'raw_response' => json_decode(
                    json_encode($snapResponse),
                    true
                ),
            ]
        );

        // Sinkronkan status order dengan baris pembayaran yang baru dibuat.
        if ($order->payment_status !== 'PAID') {
            $order->forceFill([
                'payment_status' => 'PENDING',
                'expires_at' => $order->expires_at ?? now()->addMinutes($expiryMinutes),
            ])->save();
        }

        return [
            'token' => $snapResponse->token,
            'redirect_url' => $snapResponse->redirect_url,
        ];
    }

    /**
     * Memverifikasi signature key dari Midtrans.
     */
    public function verifySignature(array $notification): bool
    {
        $orderId = $notification['order_id'] ?? '';
        $statusCode = $notification['status_code'] ?? '';
        $grossAmount = $notification['gross_amount'] ?? '';
        $incomingSignature = $notification['signature_key'] ?? '';

        $serverKey = config('services.midtrans.server_key');

        if (
            empty($orderId) ||
            empty($statusCode) ||
            empty($grossAmount) ||
            empty($incomingSignature) ||
            empty($serverKey)
        ) {
            return false;
        }

        $mySignature = hash(
            'sha512',
            $orderId . $statusCode . $grossAmount . $serverKey
        );

        return hash_equals($mySignature, $incomingSignature);
    }

    /**
     * Memetakan status transaksi Midtrans.
     */
    public function mapStatus(
        string $transactionStatus,
        ?string $fraudStatus = null
    ): array {
        $orderStatus = Order::STATUS_PENDING;
        $paymentStatus = 'PENDING';

        switch ($transactionStatus) {
            case 'capture':

                if ($fraudStatus === 'accept') {
                    $orderStatus = Order::STATUS_PROCESSING;
                    $paymentStatus = 'PAID';
                } elseif ($fraudStatus === 'challenge') {
                    $orderStatus = Order::STATUS_PENDING;
                    $paymentStatus = 'CHALLENGE';
                }

                break;

            case 'settlement':
                $orderStatus = Order::STATUS_PROCESSING;
                $paymentStatus = 'PAID';
                break;

            case 'pending':
                $orderStatus = Order::STATUS_PENDING;
                $paymentStatus = 'PENDING';
                break;

            case 'deny':
                $orderStatus = Order::STATUS_CANCELLED;
                $paymentStatus = 'DENIED';
                break;

            case 'cancel':
                $orderStatus = Order::STATUS_CANCELLED;
                $paymentStatus = 'CANCELLED';
                break;

            case 'expire':
                $orderStatus = Order::STATUS_CANCELLED;
                $paymentStatus = 'EXPIRED';
                break;

            case 'refund':
                $orderStatus = Order::STATUS_CANCELLED;
                $paymentStatus = 'REFUNDED';
                break;
        }

        return [
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * Ambil status resmi dari Midtrans. Dipisahkan supaya bisa di-mock di test
     * dan seluruh jalur webhook/polling memakai satu pintu yang sama.
     */
    public function fetchStatus(string $midtransOrderId): array
    {
        $response = \Midtrans\Transaction::status($midtransOrderId);

        return json_decode(json_encode($response), true) ?: [];
    }

    /**
     * Sinkronkan status pembayaran langsung dari Midtrans (Status API),
     * tanpa menunggu webhook. Dipakai oleh polling halaman pembayaran,
     * halaman riwayat, dan command rekonsiliasi.
     */
    public function syncFromMidtrans(Order $order): void
    {
        $payment = $order->payment;

        if (!$payment || !$payment->midtrans_order_id || $order->payment_status === 'PAID') {
            return;
        }

        $status = $this->fetchStatus($payment->midtrans_order_id);

        $this->safeLog('info', 'Midtrans Status API response', [
            'midtrans_order_id' => $payment->midtrans_order_id,
            'transaction_status' => $status['transaction_status'] ?? null,
            'status_code' => $status['status_code'] ?? null,
        ]);

        if (empty($status['transaction_status'])) {
            $this->safeLog('warning', 'Status API tidak mengembalikan transaction_status', [
                'midtrans_order_id' => $payment->midtrans_order_id,
                'status_code' => $status['status_code'] ?? null,
                'status_message' => $status['status_message'] ?? null,
            ]);

            return;
        }

        // Data berasal dari server Midtrans (bukan klien), jadi bisa diproses langsung.
        $this->applyStatus($payment, $status);
    }

    /**
     * Memproses notification webhook dari Midtrans.
     *
     * Melempar InvalidArgumentException untuk payload/order tidak valid (HTTP 400)
     * dan RuntimeException untuk kegagalan internal/Status API (HTTP 500 agar
     * Midtrans melakukan retry).
     */
    public function handleNotification(array $payload): bool
    {
        $this->safeLog('info', 'Midtrans Webhook Received', [
            'order_id' => $payload['order_id'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
        ]);

        // 1. Ambil data notification
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;

        // 2. Validasi kelengkapan payload
        if (
            !$orderId ||
            !$statusCode ||
            !$grossAmount ||
            !$transactionStatus ||
            empty($payload['signature_key'])
        ) {
            throw new \InvalidArgumentException('Incomplete Midtrans notification payload.');
        }

        // 3. Verifikasi signature. Jika tidak cocok, jangan langsung percaya payload:
        //    tanyakan status resmi ke Midtrans (Status API) memakai server key kita.
        if (!$this->verifySignature($payload)) {
            $this->safeLog('warning', 'Invalid Midtrans signature, verifying via Status API', [
                'order_id' => $orderId,
            ]);

            try {
                $verified = $this->fetchStatus((string) $orderId);
            } catch (\Throwable $e) {
                $this->safeLog('error', 'Midtrans Status API failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);

                throw new \RuntimeException('Gagal memverifikasi notifikasi ke Midtrans.', 0, $e);
            }

            $transactionStatus = $verified['transaction_status'] ?? null;
            $fraudStatus = $verified['fraud_status'] ?? null;
            $grossAmount = $verified['gross_amount'] ?? $grossAmount;
            $payload = array_merge($payload, $verified);

            if (!$transactionStatus) {
                throw new \RuntimeException('Status Midtrans tidak tersedia.');
            }
        }

        // 4. Cari pembayaran berdasarkan Midtrans order ID. Jika token sudah
        //    diganti, fallback ke ID order dari prefix TRX-{orderId}-{timestamp}.
        $payment = Payment::where('midtrans_order_id', $orderId)->first();

        if (!$payment && preg_match('/^TRX-(\d+)-/', (string) $orderId, $matches)) {
            $linkedOrder = Order::find((int) $matches[1]);
            $payment = $linkedOrder?->payment;
        }

        if (!$payment) {
            $this->safeLog('warning', 'Payment transaction not found', [
                'order_id' => $orderId,
            ]);

            throw new \InvalidArgumentException('Payment transaction not found.');
        }

        // 5. Pastikan order tersedia
        if (!$payment->order) {
            throw new \RuntimeException('Order associated with payment not found.');
        }

        // 6-8. Validasi nominal, pemetaan status, dan update database
        $this->applyStatus($payment, array_merge($payload, [
            'transaction_status' => $transactionStatus,
            'fraud_status' => $fraudStatus,
            'gross_amount' => $grossAmount,
        ]));

        return true;
    }

    /**
     * Terapkan status dari Midtrans ke payment & order.
     */
    public function applyStatus(Payment $payment, array $data): void
    {
        $orderId = $payment->midtrans_order_id;
        $transactionStatus = $data['transaction_status'];
        $fraudStatus = $data['fraud_status'] ?? null;
        $grossAmount = $data['gross_amount'] ?? $payment->gross_amount;
        $payload = $data;

        // 6. Validasi nominal pembayaran
        if ((int) $payment->gross_amount !== (int) $grossAmount) {
            throw new \InvalidArgumentException('Gross amount mismatch.');
        }

        // 7. Pemetaan status
        $mappedStatus = $this->mapStatus(
            $transactionStatus,
            $fraudStatus
        );

        // 8. Update database menggunakan transaction dan locking
        DB::transaction(function () use (
            $payment,
            $transactionStatus,
            $mappedStatus,
            $payload
        ) {
            $payment = Payment::where('id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::where('id', $payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Abaikan notifikasi basi: order yang sudah PAID tidak boleh
            // dikembalikan ke status non-PAID oleh notifikasi yang datang terlambat.
            if ($order->payment_status === 'PAID' && $mappedStatus['payment_status'] !== 'PAID') {
                return;
            }

            $orderStatus = $mappedStatus['order_status'];

            // Kebijakan "settlement terlambat": pembayaran yang masuk setelah
            // order dibatalkan/stok dikembalikan tetap dicatat LUNAS, tetapi order
            // tidak dihidupkan dan stok tidak dipotong ulang. Perlu refund admin.
            $lateSettlement = $order->order_status === Order::STATUS_CANCELLED
                && $order->stock_restored_at
                && $mappedStatus['payment_status'] === 'PAID';

            // Notifikasi non-pembayaran untuk order batal yang stoknya sudah
            // dikembalikan tetap diabaikan.
            if ($order->order_status === Order::STATUS_CANCELLED
                && $order->stock_restored_at
                && ! $lateSettlement) {
                return;
            }

            if ($lateSettlement) {
                $orderStatus = Order::STATUS_CANCELLED;
            }

            // Simpan detail transaksi Midtrans.
            $payment->transaction_status = $transactionStatus;
            $payment->payment_status = $mappedStatus['payment_status'];
            $payment->fraud_status = $payload['fraud_status'] ?? $payment->fraud_status;
            $payment->payment_type = $payload['payment_type'] ?? $payment->payment_type;

            if (
                ! empty($payload['transaction_id'])
                && ! Payment::where('transaction_id', $payload['transaction_id'])
                    ->where('id', '!=', $payment->id)
                    ->exists()
            ) {
                $payment->transaction_id = $payload['transaction_id'];
            }

            // Status order tidak boleh turun dari COMPLETED (mis. notifikasi
            // settlement duplikat yang datang setelah admin menyelesaikan order).
            if (
                $order->order_status === Order::STATUS_COMPLETED
                && $orderStatus !== Order::STATUS_COMPLETED
            ) {
                $orderStatus = Order::STATUS_COMPLETED;
            }

            // Simpan status order
            $order->order_status = $orderStatus;
            $order->payment_status = $mappedStatus['payment_status'];

            // Jika pembayaran berhasil
            if ($mappedStatus['payment_status'] === 'PAID') {
                $payment->paid_at = $payment->paid_at ?? now();
                $order->paid_at = $order->paid_at ?? now();
                $order->expires_at = null;
            }

            // Jika pembayaran dibatalkan atau kedaluwarsa, kembalikan stok.
            // StockService mengabaikan order lama yang tidak pernah memotong stok.
            if ($mappedStatus['order_status'] === Order::STATUS_CANCELLED) {
                $order->cancelled_at = $order->cancelled_at ?? now();
                $this->stockService->restoreForOrder($order);
            }

            if (
                $lateSettlement
                && ! str_contains((string) $order->notes, self::LATE_PAYMENT_NOTE)
            ) {
                $order->notes = trim(
                    (string) $order->notes . "\n" . now()->format('Y-m-d H:i:s') . ' — ' . self::LATE_PAYMENT_NOTE
                );
            }

            // Simpan raw notification
            $payment->raw_response = $payload;

            $payment->save();
            $order->save();
        });

        $this->safeLog('info', 'Midtrans notification processed successfully', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'payment_status' => $mappedStatus['payment_status'],
        ]);
    }
}
