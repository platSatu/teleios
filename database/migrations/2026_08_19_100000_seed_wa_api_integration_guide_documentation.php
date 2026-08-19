<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Menambah SATU artikel baru ke halaman /dokumentasi publik (lihat
     * PublicDocumentationController) — panduan cepat "hubungkan aplikasi lain
     * ke Konexa/Teleios" (mis. InaStudy atau sistem manapun yang mau kirim
     * WhatsApp lewat sini). Diletakkan di kategori "Kirim Pesan" yang sudah
     * ada (lihat 2026_08_03_180300_seed_wa_api_documentation_content.php),
     * SEBELUM artikel referensi teknis endpoint-nya (sort_order 15, di antara
     * "Cara Autentikasi" di kategori lain dan "Kirim Pesan WhatsApp" di
     * sort_order 20) — supaya orang baca ringkasan langkahnya dulu sebelum
     * masuk ke detail request/response mentah.
     *
     * Sengaja TIDAK menulis ulang konten "Cara Autentikasi"/"Kirim Pesan
     * WhatsApp" yang sudah ada — artikel ini murni tambahan yang merangkum
     * & mereferensikan keduanya, bukan duplikat.
     *
     * Sama seperti migration sebelumnya: idempotent lewat pengecekan slug,
     * upsertCategory/upsertArticle dipakai ulang persis (bukan ditulis
     * ulang) supaya polanya tetap konsisten satu file dengan file lain.
     */
    public function up(): void
    {
        $messageCategoryId = $this->upsertCategory('Kirim Pesan', 'kirim-pesan', 'Endpoint untuk mengirim pesan WhatsApp lewat device yang sudah terhubung.', 20);

        $this->upsertArticle($messageCategoryId, 'Integrasi dari Aplikasi Lain', 'integrasi-aplikasi-lain', 'POST', '/api/wa-api/v1/send-message', <<<'DESC'
Panduan singkat menghubungkan aplikasi lain (mis. sistem CRM, form pendaftaran, atau aplikasi internal apa pun — contohnya InaStudy) supaya bisa kirim WhatsApp lewat Konexa/Teleios, tanpa aplikasi itu perlu login ke dashboard ini.

Langkah-langkahnya:

1. Di dashboard Konexa/Teleios, buka halaman Device (dashboard/chat/connect-device), pastikan device WhatsApp yang mau dipakai sudah berstatus "Terhubung".
2. Klik tombol "API Key" pada device tersebut, lalu klik "Generate Token & Secret Key" — lihat artikel "Cara Autentikasi" untuk detailnya.
3. Di aplikasi lain (mis. Settings > WhatsApp Gateway di InaStudy), isi 3 kolom berikut:
   - URL/API Host: domain Konexa/Teleios Anda (contoh "https://domain-konexa-anda.com"), tanpa path apa pun di belakangnya.
   - Token: hasil generate langkah 2, dikirim sebagai header X-WA-Token.
   - Secret Key: hasil generate langkah 2, dikirim sebagai header X-WA-Secret.
4. Selesai — aplikasi lain itu otomatis memanggil endpoint di bawah setiap kali perlu kirim WhatsApp. Tidak ada langkah tambahan di sisi Konexa/Teleios.

Detail lengkap parameter request/response ada di artikel "Kirim Pesan WhatsApp" pada kategori yang sama.
DESC, <<<'REQ'
curl -X POST https://domain-konexa-anda.com/api/wa-api/v1/send-message \
     -H "X-WA-Token: wa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
     -H "X-WA-Secret: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
     -H "Content-Type: application/json" \
     -d '{"to": "6281234567890", "message": "Halo, ini pesan dari aplikasi lain."}'
REQ, <<<'RES'
{
  "status": "sent",
  "message": {
    "id": 123,
    "chat_jid": "6281234567890@s.whatsapp.net",
    "body": "Halo, ini pesan dari aplikasi lain.",
    "sent_at": "2026-08-19T12:00:00+07:00"
  }
}
RES, 15);
    }

    public function down(): void
    {
        DB::table('api_documentations')->where('slug', 'integrasi-aplikasi-lain')->delete();
    }

    private function upsertCategory(string $name, string $slug, string $description, int $sortOrder): string
    {
        $existingId = DB::table('category_documentations')->where('slug', $slug)->value('id');

        if ($existingId) {
            return $existingId;
        }

        $id = (string) Str::uuid();

        DB::table('category_documentations')->insert([
            'id' => $id,
            'user_id' => null,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertArticle(string $categoryId, string $title, string $slug, string $method, string $endpoint, string $description, string $request, string $response, int $sortOrder): void
    {
        $exists = DB::table('api_documentations')->where('slug', $slug)->exists();

        if ($exists) {
            return;
        }

        DB::table('api_documentations')->insert([
            'id' => (string) Str::uuid(),
            'category_documentation_id' => $categoryId,
            'title' => $title,
            'slug' => $slug,
            'method' => $method,
            'endpoint' => $endpoint,
            'description' => trim($description),
            'request_example' => trim($request),
            'response_example' => trim($response),
            'sort_order' => $sortOrder,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
