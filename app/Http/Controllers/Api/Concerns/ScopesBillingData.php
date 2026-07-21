<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopesBillingData
{
    /**
     * Batasi query customer/invoice/transaksi sesuai peran user:
     * super-admin melihat semua, reseller hanya miliknya, sales/collector hanya yang ditugaskan.
     */
    private function scopeByRole(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin')) {
            return $query;
        }
        if ($user->hasRole('reseller')) {
            return $query->where('reseller_id', $user->id);
        }
        if ($user->hasRole('sales')) {
            return $query->where('sales_id', $user->id);
        }
        if ($user->hasRole('collector')) {
            return $query->where('collector_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
