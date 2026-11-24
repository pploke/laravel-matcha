<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING_PAYMENT';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Pembayaran',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    public const PAYMENT_METHOD_CASH = 'CASH';

    public const PAYMENT_METHOD_QRIS = 'QRIS';

    public const PAYMENT_METHOD_BANK_TRANSFER = 'BANK_TRANSFER';

    // Metode lama: pesanan sebelum fitur pilihan metode pembayaran.
    public const PAYMENT_METHOD_MIDTRANS = 'MIDTRANS';

    public const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CASH => 'Tunai',
        self::PAYMENT_METHOD_QRIS => 'QRIS',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Transfer Bank',
        self::PAYMENT_METHOD_MIDTRANS => 'Midtrans',
    ];

    public const PAYMENT_METHOD_ICONS = [
        self::PAYMENT_METHOD_CASH => 'bi-cash-coin',
        self::PAYMENT_METHOD_QRIS => 'bi-qr-code',
        self::PAYMENT_METHOD_BANK_TRANSFER => 'bi-bank',
        self::PAYMENT_METHOD_MIDTRANS => 'bi-credit-card',
    ];

    /** Metode yang dibayar langsung di gerai (tanpa Midtrans). */
    public const OFFLINE_PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
    ];

    /** Metode yang diproses melalui Midtrans Snap. */
    public const ONLINE_PAYMENT_METHODS = [
        self::PAYMENT_METHOD_QRIS,
        self::PAYMENT_METHOD_BANK_TRANSFER,
        self::PAYMENT_METHOD_MIDTRANS,
    ];

    public const PAYMENT_STATUS_LABELS = [
        'PAID' => 'Lunas',
        'PENDING' => 'Menunggu Pembayaran',
        'UNPAID' => 'Belum Dibayar',
        'CHALLENGE' => 'Ditinjau',
        'EXPIRED' => 'Kedaluwarsa',
        'CANCELLED' => 'Dibatalkan',
        'DENIED' => 'Ditolak',
        'REFUNDED' => 'Dikembalikan',
    ];

    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'shipping_address',
        'shipping_fee',
        'subtotal',
        'discount_amount',
        'total_amount',
        'delivery_type',
        'payment_method',
        'order_status',
        'payment_status',
        'paid_at',
        'expires_at',
        'completed_at',
        'cancelled_at',
        'notes',
        'checkout_token',
        'shipping_courier',
        'shipping_service',
        'shipping_etd',
        'shipping_destination_id',
        'shipping_destination_label',
        'shipping_weight_grams',
        'tracking_number',
        'shipping_postal_code',
        'stock_deducted_at',
        'stock_restored_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'integer',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_deducted_at' => 'datetime',
            'stock_restored_at' => 'datetime',
            'shipping_weight_grams' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->order_status] ?? $this->order_status;
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method]
            ?? self::PAYMENT_METHOD_LABELS[self::PAYMENT_METHOD_MIDTRANS];
    }

    public function getPaymentMethodIconAttribute(): string
    {
        return self::PAYMENT_METHOD_ICONS[$this->payment_method]
            ?? self::PAYMENT_METHOD_ICONS[self::PAYMENT_METHOD_MIDTRANS];
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status]
            ?? strtoupper((string) $this->payment_status);
    }

    /** True jika pesanan dibayar tunai langsung di gerai. */
    public function isCashPayment(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_CASH;
    }

    /** True jika pesanan memakai Midtrans (QRIS, transfer bank, atau data lama). */
    public function usesMidtrans(): bool
    {
        return ! $this->isCashPayment();
    }

    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function getDeliveryTypeAttribute($value): string
    {
        // Mengambil langsung dari kolom database, dengan fallback aman jika kosong
        return $value ?? 'ambil';
    }

    /** Kode tampilan pesanan, mis. #MB-00012. */
    public function getCodeAttribute(): string
    {
        return '#MB-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
