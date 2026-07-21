<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'reseller_id', 'sales_id', 'collector_id',
        'invoice_date', 'due_date', 'subtotal', 'discount_total', 'ppn_total', 'bhp_total',
        'uso_total', 'grand_total', 'paid_amount', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'ppn_total' => 'decimal:2',
            'bhp_total' => 'decimal:2',
            'uso_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function refreshStatus(): void
    {
        $confirmedPaid = $this->payments()->where('status', 'confirmed')->sum('amount');
        $this->paid_amount = $confirmedPaid;

        if ($confirmedPaid <= 0) {
            $this->status = now()->toDateString() > $this->due_date->toDateString() ? 'overdue' : 'belum_lunas';
        } elseif ($confirmedPaid >= $this->grand_total) {
            $this->status = 'lunas';
        } else {
            $this->status = 'cicilan';
        }

        $this->save();
    }
}
