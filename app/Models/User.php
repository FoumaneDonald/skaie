<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'avatar', 'date_of_birth'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

        // Role helpers
    public function isCustomer(): bool    { return $this->role === 'customer'; }
    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isSuperAdmin(): bool  { return $this->role === 'super_admin'; }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    // Role hierarchy: super_admin > admin > customer
    public function hasRoleOrHigher(string $role): bool
    {
        $hierarchy = ['customer' => 1, 'admin' => 2, 'super_admin' => 3];
        return ($hierarchy[$this->role] ?? 0) >= ($hierarchy[$role] ?? 0);
    }

    // Add relationship
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }
}
