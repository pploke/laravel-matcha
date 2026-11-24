<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_price',
        'cost_price',
        'weight_grams',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'cost_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function lowStockAlert(): HasOne
    {
        return $this->hasOne(LowStockAlert::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Ilustrasi pengganti kalau URL gambar masih placeholder (example.com). */
    public function getImageAttribute(): ?string
    {
        if (! $this->image_url) {
            return null;
        }

        $host = parse_url($this->image_url, PHP_URL_HOST);

        if (! $host || $host === 'example.com' || str_ends_with($host, '.example.com')) {
            return null;
        }

        return $this->image_url;
    }

    /** Warna ilustrasi gelas matcha (mengikuti palet FE lama). */
    public function getToneAttribute(): string
    {
        $name = strtolower($this->name);

        return match (true) {
            str_contains($name, 'strawberry'), str_contains($name, 'berry') => 'berry',
            str_contains($name, 'choco'), str_contains($name, 'espresso') => 'choco',
            str_contains($name, 'cheese'), str_contains($name, 'milk') => 'milk',
            default => 'latte',
        };
    }

    /** Label kecil di kartu produk. */
    public function getTagAttribute(): string
    {
        $count = $this->variants_count ?? $this->variants()->count();

        return $count > 1 ? "{$count} varian" : 'Tersedia';
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->sum('stock');
    }
}
