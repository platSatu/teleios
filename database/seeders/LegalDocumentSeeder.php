<?php

namespace Database\Seeders;

use App\Models\WebFooter;
use App\Models\WebPage;
use App\Models\WebTermCondition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dokumen legal website: Syarat & Ketentuan (modul web_term_conditions --
 * dipakai popup register & /syarat-dan-ketentuan fe-konexa) dan 6 dokumen
 * pendukung sebagai Halaman tipe Dokumen (/page/{slug}, kolom footer
 * "Legal"). Isi Markdown ada di database/seeders/legal/{slug}.md; {app}
 * dan {company} diganti saat seeding.
 *
 * Aman dijalankan ulang: hanya MEMBUAT yang belum ada (dicek per slug /
 * nama), tidak pernah menimpa isi yang sudah diedit lewat Superadmin.
 * Jalankan khusus: php artisan db:seed --class=LegalDocumentSeeder
 */
class LegalDocumentSeeder extends Seeder
{
    private const COMPANY = 'PT Kreacipta Solusi Digital';

    private const FOOTER_GROUP = 'Legal';

    private const TERMS_SLUG = 'syarat-dan-ketentuan';

    private const TERMS_NAME = 'Syarat dan Ketentuan Layanan';

    /** slug => [judul, ringkasan (subtitle & meta description)] -- urutan = urutan di footer. */
    private const PAGES = [
        'kebijakan-privasi' => ['Kebijakan Privasi', 'Bagaimana kami mengumpulkan, menggunakan, dan melindungi data pribadi Anda.'],
        'kebijakan-penggunaan' => ['Kebijakan Penggunaan yang Dilarang', 'Aturan penggunaan layanan dan pengiriman pesan WhatsApp yang aman dan bertanggung jawab.'],
        'ketentuan-saldo' => ['Ketentuan Saldo, Top Up & Tarik Saldo', 'Cara kerja saldo, top up, transfer, dan penarikan dana.'],
        'kebijakan-refund' => ['Kebijakan Pembelian & Refund', 'Ketentuan pembelian paket, kode aktivasi, dan pengembalian dana.'],
        'ketentuan-referral' => ['Ketentuan Program Referral', 'Cara mendapatkan komisi, masa tahan, dan aturan program referral.'],
        'pemrosesan-data' => ['Perjanjian Pemrosesan Data', 'Pembagian tanggung jawab atas data pelanggan yang Anda kelola di layanan kami.'],
    ];

    public function run(): void
    {
        foreach (array_keys(self::PAGES) as $order => $slug) {
            [$title, $summary] = self::PAGES[$slug];

            WebPage::firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'type' => 'document',
                'subtitle' => $summary,
                'content' => $this->document($slug),
                'meta_description' => $summary,
                'show_in_footer' => true,
                'footer_group' => self::FOOTER_GROUP,
                'footer_order' => $order + 1,
                'status' => 'active',
            ]);
        }

        $this->seedTerms();

        // S&K punya modul & route sendiri, jadi link-nya di kolom "Legal"
        // dibuat lewat Web > Footer (tampil paling atas di kolom itu).
        WebFooter::firstOrCreate(
            ['group_name' => self::FOOTER_GROUP, 'link' => '/'.self::TERMS_SLUG],
            ['name' => 'Syarat dan Ketentuan', 'column_width' => 'col-md-3', 'target_blank' => false, 'sort_order' => 0, 'status' => 'active'],
        );
    }

    /**
     * S&K versi baru jadi satu-satunya yang aktif. Versi lama TIDAK dihapus
     * (users.terms_id user lama tetap menunjuk ke versi yang mereka setujui).
     */
    private function seedTerms(): void
    {
        if (WebTermCondition::where('name', self::TERMS_NAME)->exists()) {
            return;
        }

        DB::transaction(function () {
            WebTermCondition::where('status', 'active')->update(['status' => 'inactive']);

            WebTermCondition::create([
                'name' => self::TERMS_NAME,
                'descriptions' => $this->document(self::TERMS_SLUG),
                'status' => 'active',
            ]);
        });
    }

    private function document(string $slug): string
    {
        $path = database_path("seeders/legal/{$slug}.md");

        if (! is_file($path)) {
            throw new RuntimeException("Dokumen legal tidak ditemukan: {$path}");
        }

        return strtr(trim((string) file_get_contents($path)), [
            '{app}' => config('app.name'),
            '{company}' => self::COMPANY,
        ]);
    }
}
