# Standar Kerja untuk Project Teleios (Konexa)

Project: WhatsApp Business Gateway + CRM SaaS (Laravel + backend Go untuk koneksi WhatsApp)

Dokumen ini adalah instruksi standing yang harus selalu diperhatikan di setiap sesi coding untuk project ini, bukan cuma sekali dibaca lalu dilupakan.

## 1. Keamanan (Security)

- Setiap endpoint baru WAJIB jelas siapa yang boleh akses: cek middleware auth/otorisasi yang relevan (`superadmin`, `active.package`, `menu.access`, `wa.api-key`, dll — lihat `bootstrap/app.php` untuk daftar lengkap) sebelum menambah route baru.
- Jangan pernah percaya input dari request tanpa validasi (`$request->validate()` di setiap controller, seperti pola yang sudah konsisten dipakai di codebase ini).
- Kalau membuat endpoint yang dipanggil pihak ketiga (bukan user login), ikuti pola `WaApiSendMessageController` — gunakan API key middleware, jangan `auth` biasa, dan batasi kemampuannya seminimal mungkin (jangan expose lebih dari yang benar-benar dibutuhkan pihak ketiga).
- Jangan log data sensitif (token, password, nomor kartu, dll) — kalau perlu logging untuk debug, mask/redact dulu.
- Setiap kali menambah fitur yang menyentuh uang/wallet/deposit, cek dulu apakah `PackageLimitService` atau pola `lockForUpdate` + unique constraint perlu dipakai supaya tidak ada race condition yang bisa merugikan (lihat poin 2).

## 2. Concurrency & Race Condition

Codebase ini sudah punya pola yang benar untuk ini — WAJIB diikuti setiap kali menambah fitur yang bisa diakses banyak proses/worker bersamaan:

- **Klaim/dedup pekerjaan**: pakai unique constraint di level database (bukan cuma cek `firstOrCreate` di kode PHP tanpa try/catch — lihat `DispatchDueWaMessageSchedules::claimAndDispatch()` untuk contoh pola yang benar: `lockForUpdate()` + try/catch `QueryException`, sama seperti `PackageLimitService::lockOrCreateUsage()`).
- **Counter/kuota yang dipakai bersamaan** (uang, kuota kirim, dsb): ikuti pola `PackageLimitService::reserve()` — transaksi terkunci (`DB::transaction()` + `lockForUpdate()`), plus try/catch `QueryException` untuk race pada baris pertama (lihat `lockOrCreateUsage()`).
- **Job queue yang tidak boleh dobel**: pakai `WithoutOverlapping` middleware seperti di `SendScheduledWaMessage`.
- **Rate limiting per-resource** (device WA, dsb): pakai Laravel `RateLimiter` dengan cache store yang atomik (`database` atau `redis`, JANGAN `file`/`array` untuk sesuatu yang diakses banyak worker — lihat pola `BroadcastThrottleService`).
- Kalau menambah job/fitur baru yang mengirim pesan WA (auto-reply, AI bot, chatbot flow, dsb), WAJIB ikut lewat `BroadcastThrottleService` juga — jangan cuma broadcast terjadwal yang dibatasi. `SendAutoReplyMessage` dan `SendAiBotReply` sudah mengikuti pola ini (`$throttle->attempt($deviceId, $companyId)` sebelum kirim); jadikan keduanya contoh referensi untuk job pengirim WA berikutnya.

## 3. Skalabilitas

- Hindari query N+1 — eager load relasi yang dibutuhkan (`with(...)`), seperti pola yang sudah dipakai di `DealController::index()`.
- Untuk data yang bisa tumbuh besar (chat list, log pengiriman, dsb), selalu pertimbangkan pagination/limit, jangan fetch semua sekaligus.
- Kalau menambah CSS/JS baru untuk halaman besar seperti Inbox, jangan tambah lagi ke file blade yang sudah 150KB+ (`inbox/inbox.blade.php`) — ini sudah jadi masalah performa (tidak bisa di-cache browser). Pertimbangkan file terpisah kalau menyentuh area ini.

## 4. Anti-Ban WhatsApp

- Backend ini pakai koneksi WhatsApp tidak resmi (multi-device pairing via QR, bukan WhatsApp Business Platform resmi) — jadi SEMUA pengiriman pesan otomatis/massal harus tetap hati-hati:
  - Selalu ada jeda/jitter antar pengiriman ke banyak penerima (lihat pola di `DispatchDueWaMessageSchedules`).
  - Selalu cek opt-out (`BroadcastOptOutService`) sebelum mengirim ke nomor manapun secara massal.
  - Jangan kirim konten yang benar-benar identik ke ratusan nomor tanpa variasi kalau bisa dihindari.

## 5. Alur Kerja Kolaborasi

- **Jangan langsung coding kalau user masih dalam tahap diskusi/investigasi** — tunggu instruksi eksplisit untuk mulai implementasi. Kalau ragu, tanya dulu.
- Untuk perubahan yang menyentuh data production (migration, query manual, dsb), selalu ingatkan soal backup dan konfirmasi nama database yang benar (demo vs production) sebelum eksekusi.
- Jelaskan trade-off keamanan/skalabilitas di akhir setiap perubahan kode yang cukup signifikan — jangan cuma bilang "sudah selesai".
- Bahasa komunikasi: Bahasa Indonesia.

## 6. Known Issues (per audit 14 Agustus 2026, diverifikasi ulang 21 Agustus 2026)

Catatan untuk sesi mendatang — ini ditemukan lewat investigasi.

### Masih terbuka

1. **Dua sistem assignment chat berjalan sendiri-sendiri**: `WaContact.assigned_to` (lama, dipakai UI Inbox) vs `WaConversation.assigned_to` + status Chat Ops (baru, backend lengkap tapi belum ada UI). Perlu diputuskan mau disatukan ke arah mana.
2. Konsep "Category" untuk chat belum ada (baru ada Label). Pelacakan "assigned oleh siapa" (audit) juga belum ada di kedua sistem assignment.
3. Verifikasi `CACHE_STORE`/`QUEUE_CONNECTION` di `.env` produksi — pastikan bukan `file`/`array`/`sync` supaya rate limiter dan queue tetap aman untuk banyak worker. (Di `.env` lokal per 21 Agustus 2026 sudah `database` untuk keduanya — tetap cek ulang khusus di server production.)

### Sudah diperbaiki

- **Race condition di `DispatchDueWaMessageSchedules::claimAndDispatch()`** — sekarang sudah pakai `lockForUpdate()` + try/catch `QueryException`, pola yang sama dengan `PackageLimitService::lockOrCreateUsage()`. Diverifikasi 21 Agustus 2026.
- **`SendAutoReplyMessage` dan `SendAiBotReply` tidak melewati `BroadcastThrottleService`** — sekarang keduanya sudah memanggil `$throttle->attempt($deviceId, $companyId)` sebelum kirim, sama seperti `SendScheduledWaMessage`. Diverifikasi 21 Agustus 2026.
