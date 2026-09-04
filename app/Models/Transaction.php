<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'instrument',
        'quantity',
        'price_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'quantity' => 'integer',
            'price_per_unit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Transactions are append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Transactions are append-only and cannot be deleted.');
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
