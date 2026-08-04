<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seeds the public /dokumentasi page (see PublicDocumentationController)
     * with real, working content describing the third-party send-message
     * API this migration's sibling changes just introduced (App\Models\
     * WaApiKey, App\Http\Controllers\Api\WaApiSendMessageController) —
     * so the page isn't an empty CMS shell the moment this ships.
     * Superadmin can freely edit/add to this afterward from
     * dashboard/superadmin/wa-api-dokumentasi.
     *
     * Idempotent: matched by slug, so re-running (or a fresh install
     * where this already ran) never creates duplicates.
     */
    public function up(): void
    {
        $authCategoryId = $this->upsertCategory('Autentikasi', 'autentikasi', 'Cara mengautentikasi setiap request ke WhatsApp API.', 10);
        $messageCategoryId = $this->upsertCategory('Kirim Pesan', 'kirim-pesan', 'Endpoint untuk mengirim pesan WhatsApp lewat device yang sudah terhubung.', 20);

        $this->upsertArticle($authCategoryId, 'Cara Autentikasi', 'cara-autentikasi', 'GET', '/dokumentasi#autentikasi', <<<'DESC'
Setiap request ke WhatsApp API wajib menyertakan dua header berikut:

- X-WA-Token: token milik device Anda
- X-WA-Secret: secret key milik device Anda

Token dan Secret Key didapat dari halaman Device (dashboard/chat/connect-device) — klik tombol "API Key" pada device yang ingin dipakai, lalu klik "Generate Token & Secret Key". Simpan baik-baik; keduanya bisa di-generate ulang kapan saja jika bocor, tapi token/secret lama langsung berhenti berfungsi begitu digenerate ulang.
DESC, <<<'REQ'
curl -H "X-WA-Token: wa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
     -H "X-WA-Secret: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
REQ, <<<'RES'
{
  "error": "Token atau Secret Key tidak valid."
}
RES, 10);

        $this->upsertArticle($messageCategoryId, 'Kirim Pesan WhatsApp', 'kirim-pesan-whatsapp', 'POST', '/api/wa-api/v1/send-message', <<<'DESC'
Mengirim satu pesan teks WhatsApp lewat device yang terhubung ke API Key ini. Cocok dipakai sebagai kanal notifikasi dari sistem lain (misal: notifikasi pesanan baru, status tiket, dsb).

Parameter body:
- to (wajib): nomor tujuan (contoh "6281234567890") atau JID lengkap (contoh "6281234567890@s.whatsapp.net" untuk personal, atau "xxxxx@g.us" untuk grup).
- message (wajib): isi pesan, maksimal 4096 karakter.
DESC, <<<'REQ'
curl -X POST https://domain-anda.com/api/wa-api/v1/send-message \
     -H "X-WA-Token: wa_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
     -H "X-WA-Secret: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
     -H "Content-Type: application/json" \
     -d '{"to": "6281234567890", "message": "Halo, pesanan Anda sedang diproses."}'
REQ, <<<'RES'
{
  "status": "sent",
  "message": {
    "id": 123,
    "chat_jid": "6281234567890@s.whatsapp.net",
    "body": "Halo, pesanan Anda sedang diproses.",
    "sent_at": "2026-08-03T12:00:00+07:00"
  }
}
RES, 20);
    }

    public function down(): void
    {
        DB::table('api_documentations')->whereIn('slug', ['cara-autentikasi', 'kirim-pesan-whatsapp'])->delete();
        DB::table('category_documentations')->whereIn('slug', ['autentikasi', 'kirim-pesan'])->delete();
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
