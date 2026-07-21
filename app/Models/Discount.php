<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    protected $fillable = ['name', 'type', 'mode', 'value', 'is_active', 'description'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Hitung nominal diskon untuk sebuah subtotal.
     * Untuk mode prorate_activation, $context berisi ['days_used' => int, 'days_in_period' => int].
     */
    public function calculateAmount(float $subtotal, array $context = []): float
    {
        if ($this->mode === 'prorate_activation') {
            $daysInPeriod = max(1, (int) ($context['days_in_period'] ?? 30));
            $daysUsed = min($daysInPeriod, max(0, (int) ($context['days_used'] ?? 0)));
            $unusedRatio = ($daysInPeriod - $daysUsed) / $daysInPeriod;

            return round($subtotal * $unusedRatio, 2);
        }

        if ($this->type === 'percentage') {
            return round($subtotal * ((float) $this->value / 100), 2);
        }

        return min($subtotal, (float) $this->value);
    }
}
