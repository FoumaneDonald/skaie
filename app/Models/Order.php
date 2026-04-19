<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'shipping_name',
        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'shipping_phone',
        'subtotal',
        'shipping_fee',
        'total',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'     => 'float',
            'shipping_fee' => 'float',
            'total'        => 'float',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

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

    // ── Helpers ──────────────────────────────────────────────────

    public function isPending(): bool     { return $this->status === 'pending'; }
    public function isCancelled(): bool   { return $this->status === 'cancelled'; }
    public function isPaid(): bool
    {
        return $this->payment?->status === 'succeeded';
    }

    /**
     * Snapshot de l'adresse de livraison depuis un modèle Address.
     */
    public static function snapshotAddress(Address $address): array
    {
        return [
            'shipping_name'    => $address->label,
            'shipping_street'  => $address->street_line_1
                . ($address->street_line_2 ? ', ' . $address->street_line_2 : ''),
            'shipping_city'    => $address->city,
            'shipping_state'   => $address->state,
            'shipping_zip'     => $address->zip,
            'shipping_country' => $address->country ?? 'CM',
            'shipping_phone'   => $address->phone,
        ];
    }
}
