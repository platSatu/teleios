<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Isi katalog Application Menu dari sidebar yang AKTIF sekarang
 * (resources/views/layouts/partials/menu.blade.php) -- yang bisa
 * diberikan owner ke role staff lewat tab Applications. Seeder-seeder
 * lama (2026_08_03_170200 dst.) tidak pernah mengisi apa pun di server
 * karena mencari kategori "%chat%" / kategorinya belum ada saat itu.
 *
 * - Menu yang dikomentari di sidebar sengaja tidak dimasukkan.
 * - Halaman lanjutan (Header Form, Ruangan, Inbox, dll.) tidak perlu
 *   baris sendiri -- ikut menu induknya, lihat CompanyContext::DRILL_DOWN.
 * - Menu khusus owner (Persetujuan Tarik Saldo, Roles, Applications,
 *   Branch Office/Unit) tidak dimasukkan, lihat CompanyContext::OWNER_ONLY.
 *
 * Idempotent: baris yang route_name-nya sudah ada tidak diubah (nama/
 * urutan/ikon hasil edit superadmin tetap aman).
 */
return new class extends Migration
{
    private const MENUS = [
        'whatsapp' => [
            'chat.connect-device.index' => 'Device / Inbox',
            'chat.message-schedules.index' => 'Pesan Terjadwal',
            'chat.message-templates.index' => 'WA Template',
            'chat.category-templates.index' => 'Kategori Template',
            'chat.message-auto-replies.index' => 'Auto Reply (Kata Kunci)',
            'chat.message-quick-replies.index' => 'Balasan Cepat',
            'chat.ai-bots.index' => 'AI Bot',
            'chat.labels.index' => 'Label',
            'chat.phone-books.index' => 'Kontak',
            'chat.contacts.index' => 'Riwayat Kontak',
            'chat.category-phone-books.index' => 'Kelompok',
            'chat.wa-groups.index' => 'WA Group',
            'chat.google-contacts.index' => 'Google Contact',
            'chat.third-party.google-form.index' => 'Google Form',
            'chat.settings.edit' => 'Pengaturan',
        ],
        'form' => [
            'form.branch.index' => 'Branch',
            'form.category.index' => 'Form Category',
        ],
        'jadwal' => [
            'jadwal.branch.index' => 'Branch',
            'jadwal.mata-pelajaran.index' => 'Mata Pelajaran / Bidang',
            'jadwal.pengajar.index' => 'Pengajar',
            'jadwal.student.index' => 'Student',
            'jadwal.kelas.index' => 'Jadwal Kelas',
            'jadwal.laporan.index' => 'Laporan',
            'jadwal.settings.edit' => 'Pengaturan Pengingat',
            'jadwal.reschedule-requests.index' => 'Permintaan Reschedule',
            'keuangan.dashboard.index' => 'Dashboard Saldo',
            'keuangan.transfer-fee.index' => 'Transfer Fee Pengajar',
            'keuangan.withdrawal.index' => 'Tarik Saldo',
        ],
        'pembayaran' => [
            'tagihan.category.index' => 'Kategori Tagihan',
            'tagihan.pelanggan.index' => 'Pelanggan',
            'tagihan.index' => 'Tagihan (Invoice)',
            'tagihan.laporan.index' => 'Laporan',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::MENUS as $category => $menus) {
            $categoryId = DB::table('category_applications')
                ->whereRaw('LOWER(name) LIKE ?', [$category.'%'])
                ->orderBy('created_at')
                ->value('id');

            if (! $categoryId) {
                continue;
            }

            $existing = DB::table('application_menus')
                ->whereIn('route_name', array_keys($menus))
                ->pluck('route_name')
                ->all();

            $order = 0;

            foreach ($menus as $routeName => $name) {
                $order += 10;

                if (in_array($routeName, $existing, true)) {
                    continue;
                }

                DB::table('application_menus')->insert([
                    'id' => (string) Str::uuid(),
                    'category_application_id' => $categoryId,
                    'name' => $name,
                    'route_name' => $routeName,
                    'sort_order' => $order,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Sengaja dibiarkan: menu mungkin sudah diberikan ke role
        // (company_role_menus) atau diedit superadmin.
    }
};
