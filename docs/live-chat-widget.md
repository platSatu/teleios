# Live Chat Widget (6 Oktober 2026)

Widget chat yang bisa dipasang di website mana pun. AI cabang menjawab
otomatis, dan CS bisa mengambil alih dari **Chat > Live Chat Inbox**.

## Alur singkat

1. Admin membuat widget di **Chat > Live Chat Widget**, lalu menyalin kode:
   `<script src="https://DOMAIN/widget.js" data-key="wgt_..." async></script>`
2. `public/widget.js` membuat tombol + iframe ke `/chat-widget/{key}`.
   Iframe hanya boleh tampil di domain terdaftar (CSP `frame-ancestors`).
3. Pengunjung chat lewat API `/api/chat-widget/{key}/...`, dikenali dari
   token acak (disimpan sebagai hash).
4. Selama status `ai`, setiap pesan pengunjung memicu job
   `SendChatWidgetAiReply` (antrean `config('queue.ai_queue')`), yang memakai
   AI Bot cabang yang sama dengan WA (`AiReplyGenerator`).
5. Pengunjung klik "Bicara dengan tim" / menulis "cs", atau AI tidak
   tersedia -> status `waiting`. CS "Ambil alih" -> `agent` (AI berhenti).

6. Pengunjung diam 3 menit setelah dibalas -> ditanya "masih terhubung?";
   diam 2 menit lagi -> percakapan ditutup dan widget mulai dari awal
   (scheduler `chat-widget:idle`, `ChatWidgetService::handleIdle()`).

## File fitur ini

Baru (hapus semua untuk membuang fitur):

- `app/Models/ChatWidget.php`, `ChatWidgetConversation.php`, `ChatWidgetMessage.php`
- `app/Http/Controllers/Chat/Widget/` (seluruh folder)
- `app/Services/Chat/Widget/` (seluruh folder)
- `app/Jobs/SendChatWidgetAiReply.php`
- `resources/views/chat/widgets/` (seluruh folder)
- `public/widget.js`
- `database/migrations/2026_10_06_100000_create_chat_widget_tables.php`
- `docs/live-chat-widget.md` (file ini)

Titik sentuh di file lama (cari teks "Live Chat Widget"):

- `routes/web.php`: 1 route publik `chat-widget.frame` + 2 grup route di grup `chat`
- `routes/api.php`: 1 grup route `chat-widget/{key}` di paling bawah
- `resources/views/layouts/partials/menu.blade.php`: 2 item menu setelah "AI Bot"
- `app/Http/Middleware/SecurityHeaders.php`: `'chat-widget.frame'` di `routeIs()`
- `bootstrap/app.php`: 1 jadwal `chat-widget:idle`

## Cara membuang fitur dengan bersih

1. Matikan dulu (opsional): nonaktifkan semua widget dari menu.
2. Hapus tabel & menu: `php artisan migrate:rollback --path=database/migrations/2026_10_06_100000_create_chat_widget_tables.php`
3. Hapus file baru di atas, kembalikan 4 titik sentuh, lalu
   `php artisan optimize:clear`.

Kalau fitur ini masih di branch `feature/live-chat`, cukup hapus branch-nya.
