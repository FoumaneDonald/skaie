<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable(['user_id', 'otp', 'expires_at', 'used'])]
class EmailVerificationOtp extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used'       => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(string $otp): bool
    {
        return !$this->used && !$this->isExpired() && Hash::check($otp, $this->otp);
    }
}
