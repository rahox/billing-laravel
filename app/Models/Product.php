<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'price', 'cost_price', 'is_recurring', 'recurring_period',
        'is_ppn_applicable', 'is_telco_levy_applicable', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_recurring' => 'boolean',
            'is_ppn_applicable' => 'boolean',
            'is_telco_levy_applicable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
