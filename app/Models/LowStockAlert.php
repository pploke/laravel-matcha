<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LowStockAlert extends Model
{
    protected $fillable = [
        'product_id',
        'min_threshold',
        'is_notified',
    ];

    protected function casts(): array
    {
        return [
            'min_threshold' => 'integer',
            'is_notified' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
