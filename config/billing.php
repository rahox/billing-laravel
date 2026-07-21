<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tarif Pajak & Pungutan Regulasi Telekomunikasi
    |--------------------------------------------------------------------------
    | PPN mengacu UU HPP (11% berlaku sejak 2025, ditinjau berkala oleh pemerintah).
    | BHP (Biaya Hak Penyelenggaraan) & USO (Universal Service Obligation) adalah
    | pungutan wajib bagi penyelenggara jasa telekomunikasi/internet, dihitung dari
    | nominal transaksi (pendapatan kotor jasa), dibebankan sebagai beban perusahaan
    | (bukan ditagihkan ke pelanggan).
    */
    'ppn_rate' => (float) env('BILLING_PPN_RATE', 0.11),
    'bhp_rate' => (float) env('BILLING_BHP_RATE', 0.0025),
    'uso_rate' => (float) env('BILLING_USO_RATE', 0.0125),

    /*
    |--------------------------------------------------------------------------
    | Pemetaan Chart of Accounts default
    |--------------------------------------------------------------------------
    */
    'accounts' => [
        'kas' => '1-1000',
        'bank' => '1-1010',
        'piutang_usaha' => '1-1100',
        'persediaan_perangkat' => '1-1200',
        'peralatan_jaringan' => '1-1500',

        'utang_usaha' => '2-1000',
        'ppn_keluaran' => '2-1100',
        'utang_bhp' => '2-1200',
        'utang_uso' => '2-1300',
        'komisi_terutang' => '2-1400',

        'modal_pemilik' => '3-1000',

        'pendapatan_jasa_internet' => '4-1000',
        'pendapatan_penjualan_perangkat' => '4-1100',
        'pendapatan_jasa_lainnya' => '4-1200',
        'diskon_penjualan' => '4-9000',

        'hpp_perangkat' => '5-1000',
        'hpp_jasa_internet' => '5-1010',
        'beban_bhp' => '5-2800',
        'beban_uso' => '5-2900',
        'beban_komisi' => '5-9000',

        'expense_category_map' => [
            'bandwidth_upstream' => '5-1010',
            'sewa_tower_kolokasi' => '5-2000',
            'listrik' => '5-2100',
            'gaji_karyawan' => '5-2200',
            'perangkat_jaringan' => '1-1500', // kapitalisasi aset
            'sewa_kantor' => '5-2400',
            'internet_kantor' => '5-2500',
            'transportasi_operasional' => '5-2600',
            'pemeliharaan_jaringan' => '5-2300',
            'marketing' => '5-2700',
            'lainnya' => '5-9900',
        ],
    ],
];
