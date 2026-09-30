<?php

namespace Database\Seeders;

use App\Models\WebFooter;
use Illuminate\Database\Seeder;

/**
 * Link footer fe-konexa ke halaman aplikasi Teleios (kolom "Bantuan").
 * URL dibangun dari route() -- jadi mengikuti APP_URL masing-masing app
 * (bizbos/konexa), tidak di-hardcode.
 *
 * Aman dijalankan ulang: hanya membuat link yang belum ada (dicek per grup
 * & nama), tidak menimpa yang sudah diedit lewat Superadmin > Web > Footer.
 * Jalankan khusus: php artisan db:seed --class=FooterLinkSeeder
 */
class FooterLinkSeeder extends Seeder
{
    private const GROUP = 'Bantuan';

    public function run(): void
    {
        $links = [
            ['name' => 'Dokumentasi', 'link' => route('dokumentasi.index')],
        ];

        foreach ($links as $order => $link) {
            WebFooter::firstOrCreate(
                ['group_name' => self::GROUP, 'name' => $link['name']],
                ['link' => $link['link'], 'column_width' => 'col-md-3', 'target_blank' => true, 'sort_order' => $order + 1, 'status' => 'active'],
            );
        }
    }
}
