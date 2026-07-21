# Billing ISP

Sistem billing/tagihan untuk ISP: manajemen pelanggan, produk jasa/barang, transaksi, invoice,
pembayaran (termasuk cicilan), komisi sales, dan akuntansi (jurnal, PPN, BHP, USO, Laba Rugi, Neraca).

Backend: Laravel 13 (API + session auth via Sanctum, Spatie Permission untuk role).
Frontend: [Materio Vuetify Vue.js Admin Template](https://github.com/themeselection/materio-vuetify-vuejs-laravel-admin-template-free) (MIT, lihat `LICENSE-materio-theme`) sebagai SPA yang disajikan langsung oleh Laravel.

## Role

| Role | Akses |
|---|---|
| `super-admin` | Semua data & laporan, kelola user, produk, diskon, beban, Laba Rugi, Neraca |
| `reseller` | Kelola pelanggan/sales miliknya, buat transaksi & invoice, laporan transaksi |
| `sales` | Dashboard & laporan komisi (flat/persentase) atas pelanggan yang ditangani |
| `collector` | Menagih pelanggan, mencatat & mengonfirmasi pembayaran |

## Setup

```bash
composer install
npm install
cp .env.example .env   # jika belum ada .env
php artisan key:generate
php artisan migrate --seed   # jalankan migrasi + data dummy (Jan 2026 - sekarang)
npm run build                 # atau `npm run dev` untuk mode pengembangan
php artisan serve
```

Database default: SQLite (`database/database.sqlite`), tinggal ganti `DB_CONNECTION` di `.env` untuk MySQL/PostgreSQL di produksi.

## Akun demo (password semua: `password`)

- Owner (super-admin): `owner@bill-isp.test`
- Reseller: `reseller1@bill-isp.test`, `reseller2@bill-isp.test`, `reseller3@bill-isp.test`
- Sales: `sales01@bill-isp.test`, `sales02@bill-isp.test`, `sales11@bill-isp.test`, `sales12@bill-isp.test`, `sales21@bill-isp.test`, `sales22@bill-isp.test` (komisi flat/persentase campuran per reseller)
- Collector: `collector1@bill-isp.test`, `collector2@bill-isp.test`

## Alur bisnis

1. **Transaksi** — item tagihan per pelanggan (produk jasa/barang, qty, diskon opsional, PPN/BHP/USO otomatis).
2. **Invoice** — beberapa transaksi pelanggan yang sama digabung jadi satu invoice.
3. **Pembayaran** — dicatat & dikonfirmasi collector; mendukung pelunasan penuh maupun cicilan (invoice: `belum_lunas` / `cicilan` / `lunas` / `overdue`).
4. **Jurnal otomatis** — setiap invoice, pembayaran, beban, dan komisi memposting jurnal double-entry ke Chart of Accounts standar Indonesia (lihat `database/seeders/ChartOfAccountSeeder.php`).
5. **Pajak & pungutan** — PPN 11% ditagihkan ke pelanggan; BHP 0,25% & USO 1,25% dari nominal transaksi dicatat sebagai beban & utang ke regulator (tidak ditagihkan ke pelanggan), lihat `config/billing.php`.
6. **Komisi sales** — dihitung otomatis saat pembayaran dikonfirmasi (flat sekali per invoice, atau persentase per pembayaran).
7. **Laporan** — transaksi (owner/reseller), komisi (sales), penagihan (collector), Laba Rugi & Neraca (owner) — lihat `app/Services/Reports/`.

## Struktur kode penting

- `app/Models` — entitas domain (Customer, Product, Discount, Transaction, Invoice, Payment, ChartOfAccount, JournalEntry, Commission, Expense).
- `app/Services/Billing` — logika harga/diskon/pajak, pembentukan invoice, pembayaran, komisi.
- `app/Services/Accounting/JournalPostingService.php` — mesin jurnal double-entry.
- `app/Services/Reports` — Laba Rugi, Neraca, laporan transaksi/komisi/penagihan.
- `app/Http/Controllers/Api` — REST API yang dikonsumsi frontend.
- `resources/js` — SPA Vue 3 + Vuetify 3 (tema Materio), lihat `resources/js/pages` untuk semua halaman.
- `database/seeders/DemoDataSeeder.php` — generator data dummy pelanggan/transaksi/pembayaran/beban sejak 1 Jan 2026.
