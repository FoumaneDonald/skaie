<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'stripe_payment_intent_id',
        'stripe_client_secret',
        'amount',
        'currency',
        'status',
        'failure_message',
        'stripe_metadata',
        'paid_at',
    ];

    protected $hidden = [
        'stripe_client_secret', // ne jamais exposer sauf lors de la création
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'float',
            'stripe_metadata' => 'array',
            'paid_at'         => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function isSucceeded(): bool  { return $this->status === 'succeeded'; }
    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }
}
