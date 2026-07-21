<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'parent_reseller_id', 'commission_type', 'commission_value', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'commission_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function parentReseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_reseller_id');
    }

    public function salesTeam(): HasMany
    {
        return $this->hasMany(User::class, 'parent_reseller_id');
    }

    public function customersAsReseller(): HasMany
    {
        return $this->hasMany(Customer::class, 'reseller_id');
    }

    public function customersAsSales(): HasMany
    {
        return $this->hasMany(Customer::class, 'sales_id');
    }

    public function customersAsCollector(): HasMany
    {
        return $this->hasMany(Customer::class, 'collector_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'sales_id');
    }
}
