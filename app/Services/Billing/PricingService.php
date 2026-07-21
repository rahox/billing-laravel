<?php

namespace App\Services\Billing;

use App\Models\Discount;
use App\Models\Product;

class PricingService
{
    /**
     * Hitung rincian harga satu baris transaksi: diskon, PPN, BHP, USO, dan total tagihan.
     *
     * @return array{qty:int, unit_price:float, discount_amount:float, subtotal:float, ppn_amount:float, bhp_amount:float, uso_amount:float, total:float}
     */
    public function calculateLine(Product $product, int $qty, ?Discount $discount = null, array $discountContext = []): array
    {
        $unitPrice = (float) $product->price;
        $gross = $unitPrice * $qty;

        $discountAmount = $discount ? $discount->calculateAmount($gross, $discountContext) : 0.0;
        $subtotal = round($gross - $discountAmount, 2);

        $ppnAmount = $product->is_ppn_applicable
            ? round($subtotal * config('billing.ppn_rate'), 2)
            : 0.0;

        $bhpAmount = $product->is_telco_levy_applicable
            ? round($subtotal * config('billing.bhp_rate'), 2)
            : 0.0;

        $usoAmount = $product->is_telco_levy_applicable
            ? round($subtotal * config('billing.uso_rate'), 2)
            : 0.0;

        return [
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'subtotal' => $subtotal,
            'ppn_amount' => $ppnAmount,
            'bhp_amount' => $bhpAmount,
            'uso_amount' => $usoAmount,
            // BHP & USO adalah beban internal perusahaan ke regulator, bukan ditagihkan ke pelanggan.
            'total' => round($subtotal + $ppnAmount, 2),
        ];
    }

    /**
     * Konteks proration aktivasi awal: dari total hari 1 periode billing, berapa hari yang
     * benar-benar dinikmati pelanggan sejak tanggal aktivasi sampai akhir periode.
     * Sisanya (hari sebelum aktivasi) menjadi nilai diskon prorate.
     */
    public function activationProrationContext(\DateTimeInterface $periodStart, \DateTimeInterface $periodEnd, \DateTimeInterface $activationDate): array
    {
        $daysInPeriod = (int) \DateTime::createFromInterface($periodStart)->diff($periodEnd)->days + 1;
        $daysUsed = (int) \DateTime::createFromInterface($activationDate)->diff($periodEnd)->days + 1;

        return [
            'days_in_period' => max(1, $daysInPeriod),
            'days_used' => max(0, min($daysInPeriod, $daysUsed)),
        ];
    }
}
