<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Chart of Accounts standar untuk ISP, mengacu pola akuntansi umum Indonesia (PSAK).
     * Format: [code, name, type, normal_balance]
     */
    public function run(): void
    {
        $accounts = [
            // ASET
            ['1-1000', 'Kas', 'asset', 'debit'],
            ['1-1010', 'Bank', 'asset', 'debit'],
            ['1-1100', 'Piutang Usaha', 'asset', 'debit'],
            ['1-1200', 'Persediaan Perangkat', 'asset', 'debit'],
            ['1-1500', 'Peralatan Jaringan', 'asset', 'debit'],
            ['1-1510', 'Akumulasi Penyusutan Peralatan Jaringan', 'asset', 'credit'],

            // KEWAJIBAN
            ['2-1000', 'Utang Usaha', 'liability', 'credit'],
            ['2-1100', 'PPN Keluaran', 'liability', 'credit'],
            ['2-1200', 'Utang BHP Telekomunikasi', 'liability', 'credit'],
            ['2-1300', 'Utang USO', 'liability', 'credit'],
            ['2-1400', 'Komisi Sales Terutang', 'liability', 'credit'],
            ['2-1500', 'Pendapatan Diterima Dimuka', 'liability', 'credit'],

            // EKUITAS
            ['3-1000', 'Modal Pemilik', 'equity', 'credit'],
            ['3-2000', 'Laba Ditahan', 'equity', 'credit'],

            // PENDAPATAN
            ['4-1000', 'Pendapatan Jasa Internet', 'revenue', 'credit'],
            ['4-1100', 'Pendapatan Penjualan Perangkat', 'revenue', 'credit'],
            ['4-1200', 'Pendapatan Jasa Lainnya', 'revenue', 'credit'],
            ['4-9000', 'Diskon Penjualan', 'revenue', 'debit'],

            // BEBAN POKOK & OPERASIONAL
            ['5-1000', 'Beban Pokok Penjualan Perangkat', 'expense', 'debit'],
            ['5-1010', 'Beban Pokok Jasa Internet (Bandwidth Upstream)', 'expense', 'debit'],
            ['5-2000', 'Beban Sewa Tower/Kolokasi', 'expense', 'debit'],
            ['5-2100', 'Beban Listrik', 'expense', 'debit'],
            ['5-2200', 'Beban Gaji Karyawan', 'expense', 'debit'],
            ['5-2300', 'Beban Pemeliharaan Jaringan', 'expense', 'debit'],
            ['5-2400', 'Beban Sewa Kantor', 'expense', 'debit'],
            ['5-2500', 'Beban Internet Kantor', 'expense', 'debit'],
            ['5-2600', 'Beban Transportasi Operasional', 'expense', 'debit'],
            ['5-2700', 'Beban Marketing', 'expense', 'debit'],
            ['5-2800', 'Beban BHP Telekomunikasi', 'expense', 'debit'],
            ['5-2900', 'Beban USO (Universal Service Obligation)', 'expense', 'debit'],
            ['5-9000', 'Beban Komisi Sales', 'expense', 'debit'],
            ['5-9900', 'Beban Lain-lain', 'expense', 'debit'],
        ];

        foreach ($accounts as [$code, $name, $type, $normalBalance]) {
            ChartOfAccount::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $normalBalance, 'is_active' => true]
            );
        }
    }
}
