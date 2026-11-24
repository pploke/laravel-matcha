<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const CATEGORIES = [
        'Bahan Baku',
        'Kemasan',
        'Gaji',
        'Listrik & Air',
        'Sewa',
        'Peralatan',
        'Transportasi',
        'Pemasaran',
        'Lainnya',
    ];

    protected $fillable = [
        'title',
        'category',
        'amount',
        'expense_date',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}