<?php

namespace Database\Seeders;

use App\Models\WebPage;
use App\Services\Referral\ReferralService;
use Illuminate\Database\Seeder;

/**
 * Halaman ajakan Affiliate fe-konexa (/page/affiliate, footer kolom
 * "Bantuan"). Aturan lengkapnya tetap di dokumen "Program Referral"
 * (LegalDocumentSeeder). Angka komisi & masa tahan diambil dari pengaturan
 * referral yang berlaku SAAT seeding (ReferralService::setting) -- kalau
 * pengaturannya diubah, edit juga halaman ini lewat Superadmin > Web > Halaman.
 *
 * Aman dijalankan ulang: hanya membuat kalau slug belum ada.
 * Jalankan khusus: php artisan db:seed --class=AffiliatePageSeeder
 */
class AffiliatePageSeeder extends Seeder
{
    private const SLUG = 'affiliate';

    /** Sama dengan validasi min di Wallet\WalletWithdrawalController. */
    private const MIN_WITHDRAWAL = 10000;

    public function run(): void
    {
        $summary = 'Ajak usaha lain memakai '.config('app.name').' dan dapatkan komisi berulang dari setiap pembelian mereka.';

        WebPage::firstOrCreate(['slug' => self::SLUG], [
            'title' => 'Program Affiliate',
            'type' => 'document',
            'subtitle' => $summary,
            'content' => $this->content(),
            'meta_description' => $summary,
            'show_in_footer' => true,
            'footer_group' => 'Bantuan',
            'footer_order' => 1,
            'status' => 'active',
        ]);
    }

    private function content(): string
    {
        $app = config('app.name');
        $percent = rtrim(rtrim(number_format(ReferralService::setting('referral_commission_percent'), 2, ',', '.'), '0'), ',');
        $holdDays = (int) ReferralService::setting('referral_hold_days');
        $rupiah = fn (float $amount) => 'Rp'.number_format($amount, 0, ',', '.');
        $example = 5 * 300000 * ReferralService::setting('referral_commission_percent') / 100;
        $registerUrl = route('register');

        return <<<MD
Punya jaringan pemilik usaha, sekolah, komunitas, atau klien? Rekomendasikan {$app} kepada mereka dan dapatkan komisi setiap kali mereka berlangganan.

## 1. Keuntungan Jadi Affiliate

- **Komisi {$percent}%** dari setiap pembelian dan perpanjangan paket.
- **Komisi berulang**, bukan sekali saja. Selama pelanggan Anda terus berlangganan, komisi terus masuk.
- **Pelanggan terhubung permanen** ke Anda sejak pembelian pertama.
- **Gratis bergabung**, tanpa target dan tanpa biaya pendaftaran.

## 2. Cara Bergabung

1. **Daftar akun** {$app} secara gratis.
2. Buka menu **Referral** di akun Anda, lalu salin **kode** atau **link referral** Anda.
3. **Bagikan** ke calon pelanggan melalui WhatsApp, media sosial, website, atau presentasi langsung.

[Daftar Jadi Affiliate]({$registerUrl})

## 3. Cara Komisi Dibayar

1. Komisi dari **pembelian pertama** pelanggan masuk ke **Saldo** setelah masa tahan **{$holdDays} hari**.
2. Komisi dari pembelian berikutnya **langsung masuk** ke Saldo.
3. Saldo dapat **ditarik ke rekening atas nama Anda sendiri**, minimal **{$rupiah(self::MIN_WITHDRAWAL)}**, atau dipakai untuk membeli paket.

## 4. Contoh Penghasilan

Anda mengajak **5 usaha**, dan masing-masing berlangganan paket seharga **{$rupiah(300000)} per bulan**:

- Komisi per bulan: 5 x {$rupiah(300000)} x {$percent}% = **{$rupiah($example)}**
- Selama kelima pelanggan terus memperpanjang, komisi tersebut **terus masuk setiap bulan**.

*Angka di atas hanya ilustrasi. Penghasilan sebenarnya bergantung pada paket yang dibeli pelanggan Anda.*

## 5. Pertanyaan Umum

**Apakah pelanggan saya mendapat diskon?**
Bisa. Diskon pembelian pertama berlaku bila tersedia untuk kode Anda, dan besarnya tampil saat pelanggan memasukkan kode di halaman pembelian.

**Kapan komisi bisa ditarik?**
Setelah komisi masuk ke Saldo. Untuk pembelian pertama, setelah masa tahan {$holdDays} hari selesai.

**Bolehkah saya memakai kode sendiri?**
Tidak. Kode referral hanya untuk pelanggan baru. Pemakaian kode sendiri atau akun ganda akan membatalkan komisi.

**Apakah ada batas jumlah pelanggan?**
Tidak ada. Semakin banyak pelanggan aktif, semakin besar komisi Anda.

## 6. Ketentuan

Program ini tunduk pada [Ketentuan Program Referral](/page/ketentuan-referral) dan [Syarat & Ketentuan](/syarat-dan-ketentuan) {$app}. Mohon dibaca sebelum mulai membagikan kode Anda.
MD;
    }
}
