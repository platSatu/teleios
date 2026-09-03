# Standar Kerja untuk Project Teleios (Konexa)

Project: WhatsApp Business Gateway + CRM SaaS (Laravel + backend Go untuk koneksi WhatsApp)

Dokumen ini adalah instruksi standing yang harus selalu diperhatikan di setiap sesi coding untuk project ini, bukan cuma sekali dibaca lalu dilupakan.

## Checklist Prioritas Kerja Saat Ini (diurutkan, per 21 Agustus 2026, update 27 Agustus 2026, update 2 September 2026)

Ini rangkuman semua task terbuka dari seluruh dokumen ini, diurutkan dari yang paling mendesak. Cek dari atas ke bawah tiap mulai sesi baru — jangan loncat ke bawah sebelum yang di atasnya beres, kecuali ada alasan jelas.

- [ ] **1. Commit & push semua perubahan hari ini ke git.** Composer.lock, fix migration (`disableForeignKeyConstraints`), fix seeder (hapus `WithoutModelEvents`), CLAUDE.md, dan `docs/api/wa-api-v1.openapi.json` semuanya masih cuma ada di disk lokal + VPS staging, BELUM ke-commit sama sekali. Ini paling berisiko — kalau ada apa-apa di laptop/VPS, semua kerjaan bisa hilang tanpa jejak. Command lengkap ada di section "Alur Branch & Deploy" langkah 2-4 di bawah.
- [ ] **2. `g_backend`: tambahkan panic recovery (`recover()`) di semua goroutine manual + event handler WhatsApp** — saat ini TIDAK ADA satupun `recover()` di codebase Go (lihat 9.1). Kalau 1 device di company manapun memicu panic, SELURUH proses mati dan WA SEMUA company ikut terputus bersamaan — dan karena proses production belum systemd (poin 13 di bawah), tidak auto-restart. Risiko tertinggi di seluruh audit karena dampaknya lintas-tenant, bukan cuma 1 company.
- [ ] **3. Sentralisasi proteksi pengiriman WA di `InboxService`** — menutup celah `WaApiSendMessageController` (lihat detail temuan di section "Task Menuju SaaS Siap Pakai" 7.1) sekaligus menghilangkan duplikasi cek di 3 file lain. **Scope bertambah per audit 27 Agustus**: `SendChatbotFlowMessages.php` dan `SendCsatSurvey.php` juga ternyata bypass total (lihat 9.5) — begitu sentralisasi ini selesai, keduanya otomatis ikut terlindungi tanpa kode tambahan, sama seperti `WaApiSendMessageController`. **Spesifikasi lengkap (siap eksekusi begitu ada instruksi "lanjut kerjakan"):**
  - Scope: method `send()`, `sendPoll()`, `sendMedia()`, `sendStoredMedia()` di `app/Services/Chat/InboxService.php` — keempatnya benar-benar mengirim ke Go backend.
  - Urutan pengecekan di dalam tiap method itu, SEBELUM panggil Go backend:
    1. `PackageLimitService::requireActivePackage($company)`
    2. `BroadcastThrottleService::attempt($deviceId, $companyId)`
    3. `PackageLimitService::reserve($company, $metric)` — default metric `broadcast_send`, buat parameter supaya bisa beda per caller (untuk kalau nanti auto-reply/AI bot dapat metric sendiri, lihat poin 8 checklist ini).
    4. Kalau kirim ke Go backend gagal → `release()` kuota yang tadi di-reserve; kalau sukses → biarkan.
  - Lempar exception spesifik per jenis kegagalan (package tidak aktif / kuota habis / throttle penuh) supaya tiap pemanggil bisa handle pesannya sesuai konteks (`WaApiSendMessageController` balas JSON 429/403, auto-reply/AI bot cukup skip diam-diam seperti sekarang).
  - Hapus pengecekan manual yang sekarang dobel di `SendScheduledWaMessage.php`, `SendAutoReplyMessage.php`, `SendAiBotReply.php` (baris `requireActivePackage()`/`reserve()`/`release()`/`throttle->attempt()`) — supaya tidak reserve kuota 2x per pesan. `WaApiSendMessageController`, `SendChatbotFlowMessages`, dan `SendCsatSurvey` otomatis ikut terlindungi begitu manggil `InboxService::send()`/`sendPoll()` dengan company+deviceId yang benar, tanpa tambahan kode di pemanggil itu sendiri.
  - WAJIB test di staging sebelum merge ke main: broadcast terjadwal, auto-reply, AI bot, chatbot flow, CSAT survey, dan WA API pihak ketiga — masing-masing 2 skenario (dalam kuota vs kuota habis, package aktif vs expired) — supaya jalur yang sudah jalan sekarang tidak ikut rusak.
  - **Belum boleh dikerjakan sebelum ada instruksi eksplisit** — masih tahap diskusi per aturan section "Alur Kerja Kolaborasi".
- [ ] **4. Tambah idempotency guard di `GoogleFormWebhookController`** — TIDAK otomatis ketutup oleh poin 3 di atas. Submit form yang terkirim dobel (retry Apps Script, dsb) saat ini bisa bikin pesan WA dobel ke customer. Ikuti pola `Cache::add($lockKey, true, ...)` yang sudah dipakai `WaIncomingMessageWebhookController` (lihat 9.5).
- [ ] **5. Hash `secret_key`/`token` di `WaApiKey`** — saat ini tersimpan plaintext di DB (lihat 9.3). Kebocoran DB/backup langsung expose semua kredensial API pihak ketiga semua company.
- [ ] **6. Tambah index `created_at`** pada `audit_logs`, `ledger_entries`, `history_user_login`, `voucher_histories`, `payment_transactions` — kelima tabel ini tumbuh terus lintas semua company dan selalu di-query `->latest()->paginate()` tanpa index (lihat 9.6). Migration tambah index, risiko rendah untuk dikerjakan.
- [ ] **7. Cek Superadmin > Package Limit** — pastikan semua paket yang dijual sudah punya angka limit untuk `device_count`, `contact_count`, `branch_count`, `broadcast_send`. Operasional, bukan coding. Selama belum diisi, semua company punya kuota unlimited tanpa disadari (lihat 7.2).
- [ ] **8. Putuskan kebijakan kuota auto-reply & AI bot reply** — sengaja unlimited (beda kebijakan dari broadcast terjadwal), atau perlu metric baru (`auto_reply_send`/`ai_bot_send`)? Ini nentuin apakah poin 3 di atas perlu metric tambahan (lihat 7.1).
- [ ] **9. Konsolidasi menu pengiriman pesan** (Visi Produk poin 1) — broadcast terjadwal + chatbot flow + jenis sejenis lain jadi satu form, tinggal pilih jenisnya di dalam. Refactor UX besar, cek dulu kondisi menu/controller aktual sebelum mulai, jangan asumsi.
- [ ] **10. Konsolidasi menu balas pesan** (Visi Produk poin 2) — AI, chatbot flow, auto-reply, balasan cepat jadi satu menu.
- [ ] **11. Fix `Superadmin\UserController::reset()`** — ganti update `wallet.balance` langsung jadi lewat `WalletLedgerService::debit()`, supaya konsisten tercatat di `LedgerEntry` seperti perubahan saldo lain (lihat 7.4). Risiko rendah, prioritas rendah.
- [ ] **12. Refactor `menu.blade.php`** (738 baris/48KB) jadi data-driven (array/config + 1 partial loop) supaya nambah/hapus menu tidak edit HTML manual panjang (lihat 7.3).
- [ ] **13. Systemd service + graceful shutdown untuk `g_backend_konexa` production** — proses jalan unmanaged (bare process sejak 19 Agustus), kalau mati tidak auto-restart (lihat Known Issues). **Digabung dengan 9.2**: tambahkan juga signal handling (SIGTERM/SIGINT) supaya koneksi WA semua device ditutup rapi saat restart/deploy, bukan di-kill paksa.
- [ ] **14. Tentukan use case & scope function-calling chatbot** (query/update data CRM lewat AI bot) — fitur baru besar, butuh keputusan use-case dulu sebelum desain teknis (lihat 7.5).
- [x] **15. Modul Jadwal Lanjutan ("Jadwal v2")** — pengembangan besar di atas modul Jadwal yang sudah ada (dibangun ulang 31 Agustus 2026, lihat riwayat migration `2026_08_31_140000` dst, modul lama sebelumnya sudah di-drop permanen 21 Agustus). Didiskusikan panjang dengan user 2 September 2026, user memberi instruksi eksplisit "lakukan pengembangan dan mulai coding" pada sesi 2 September 2026 — **SUDAH DIIMPLEMENTASIKAN PENUH (13 poin spesifikasi di bawah, semua selesai)**, ~60 file baru/diubah, semua lolos `php -l` + Blade balance check, **BELUM di-commit ke git** (lihat catatan status di akhir poin ini). Istilah penting supaya tidak rancu dengan kode yang sudah ada: yang user sebut **"Kelas"** (Piano, Drum, dst) = tabel `jadwal_mata_pelajaran` yang sudah ada; **"Kategori"** (Piano Classic Level 1/2, Piano Pop) = level BARU di bawah Kelas — sudah ada tabelnya sekarang (`jadwal_kategori`); **"Sesi"** = satu pertemuan bertanggal, yang di kode sekarang disebut `jadwal_kelas` (jangan disamakan dengan "Kelas" versi user).

  **Kenapa ini genuinely pengembangan baru, bukan "aktifkan fitur yang sudah ada":** `jadwal_kelas` hari ini cuma punya `start_time`/`end_time` (dateTime spesifik, bukan hari+jam berulang) — satu baris = satu sesi 1-on-1 yang dibuat manual satu-satu, tidak ada konsep jadwal rutin mingguan, tidak ada kolom harga/persentase/durasi di mana pun di aplikasi, `attendance_status` yang ada sekarang cuma 2 opsi (`hadir`/`tidak_hadir`) tanpa logika hangus/cap-bulanan, dan reminder WA (`JadwalReminderSetting.remind_target`) cuma bisa ke `parent`/`student`/`both` — **belum ada opsi ke pengajar sama sekali**. Notifikasi ke pengajar saat ini CUMA jalan lewat alur approve/reject Reschedule Request (`reschedule_notify_pengajar`) — kalau admin edit jadwal langsung dari form Edit biasa, pengajar TIDAK ke-notif (gap yang perlu ditutup juga).

  **Spesifikasi hasil diskusi (semua harus via setting, tidak ada yang hardcode):**

  1. **Jam Operasional per Branch** (setting baru) — hari buka (mis. Senin–Sabtu), jam buka–tutup, jam istirahat. Beda-beda per branch.
  2. **Ruangan** (entitas baru, per branch) — nama + catatan kegunaan (opsional, cuma info/saran — TIDAK mengunci ruangan ke kelas tertentu, karena satu ruangan bisa dipakai piano atau gitar akustik gantian).
  3. **Kategori** (entitas baru, di bawah Kelas/`jadwal_mata_pelajaran`) — tiap Kategori punya **harga per sesi sendiri** + **persentase split company/pengajar sendiri** (level fee di sini, BUKAN global — konfirmasi user: "Piano Classic Level 1, harga 400rb, Level 2 500rb, Piano Pop flat karena tidak ada level").
  4. **Jadwal Rutin murid** (entitas baru) — gantinya `jadwal_student` sekarang yang cuma bisa 1 mata-pelajaran+1 pengajar per baris, murid bisa punya BANYAK baris Jadwal Rutin: Kategori + Pengajar + Ruangan (opsional) + Hari + Jam mulai + Durasi. Ini "cetakan" mingguannya.
  5. **Validasi bentrok** (guru & ruangan) dicek **saat Jadwal Rutin dibuat/disimpan** (bukan nanti pas generate) — user: "harusnya tidak ada yang bentrok karena kan 1 kelas 1 ruangan 1 guru jadi sifatnya private", jadi validasi di titik input sudah cukup, tidak perlu resolver bentrok yang rumit saat generate.
  6. **Auto-generate Sesi otomatis** (bukan tombol manual) — sistem generate baris sesi bertanggal dari Jadwal Rutin tiap bulan, sesuai jam operasional branch, default **4 sesi/bulan**, minggu ke-5 (kalau hari itu jatuh 5x dalam sebulan) otomatis TIDAK dihitung/tidak digenerate sebagai sesi reguler — slotnya disisakan buat sesi pengganti (lihat poin 8). Angka "4 sesi/bulan" dan durasi default (mis. 30 menit) HARUS dari setting, bukan angka mati di kode.
  7. **Status kehadiran diperluas jadi 3 opsi** (dari 2 sekarang): **Hadir** / **Tidak Hadir — hangus** (tanpa keterangan, tidak ada pengganti, **pengajar tetap dibayar penuh** karena dia tetap hadir mengajar sesuai jadwal) / **Izin/Sakit — dapat pengganti**.
  8. **Sesi pengganti** (untuk kasus izin/sakit) — **dipilih manual oleh admin** (bukan sistem cari otomatis), dengan 2 pola umum yang biasa ditawarkan: (a) hari sama tapi digabung jadi durasi 1 jam, atau (b) diisi ke slot minggu ke-5 yang biasanya kosong (lihat poin 6). Rencana teknis: sesi pengganti jadi baris BARU yang terhubung/referensi ke sesi asli (bukan mengubah sesi asli), supaya histori "pernah izin tanggal X, penggantinya tanggal Y" tetap tercatat rapi untuk laporan — **ini masih asumsi saya, perlu dikonfirmasi ulang ke user sebelum desain final** kalau mulai dikerjakan.
  9. **Reminder WA ke pengajar** — H-1 OTOMATIS (rekap semua sesi besok: jam berapa sampai jam berapa, murid siapa saja) DAN bisa direquest manual kapan saja oleh pengajar lewat chat WA (mis. ketik "jadwal", sistem cocokkan nomor WA pengirim ke `users.handphone` si pengajar, balas rekap jadwal minggu ini). User eksplisit: **pengajar TIDAK perlu halaman dashboard baru untuk ini** ("pengajar sementara tidak perlu dashboard dulu, tapi bisa request by wa") — pakai infrastruktur auto-reply/device WA yang sudah ada, jangan bikin sistem baru.
  10. **Reminder manual oleh admin** — selain otomatis H-1, admin bisa klik kirim reminder kapan saja (per sesi/per murid/per pengajar) — device pengirim & template pesan tetap ambil dari SATU setting yang sama dengan reminder otomatis (jangan hardcode teks/nomor terpisah untuk jalur manual ini).
  11. **Notifikasi pengajar saat reschedule** — perluas supaya notif juga jalan kalau admin edit jadwal LANGSUNG lewat form Edit biasa (bukan cuma lewat approve/reject Reschedule Request seperti sekarang — lihat gap di paragraf pembuka poin ini).
  12. **Laporan Harian** (baru, bisa export Excel) — rekap hari ini: kelas/kategori apa saja yang terpakai, ruangan mana, daftar murid & pengajar, jam berapa saja.
  13. **Laporan Bulanan** (baru, bisa export Excel) — jumlah murid aktif/baru masuk, jumlah pergantian jadwal (reschedule) yang terjadi, total fee (breakdown persentase company vs tiap pengajar), lama mengajar (total jam) per pengajar.

  **Update setelah implementasi awal**: poin 12 & 13 di atas awalnya 2 menu terpisah (Laporan Harian & Laporan Bulanan), lalu atas permintaan user digabung jadi **1 menu "Laporan"** dengan filter rentang tanggal bebas (dari-sampai — satu hari kalau dari==sampai, satu bulan kalau dari=awal bulan sampai akhir bulan, atau rentang lain), export Excel 1 file 2 sheet (Detail Sesi + Rekap). Tombol Export & field tanggal ada di form yang sama (`formaction`), field tanggal wajib diisi (`required` + validasi server `JadwalLaporanController::export()`) — kalau export diklik tanpa tanggal terisi, redirect balik dengan pesan "Silakan pilih tanggal terlebih dahulu.". Lihat `JadwalLaporanService::sesiUntukRentang()`/`rekap()`, `JadwalLaporanExport`, `resources/views/jadwal/laporan/index.blade.php`.

  **Status implementasi (update per sesi coding setelah instruksi "lanjut kerjakan")**: Semua 13 poin di atas sudah dikerjakan — migration & model (Jam Operasional/`JadwalBranchSetting`, Ruangan/`JadwalRuangan`, Kategori/`JadwalKategori`, Jadwal Rutin/`JadwalRutin`, kolom snapshot baru di `jadwal_kelas`, `jadwal_pengajar_reminder_logs`), CRUD + validasi bentrok (`JadwalRutinConflictService`), command auto-generate sesi bulanan (`jadwal:generate-sesi`, skip minggu ke-5, scheduled tiap tanggal 1), status kehadiran 3 opsi + sesi pengganti (referensi via `pengganti_dari_sesi_id`), reminder WA ke pengajar (H-1 otomatis via `jadwal:dispatch-pengajar-reminders`, manual per-sesi oleh admin, dan request on-demand lewat keyword WA `pengajar_request_keyword` — semua reuse device/template dari `JadwalReminderSetting` yang sama, tidak ada yang hardcode), notifikasi pengajar saat admin edit jadwal langsung (bukan cuma lewat reschedule request), dan Laporan (1 menu, filter rentang tanggal dari-sampai) dengan export Excel 2 sheet.

  **Belum selesai / langkah berikutnya**: belum di-commit ke git sama sekali (masih di disk lokal user, lihat poin 1 checklist di atas soal risiko ini) — dan workflow branch project ini mewajibkan lewat `staging` dulu, bukan langsung ke `main` (lihat section "Alur Branch & Deploy"), jadi commit Jadwal v2 ini juga harus ikut alur itu. QA yang sudah dilakukan sejauh ini murni lint-level (`php -l` semua file PHP + balance-check semua Blade) dan cross-check route/view — belum ada review manual di browser (klik-klik UI, isi form beneran) ataupun test otomatis, jadi tetap disarankan dicoba dulu di staging sebelum dianggap 100% siap produksi.

  **Restrukturisasi drill-down 14 September 2026** (atas permintaan user, dikirim sebagai diagram): urutan navigasi index-ke-index diubah total jadi **Branch -> Ruangan -> Jam Operasional -> Mata Pelajaran / Bidang -> Kategori -> Pengajar -> Student**, tiap index cuma nampilin SATU tombol "+ Add <anak berikutnya>" + Edit/Delete (bukan berbagai tombol campur seperti sebelumnya). Poin penting biar tidak salah paham baca kode:
  - **Data Jam Operasional TIDAK pindah jadi per-Ruangan** — tetap App\Models\JadwalBranchSetting satu baris per `branch_office_id` seperti spec asli poin 1 di atas (`GenerateJadwalRutinSesi`/`JadwalRutinConflictService`/`JadwalKategori::hargaPerSesi()` semuanya masih baca `branchOffice->jadwalBranchSetting` sebagai satu baris, TIDAK disentuh). Yang pindah cuma TITIK MASUK-nya — tombol "Add Jam Operasional" sekarang di baris Ruangan (`JadwalRuanganController`/index.blade.php), lewat `JadwalBranchSettingController::index()` (baru) yang nampilin 0/1 baris branch itu. Mengedit dari Ruangan A akan mengubah baris yang SAMA dengan Ruangan B kalau satu branch — sudah ada catatan eksplisit di UI-nya (index.blade.php & edit.blade.php).
  - **Mata Pelajaran / Bidang TIDAK dapat FK baru** — tetap `branch_office_id` langsung seperti sebelumnya (spec asli, tidak berubah). `ruangan_id` cuma dibawa-bawa lewat query string (index/create/store) supaya tombol "Kembali" & "+ Tambah" bisa balik ke halaman Jam Operasional-nya Ruangan yang tadi dipakai masuk — murni navigasi, bukan kolom baru.
  - **Pengajar jadi entitas baru** — App\Models\JadwalPengajarKategori (tabel `jadwal_pengajar_kategori`, migration `2026_09_14_090300`), penugasan Pengajar (tetap `users` yang sama, lewat `ResolvesCompanyContext::companyTeamMembers()`) ke SATU Kategori (`unique(jadwal_kategori_id, pengajar_id)`). `JadwalPengajarController` yang sebelumnya cuma `index()` read-only (pilih dari daftar user, scoped ke Mata Pelajaran) sekarang full CRUD, scoped ke `jadwal_kategori_id`. Ketersediaan (hari & jam) ada di relasi terpisah — lihat poin "Ketersediaan Pengajar jadi banyak slot" di bawah, bukan lagi kolom langsung di tabel ini. Ketersediaan ini MURNI INFO — ditampilkan sebagai alert box di form Add Student (`JadwalStudentController::create()` lookup `JadwalPengajarKategori` by kategori+pengajar), TIDAK divalidasi silang ke `JadwalRutin` (validasi bentrok jadwal tetap 100% di `JadwalRutinConflictService`, tidak disentuh). Hapus baris Pengajar ini AMAN, tidak menghapus Jadwal Rutin/sesi yang sudah dibuat pengajar itu (referensi ke `users.id` langsung, tidak saling FK).
  - **Student tidak dapat kolom baru** — `jadwal_student` tetap persis sama (`jadwal_mata_pelajaran_id` + `pengajar_id`, tanpa `jadwal_kategori_id`). `jadwal_kategori_id` cuma query-string context (create/index/store) buat breadcrumb "Kembali ke Pengajar" & panel ketersediaan di atas, TIDAK disimpan ke tabel.
  - Tombol "Jadwal Rutin" di index Student (yang berfungsi sebagai "lihat jadwal per student" di diagram) **sudah ada dari sebelumnya** (`route('jadwal.rutin.index', ['student_id' => ...])`), tidak perlu dibuat baru — begitu juga index `jadwal-kelas` dengan tab per Ruangan (bagian bawah diagram) yang sudah dikerjakan di sesi sebelumnya, tidak disentuh restrukturisasi ini.
  - QA restrukturisasi ini juga murni lint-level (`php -l` semua file yang diubah + balance-check semua Blade yang diubah) — belum diklik manual di browser, ikut catatan "belum selesai" di atas.
  - **Update 3 September 2026 (masih permintaan user, sesi yang sama): Pengajar dapat menu sidebar sendiri.** Sebelumnya Pengajar cuma bisa dijangkau lewat drill-down "+ Add Pengajar" di index Kategori (`jadwal_kategori_id` selalu ada). Sekarang ada juga entry "Pengajar" langsung di sidebar (`resources/views/layouts/partials/menu.blade.php`, antara "Mata Pelajaran / Bidang" dan "Student") yang mengarah ke `route('jadwal.pengajar.index')` TANPA query string — mode "global", pola sama seperti `JadwalMataPelajaranController`/`JadwalStudentController` yang sudah ada (bare = semua baris company/branch + kolom Kategori tambahan, scoped via `jadwal_kategori_id` = filter ke satu Kategori seperti sebelumnya). `JadwalPengajarController::index()/create()/store()/edit()/update()` semuanya sudah menangani `$kategori` null; `edit()` SELALU dropdown Kategori bebas (tidak pernah locked), `create()` locked kalau datang dengan `jadwal_kategori_id` valid, bebas kalau tidak — pola locked-vs-free "ina" project's University Album Photo yang sama dipakai di form lain. Blade view `jadwal-pengajar/{index,create,edit,_form}.blade.php` diupdate seluruhnya supaya tidak null-property-access saat `$kategori` null. "Notifikasi jika tidak bisa add" (poin eksplisit dari user) diimplementasi di `create.blade.php`: kalau belum ada Kategori sama sekali (mode global, dropdown bakal kosong) atau belum ada Team Member yang eligible jadi pengajar, form disembunyikan diganti alert warning yang jelas + link ke halaman prasyaratnya, bukan nampilin dropdown kosong yang bakal gagal validasi diam-diam.
  - **Update 3 September 2026 (masih sesi yang sama): Ketersediaan Pengajar jadi BANYAK slot (hari + jam), bukan satu hari_bisa[] + satu jam.** Kasus lapangan dari user: pengajar TIDAK available nonstop pagi-sore seperti jam kantor — satu hari bisa punya lebih dari satu rentang jam (mis. Senin 10:00-12:00 lalu 17:00-19:00 di hari yang sama), dan tiap hari bisa beda-beda. Struktur lama (kolom `hari_bisa` json + satu `jam_mulai`/`jam_selesai` langsung di `jadwal_pengajar_kategori`) tidak bisa menampung itu (satu jam berlaku sama ke semua hari yang dicentang). **Migration `jadwal_pengajar_kategori` (`2026_09_14_090300`) BELUM PERNAH di-`php artisan migrate`-kan saat perubahan ini dibuat**, jadi kolom `hari_bisa`/`jam_mulai`/`jam_selesai` dihapus LANGSUNG di migration itu (bukan migration alter terpisah) — tidak ada data yang perlu dimigrasikan. Ketersediaan sekarang di tabel anak baru `jadwal_pengajar_kategori_jadwal` (migration `2026_09_14_090400`, model App\Models\JadwalPengajarJadwal) — satu baris = satu `hari` (int, konvensi sama Carbon::dayOfWeek) + satu `jam_mulai`/`jam_selesai`, relasi `hasMany` dari `JadwalPengajarKategori::jadwals()`. Boleh banyak baris di hari yang sama, tidak ada unique constraint di situ (validasi cuma jam_selesai > jam_mulai per baris, lewat `$validator->after()` di `JadwalPengajarController::validator()`, bukan rule `after:` bawaan supaya tidak bergantung ke perilaku wildcard sibling-field Laravel). `JadwalPengajarKategori::jadwalGroupedByHari()`/`jadwalSummaryLabel()` — helper buat nampilin slot dikelompokkan per hari (dipakai di index Pengajar & panel ketersediaan form Add Student). Form (`jadwal-pengajar/_form.blade.php`) sekarang list baris dinamis "+ Tambah Baris" (vanilla JS, pola sama seperti preview harga di `jadwal-kategori/_form.blade.php`) — tiap baris: dropdown Hari + input jam mulai + input jam selesai, bukan checkbox hari + satu jam gabungan. `JadwalPengajarController::syncJadwal()` — hapus semua slot lama lalu buat ulang dari input form tiap kali store()/update() (dibungkus `DB::transaction`), lebih sederhana & aman daripada diff per-baris karena jumlah baris bebas berubah dari UI.

## Visi Produk & Arah Pengembangan (didiskusikan 21 Agustus 2026)

Tujuan besar project ini: menjadikan Konexa sebagai **CRM + tools marketing berbasis WhatsApp yang andal dan mudah dipakai** — bukan aplikasi dengan banyak menu terpisah yang membingungkan. Prinsip "kemudahan, tidak terlalu banyak menu" ini jadi acuan setiap kali mendesain fitur baru atau menata ulang UI (lihat juga section 5 & 7.3 soal konsolidasi UI).

Arah konkret yang disepakati:

1. **Satu menu untuk semua jenis pengiriman pesan** — form "pengaturan pengiriman" menggabungkan apa yang sekarang terpisah jadi "pesan terjadwal" dan "chatbot flow" (plus jenis sejenis lain: broadcast, sekali kirim, berulang, bertahap dengan interval) jadi SATU form — tinggal pilih jenisnya di dalam form itu, bukan halaman/menu yang beda-beda per jenis. **Arah refactor baru** — cek dulu kondisi menu/controller aktual sebelum mulai kerja, jangan asumsi sudah begini.
2. **Satu menu untuk semua jenis balasan pesan** — pengaturan balas pesan (AI, chatbot flow, auto-reply, balasan cepat) digabung jadi satu menu, semua jenis balasan diatur dari situ. **Arah refactor baru**, sama seperti poin 1.
3. **Phone book export & import** — ✅ sudah ada di kode (`PhoneBookController::export()`, `importTemplate()`, `import()`, diverifikasi 21 Agustus 2026). Tidak perlu dibangun ulang, cukup dipastikan tetap terjaga kalau area ini disentuh refactor lain.
4. **Template WA** tetap dipertahankan sebagai fitur pendukung pengiriman/balasan pesan — jangan hilang/kesampingkan waktu konsolidasi menu di poin 1 & 2.
5. **Assignment inbox ke rekan kerja atau device lain** — fitur ini WAJIB tunduk ke batasan package yang dipilih company, bukan fitur unlimited untuk semua tier. Cek dulu apakah assignment sekarang sudah gated per package atau belum (lihat juga Known Issues soal 2 sistem assignment yang berjalan sendiri-sendiri).
6. **Begitu package habis, SEMUA layanan berhenti** — bukan cuma pengiriman WhatsApp (yang sudah ditutup sebagian, lihat section 7.1), tapi seluruh fitur yang bergantung pada package aktif (assignment device/kontak lain, dsb) harus konsisten ikut berhenti juga.
7. **Package dibedakan berdasarkan 4 metric**: jumlah pengiriman pesan, jumlah device, jumlah cabang (branch), dan jumlah phone book (kontak) yang boleh ditampung — ini sudah match dengan kerangka `LimitMetric`/`PackageLimit` yang sudah ada (`broadcast_send`, `device_count`, `branch_count`, `contact_count`, lihat section 7.2). Tinggal dipastikan operasional (superadmin isi angkanya per package) dan konsisten dipakai di semua fitur relevan.
8. **Add-on/integrasi pihak ketiga** (Google Form, dan lain-lain ke depannya) — arsitekturnya harus mendukung penambahan integrasi baru tanpa bongkar besar-besaran. `GoogleFormWebhookController` sudah ada sebagai contoh pertama pola integrasi semacam ini.
9. **Broadcast harus tetap aman kalau banyak user jalankan bersamaan** — ini prinsip yang sudah jadi pondasi section 0 dan sudah banyak diverifikasi di section 7 (throttle, quota lock, dsb), tetap jadi syarat mutlak setiap fitur baru yang menyentuh pengiriman WA.

## 0. Prinsip Dasar — WAJIB Dicek Setiap Kali Ada Fitur/Endpoint/Job Baru

Aplikasi ini adalah **SaaS multi-tenant dengan target banyak user dan banyak company memakai sistem secara bersamaan** — bukan aplikasi internal skala kecil. Jangan pernah menganggap sebuah fitur baru "selesai" sebelum 5 pertanyaan ini dijawab:

1. **Race condition** — Kalau kode ini dipanggil 2 request/worker/user secara bersamaan (2 klik submit bebarengan, 2 webhook yang datang hampir bersamaan, 2 cron job yang overlap), apakah hasilnya tetap benar? Kalau menyentuh counter/saldo/kuota/baris yang direbutkan banyak proses, WAJIB pakai `DB::transaction()` + `lockForUpdate()` seperti `PackageLimitService::reserve()`. Kalau soal klaim/dedup pekerjaan, WAJIB pakai unique constraint di database + try/catch `QueryException` seperti `DispatchDueWaMessageSchedules::claimAndDispatch()`. Detail pola lengkap ada di poin 2.
2. **Proses dobel / idempotency** — Kalau request, webhook, atau job yang sama entah kenapa terpanggil 2x (WhatsApp retry kirim webhook, user klik tombol 2x karena koneksi lambat, job di-retry setelah timeout), apakah efeknya tetap sama seperti dipanggil sekali (saldo tidak nambah/kepotong 2x, pesan WA tidak terkirim dobel, baris tidak ke-insert dobel)? Kalau belum, tambahkan idempotency key atau unique constraint yang relevan sebelum fitur dianggap selesai — jangan cuma andalkan "kemungkinannya kecil".
3. **Bottleneck / siap skala besar** — Kalau tabel yang disentuh nanti berisi jutaan baris dan dipakai ratusan company sekaligus, apakah query-nya masih cepat? Cek N+1 (`with(...)`), cek index kolom yang dipakai di `WHERE`/`JOIN`/`ORDER BY`, cek pagination untuk list yang bisa tumbuh besar (chat, log pengiriman, dsb). Detail di poin 3.
4. **Broadcast/pengiriman WA harus tetap jalan bersamaan** — broadcast/auto-reply/AI bot dari company A tidak boleh saling block atau nge-lag broadcast company B yang jalan di saat bersamaan, tapi tetap WAJIB lewat `BroadcastThrottleService` supaya tidak kena ban WhatsApp (jeda/jitter per device tetap dijaga). Detail di poin 4.
5. **Uang/deposit/wallet** — kalau fitur menyentuh saldo/deposit, TIDAK BOLEH pola "cek saldo dulu, baru kurangi" tanpa lock (celah race condition klasik yang bisa bikin saldo minus atau double-spend kalau 2 request datang bersamaan). WAJIB `lockForUpdate()` + transaksi, ikuti pola `PackageLimitService`.
6. **Clean code & tampilan responsive** — kalau fitur ini ada sisi UI (blade baru/blade diubah), sudah pakai komponen Blade yang sudah ada (`x-primary-button`, `x-text-input`, dsb, bukan HTML mentah yang mengulang), sudah extend layout yang ada, dan sudah dicek tampilannya di mobile/tablet/desktop? Kalau ada logic bisnis, sudah dipindah ke Service class (bukan numpuk di controller)? Detail di poin 5.

Kalau salah satu jawabannya "belum aman" atau "belum dicek", jangan lanjut — perbaiki dulu polanya mengikuti contoh yang sudah ada di codebase (poin 1-4 di bawah), baru boleh dianggap selesai.

## 1. Keamanan (Security)

- Setiap endpoint baru WAJIB jelas siapa yang boleh akses: cek middleware auth/otorisasi yang relevan (`superadmin`, `active.package`, `menu.access`, `wa.api-key`, dll — lihat `bootstrap/app.php` untuk daftar lengkap) sebelum menambah route baru.
- Jangan pernah percaya input dari request tanpa validasi (`$request->validate()` di setiap controller, seperti pola yang sudah konsisten dipakai di codebase ini).
- Kalau membuat endpoint yang dipanggil pihak ketiga (bukan user login), ikuti pola `WaApiSendMessageController` — gunakan API key middleware, jangan `auth` biasa, dan batasi kemampuannya seminimal mungkin (jangan expose lebih dari yang benar-benar dibutuhkan pihak ketiga).
- **Setiap kali menambah/mengubah endpoint API pihak ketiga/per-device** (apa pun di bawah prefix `/api/wa-api/...`, atau API sejenis untuk integrasi eksternal ke depannya seperti add-on Google Form dkk), WAJIB catat/update dokumentasinya dalam format **OpenAPI 3.0 JSON** di `docs/api/` (satu file per grup API, contoh: `docs/api/wa-api-v1.openapi.json`) — bukan cuma komentar di kode. Ini supaya dev (internal atau pihak ketiga) bisa lihat dokumentasinya lewat tool standar (Swagger UI, Redoc, Postman import, dsb) tanpa harus baca source code. Dokumentasikan: path, method, header auth yang dipakai, schema request body, dan semua kemungkinan response (sukses maupun error) — contoh lengkap sudah ada di `docs/api/wa-api-v1.openapi.json` untuk endpoint `POST /wa-api/v1/send-message`, ikuti pola yang sama untuk endpoint baru. Update dokumentasi ini LANGSUNG waktu ngerjain fungsinya (bukan sesi terpisah) — tidak perlu dijelaskan panjang lebar dulu ke user, cukup dikerjakan sekalian.
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

## 5. Clean Code & Konsistensi Tampilan (Responsive)

- **PENTING — project ini punya 2 sistem UI berbeda, jangan disamaratakan** (koreksi dari versi sebelumnya di dokumen ini): `layouts.dashboard` (dipakai HAMPIR SEMUA fitur nyata — inbox, CRM, broadcast, package, dsb) pakai template admin **Bootstrap statis** (`public/be/assets/css/bootstrap.min.css`, `app.min.css`, di-load lewat `<link>` biasa, bukan Vite). `layouts.app` (bawaan scaffold Breeze, dipakai untuk halaman auth-adjacent) pakai **Tailwind** lewat `@vite(...)`, dan komponen di `resources/views/components/` (`x-primary-button`, `x-text-input`, dst) itu gaya Tailwind — kemungkinan besar TIDAK relevan/tidak dipakai di halaman dashboard yang sebenarnya. Sebelum bikin form/tombol baru: cek dulu halaman itu extend layout yang mana, baru ikuti gaya markup yang sudah dipakai tetangganya di layout yang sama — jangan campur Tailwind class ke halaman Bootstrap atau sebaliknya.
- **Ikuti layout yang sudah ada** — halaman baru extend `layouts.dashboard` (mayoritas fitur) atau `layouts.app`/`layouts.guest` sesuai konteksnya (bukan bikin `<html>`/`<head>` sendiri dari nol), supaya header/menu/footer (`layouts/partials/header.blade.php`, `menu.blade.php`, `footer.blade.php`) otomatis konsisten dan tidak dobel-maintain.
- **Responsive wajib, ikuti pola breakpoint yang sudah dipakai** — cek bagaimana halaman existing (dashboard, inbox, dsb) mengatur breakpoint Tailwind-nya (`sm:`, `md:`, `lg:`), pakai pola yang sama di halaman baru. Sebelum fitur dianggap selesai, cek tampilannya minimal di 3 lebar: mobile (~375px), tablet (~768px), desktop (~1280px) — jangan cuma dicek di layar besar lalu diasumsikan otomatis rapi di HP.
- **Clean code / best practice umum**:
  - Logic bisnis yang lebih dari beberapa baris jangan ditaruh langsung di controller — pindahkan ke Service class (ikuti pola `PackageLimitService`, `BroadcastThrottleService`) supaya controller tetap tipis dan gampang ditest.
  - Validasi input pakai Form Request class kalau aturannya sudah cukup kompleks/reusable, bukan numpuk `$request->validate([...])` panjang langsung di controller kalau dipakai di lebih dari satu tempat.
  - Penamaan variabel/method/class harus jelas menjelaskan maksudnya (bahasa Inggris untuk kode, konsisten dengan codebase yang sudah ada) — hindari singkatan ambigu.
  - Hindari duplikasi: kalau nulis logic yang mirip dengan yang sudah ada di tempat lain, cek dulu apakah bisa di-extract jadi helper/trait/service yang dipakai bersama, jangan copy-paste.
  - Ikuti code style yang sudah ada di file sekitarnya (indentasi, urutan import, dsb); kalau project ini punya Laravel Pint/PHP-CS-Fixer terpasang, jalankan sebelum commit.

## 6. Alur Kerja Kolaborasi

- **Jangan langsung coding kalau user masih dalam tahap diskusi/investigasi** — tunggu instruksi eksplisit untuk mulai implementasi. Kalau ragu, tanya dulu.
- Untuk perubahan yang menyentuh data production (migration, query manual, dsb), selalu ingatkan soal backup dan konfirmasi nama database yang benar (demo vs production) sebelum eksekusi.
- Jelaskan trade-off keamanan/skalabilitas di akhir setiap perubahan kode yang cukup signifikan — jangan cuma bilang "sudah selesai".
- Bahasa komunikasi: Bahasa Indonesia.

## 7. Alur Branch & Deploy (staging → main)

Project ini pakai 2 branch yang masing-masing terhubung ke 1 environment — **jangan pernah commit langsung ke `main`**, semua perubahan wajib lewat `staging` dulu:

| Branch | Environment | Folder di VPS | Fungsi |
|---|---|---|---|
| `staging` | `staging.konexa.id` | `/var/www/staging.konexa` (DB `konexa_staging`, backend Go di `/var/www/g_backend_staging` port `8082`) | tempat coba/tes sebelum ke production |
| `main` | `app.konexa.id` | `/var/www/app.konexa` (backend Go production di `/var/www/g_backend_konexa`, **belum** systemd — lihat Known Issues) | production, dipakai customer asli |

**Step-by-step tiap ada perubahan kode:**

1. Kerja di lokal (`C:\xampp\htdocs\teleios`), pastikan di branch `staging`: `git checkout staging` (atau `git pull origin staging` dulu kalau branch-nya sudah ada perubahan dari orang lain).
2. Kalau nambah dependency composer baru, jalankan `composer update <nama-package>` (bukan `composer update` polos tanpa nama package, supaya tidak ikut update package lain yang tidak berhubungan) supaya `composer.lock` ikut ter-update dan ikut di-commit.
3. `git add .` — **cek dulu `git status` sebelum ini**, pastikan tidak ada file yang tidak seharusnya ikut (`.env`, folder sampah semacam `_to_delete/`, dsb).
4. `git commit -m "pesan yang jelas"` lalu `git push origin staging`.
5. Di VPS, masuk ke `/var/www/staging.konexa`, jalankan `git pull origin staging`. Kalau ada perubahan `composer.lock`: `composer install`. Kalau ada migration baru: **cek dulu `.env` benar-benar `DB_DATABASE=konexa_staging`**, baru `php artisan migrate`. Kalau ada perubahan asset front-end: `npm run build`. Kalau ada perubahan config/`.env`: `php artisan config:clear`.
6. Test langsung di `https://staging.konexa.id` — pastikan fitur yang diubah benar-benar jalan, bukan cuma asumsi.
7. Kalau sudah oke di staging, baru merge ke `main`: lewat Pull Request di GitHub (lebih aman, bisa direview) — merge `staging` ke `main`, atau `git checkout main && git pull && git merge staging && git push origin main` kalau kerja sendiri tanpa PR.
8. Di VPS, masuk ke `/var/www/app.konexa` (production) — **WAJIB backup database production dulu** dan konfirmasi nama database yang benar sebelum lanjut (lihat section 6). Baru `git pull origin main`, ulangi step composer/migrate/npm/cache seperti di atas tapi untuk production.
9. Kalau ada perubahan job/queue, jalankan `php artisan queue:restart` di production supaya worker yang lagi jalan pakai kode lama ganti ke kode baru.
10. Verifikasi `app.konexa.id` normal setelah deploy.

Kalau perlu mundur (rollback) di production: `git log` cari commit sebelumnya, `git revert <commit-hash-merge>` (bukan `reset --hard`, supaya histori tetap utuh dan aman untuk branch yang sudah di-push), lalu ulangi step 8-10.

## 8. Task Menuju SaaS Siap Pakai (Audit 21 Agustus 2026)

Audit ini menjawab 5 titik yang diminta dicek untuk kesiapan SaaS multi-tenant (broadcast bisa jalan bersamaan banyak user). Kabar baiknya, pondasinya sudah cukup matang di beberapa bagian — tapi ada celah nyata yang perlu ditutup. Ringkasan aksi konkret dari audit ini sudah dipindahkan ke checklist prioritas di paling atas dokumen ini — bagian di bawah ini detail/alasan teknisnya.

### 7.1 Pengiriman WA harus berhenti otomatis begitu layanan/kuota habis

Sudah benar di `SendScheduledWaMessage`, `SendAutoReplyMessage`, `SendAiBotReply` — ketiganya sudah panggil `PackageLimitService::requireActivePackage()` sebelum kirim, dan `SendScheduledWaMessage` juga sudah `reserve()`/`release()` kuota `broadcast_send`.

Yang masih bolong:
- [ ] **`WaApiSendMessageController`** (endpoint API pihak ketiga, `POST` send message pakai `WaApiKey`) **tidak** memanggil `requireActivePackage()`, `reserve()`, atau `BroadcastThrottleService` sama sekali — langsung ke `InboxService::send()`. Pihak ketiga masih bisa terus kirim pesan walau package company sudah expired/kuota habis, dan tanpa jeda/jitter anti-ban. **Ini prioritas paling tinggi untuk ditutup** — lihat spesifikasi refactor lengkap di checklist prioritas poin 2 di atas.
- [ ] `SendAutoReplyMessage` dan `SendAiBotReply` cuma cek package aktif atau tidak, tapi TIDAK `reserve()`/`consume()` kuota apa pun — auto-reply dan balasan AI bot tidak mengurangi kuota `broadcast_send` (atau metric lain), jadi company bisa kirim auto-reply/AI bot tanpa batas walau kuota pesan terjadwalnya sudah habis. Perlu diputuskan: memang sengaja unlimited (kebijakan beda dari broadcast terjadwal), atau perlu metric kuota sendiri (`auto_reply_send`, `ai_bot_send`)?

### 7.2 Pembatasan device, kontak, broadcast, branch

Kerangka `PackageLimitService` + `LimitMetric` sudah generic dan dipakai konsisten: `ConnectDeviceController` (`device_count`), `PhoneBookController` (`contact_count`), `BranchOfficeController` (`branch_count`), `SendScheduledWaMessage` (`broadcast_send`). Kodenya sudah benar.

Yang masih perlu dikerjakan (operasional, bukan kode):
- [ ] Semua metric ini **fail-open** (unlimited) selama superadmin belum benar-benar mengisi `LimitMetric` + `PackageLimit` untuk suatu package. Cek di Superadmin > Package Limit: pastikan setiap package yang dijual sudah punya angka limit untuk `device_count`, `contact_count`, `branch_count`, `broadcast_send` — kalau belum diisi, company pemegang package itu punya kuota tak terbatas tanpa disadari siapa pun.
- [ ] Kalau poin 7.1 soal auto-reply/AI bot mau dibatasi juga, perlu metric key baru didaftarkan di sini juga.

### 7.3 Efisiensi form & refactor menu

- [ ] **Ditemukan 2 sistem UI berjalan bersamaan** (lihat koreksi di section 5) — ini kemungkinan besar sumber form yang tidak efisien/berulang, karena form-form di `layouts.dashboard` (mayoritas app) kemungkinan nulis markup Bootstrap manual berulang-ulang alih-alih pakai component reusable (component yang ada di `resources/views/components/` itu gaya Tailwind, kemungkinan tidak kepakai di sana). Task: putuskan satu sistem UI resmi (kemungkinan tetap Bootstrap admin theme karena itu yang dipakai mayoritas halaman), lalu buat set komponen Blade reusable BERGAYA BOOTSTRAP untuk form-input/button/card yang sering diulang.
- [ ] `resources/views/layouts/partials/menu.blade.php` sudah 738 baris / 48KB — daftar menu ditulis manual sebagai HTML panjang dengan banyak `@if`/`@php` inline untuk cek permission per item. Pertimbangkan refactor jadi data-driven (array/config daftar menu + permission-nya, di-loop lewat 1 partial/component kecil) supaya nambah/hapus menu tidak perlu edit HTML manual yang panjang dan gampang salah taruh permission check.
- [ ] Cek apakah form-form besar lain (CRUD company/user/package di Superadmin, dsb) sudah pakai pola partial `_form.blade.php` yang reusable (contoh yang sudah ada: `chat/message-schedules/_form.blade.php`, `chat/message-templates/_form.blade.php`) — kalau belum, standardisasi ke pola itu.

### 7.4 Keamanan pengiriman saldo, pengisian deposit & pembelian package

Ini bagian **paling matang** dari semua yang diaudit — sudah pakai pola production-grade:
- `WalletLedgerService::move()` — setiap perubahan saldo WAJIB lewat sini, pakai `lockForUpdate()` + `DB::transaction()`, setiap perubahan tercatat immutable di `LedgerEntry` (before/after balance).
- `WalletTransferController` — transfer antar wallet mengunci KEDUA wallet dalam urutan `id` yang konsisten (`orderBy('id')->lockForUpdate()`), mencegah deadlock kalau ada 2 transfer berlawanan arah bersamaan.
- `DuitkuCallbackController` (webhook top-up) — idempotent dengan benar: re-fetch status deposit pakai `lockForUpdate()` DI DALAM transaction sebelum diproses, jadi callback yang dikirim dobel oleh Duitku tidak akan mengkredit saldo 2x. Ada audit log & riwayat status juga.
- **`PackageCheckoutController::store()`** (beli package pakai saldo wallet) — diverifikasi 21 Agustus 2026, juga sudah rapi: pakai `Cache::lock("package-checkout:{user_id}", 15)` non-blocking sebagai guard submit-ganda (double-click/retry tidak akan memotong saldo 2x untuk 1 niat beli), kuota kode promo di-re-check pakai `lockForUpdate()` DI DALAM transaction (supaya kuota promo yang hampir habis tidak bisa "kejual lebih" waktu 2 checkout konkuren), dan pembayarannya sendiri tetap lewat `WalletLedgerService::debit()`. Lock selalu di-release lewat `finally`.
- **`VoucherRedeemController::store()`** (redeem kode aktivasi jadi subscription aktif) — juga diverifikasi 21 Agustus 2026: voucher yang di-redeem DAN voucher aktif sebelumnya (dipakai untuk logic chaining durasi) sama-sama di-`lockForUpdate()` di dalam satu transaction, supaya 2 kali redeem hampir bersamaan tidak menghasilkan window durasi yang overlap/salah hitung.

Task kecil yang masih ditemukan (bukan soal concurrency, soal konsistensi pola):
- [ ] `Superadmin\UserController::reset()` (fitur reset total data user) meng-update `wallet.balance` langsung ke 0 (`$user->wallet->update(['balance' => 0])`), TIDAK lewat `WalletLedgerService` — jadi perubahan saldo ini tidak tercatat sebagai `LedgerEntry` seperti perubahan saldo lainnya. Sudah di dalam `DB::transaction()` jadi aman dari sisi atomicity, tapi melanggar aturan "semua perubahan saldo WAJIB lewat WalletLedgerService" yang dipegang di semua tempat lain. Risiko rendah (ini fitur superadmin-only yang jarang dipakai), tapi sebaiknya diperbaiki untuk konsistensi — ganti jadi panggil `WalletLedgerService::debit()` sejumlah saldo yang ada sebelum di-nolkan.

Selebihnya tidak ada task mendesak — cuma dijaga: setiap fitur uang BARU (refund, komisi referral, dsb) WAJIB lewat `WalletLedgerService`, jangan bikin jalur update saldo baru yang terpisah.

### 7.5 Chatbot: query/update data aplikasi

`AiReplyGenerator` (`app/Services/AiBot/AiReplyGenerator.php`) saat ini murni text-in/text-out — sistem prompt + knowledge base statis (dokumen upload). Belum ada function-calling/tool-use untuk baca/ubah data aplikasi (cek status pesanan, lihat data CRM, update lead, dsb).

Ini task pengembangan baru (bukan bug):
- [ ] Tentukan use case konkret dulu (misal: chatbot jawab "status pesanan saya" via query ke data milik company itu).
- [ ] Tambah kemampuan function-calling di `AiProviderClientResolver`/`AiReplyGenerator` (provider AI modern umumnya sudah support tool/function calling).
- [ ] WAJIB scope ketat semua query/update tool ke `company_id` milik bot itu — jangan sampai chatbot bisa baca/ubah data company lain.
- [ ] Aksi UPDATE oleh chatbot sebaiknya tercatat di audit log (pola `AuditLog` yang sudah dipakai `DuitkuCallbackController`), jangan silent.

## 9. Audit Lanjutan — Teleios + g_backend (27 Agustus 2026)

Audit ini mencakup 4 sudut: concurrency & keamanan uang di Laravel, skalabilitas & isolasi multi-tenant di Laravel, keamanan/concurrency di `g_backend` (Go, belum pernah diaudit sedalam ini sebelumnya), dan titik sambung Laravel↔Go + endpoint pihak ketiga. Semua pola production-grade yang sudah diklaim section 7.1-7.4 (PackageLimitService, WalletLedgerService, BroadcastThrottleService, DispatchDueWaMessageSchedules, checkout/voucher, webhook Duitku) **terverifikasi ulang masih benar, tidak ada regresi**. Isolasi multi-tenant Laravel juga secara umum solid (pola scoping `company_id` konsisten). Temuan baru di bawah, diurutkan dari yang paling berisiko.

### 9.1 `g_backend`: tidak ada panic recovery (Risiko Tinggi)

Tidak ada satupun `recover()` di seluruh codebase Go. Recovery middleware Gin cuma melindungi goroutine per-HTTP-request — goroutine manual (`RestoreSessions`, connection watchdog, `eventHandler` whatsmeow, webhook fire-and-forget, dsb) tidak dilindungi sama sekali. Kalau satu device di company manapun memicu panic (misal nil pointer dari event WA yang tidak terduga), **SELURUH proses Go mati** — koneksi WA SEMUA company terputus bersamaan, bukan cuma company yang device-nya bermasalah. Diperparah karena proses production belum systemd (lihat Known Issues) — tidak auto-restart.

Rekomendasi: tambah `defer func(){ if r := recover(); r != nil { log... } }()` di setiap goroutine manual + di dalam `eventHandler` (per-event, bukan per-client), supaya 1 event/device yang error tidak menjatuhkan koneksi device lain. Tetap log panic-nya dengan jelas — `recover()` mencegah proses mati, tapi bukan pengganti perbaikan root cause bug-nya.

### 9.2 `g_backend`: tidak ada graceful shutdown (Risiko Sedang-Tinggi)

Tidak ada signal handling (SIGTERM/SIGINT) di `cmd/server/main.go`. Tiap restart/deploy, proses di-`kill` paksa — semua koneksi WA aktif terputus kasar (bukan logout bersih), termasuk risiko mid-write ke SQLite whatsmeow store. Sebaiknya dikerjakan bareng dengan setup systemd (checklist poin 13).

### 9.3 `WaApiKey`: `secret_key`/`token` disimpan plaintext (Risiko Tinggi)

`app/Models/WaApiKey.php` menyimpan kredensial API pihak ketiga polos di database, tidak di-hash. Kebocoran DB/backup langsung expose semua kredensial API semua company sekaligus. Rekomendasi: hash saat disimpan (pola sama seperti password), tampilkan nilai asli hanya sekali saat digenerate.

### 9.4 `POST /wa-api/v1/send-message`: tidak ada rate limiting sama sekali (Risiko Tinggi)

Dikonfirmasi bukan cuma belum lewat `BroadcastThrottleService` (sudah diketahui, lihat 7.1) — juga TIDAK ADA `throttle:` middleware Laravel maupun limiter apapun di sisi Go untuk endpoint ini. Otomatis ikut tertutup begitu sentralisasi `InboxService` (checklist poin 3) selesai — dicatat di sini supaya masuk scope test staging-nya.

### 9.5 Job/endpoint lain yang bypass proteksi kirim (Risiko Tinggi/Sedang)

Selain `WaApiSendMessageController` (7.1), ditemukan 2 jalur lain yang juga langsung panggil `InboxService` tanpa `requireActivePackage()`/`reserve()`/`BroadcastThrottleService`:
- **`app/Jobs/SendChatbotFlowMessages.php`** (Tinggi) — volume berpotensi tinggi (tiap step flow terpicu), risiko ban WA + kuota tembus tanpa batas untuk company yang package-nya sudah expired.
- **`app/Jobs/SendCsatSurvey.php`** (Sedang) — volume rendah (1x per percakapan resolved), risiko lebih kecil tapi pelanggaran pola yang sama.
- **`app/Http/Controllers/Api/GoogleFormWebhookController.php`** (Tinggi) — sama, DITAMBAH **tidak idempotent** (tidak ada dedup guard sama sekali): submit form yang terkirim dobel bisa bikin pesan WA dobel ke customer asli. Idempotency ini TIDAK otomatis ketutup oleh sentralisasi `InboxService` — perlu ditambah terpisah (checklist poin 4), ikuti pola `Cache::add` per submission id seperti `WaIncomingMessageWebhookController`.

Ketiganya (proteksi kuota/throttle-nya) otomatis terlindungi begitu checklist poin 3 selesai.

### 9.6 Index database untuk tabel log besar (Risiko Tinggi)

Tidak ada index `created_at` di 5 tabel log yang tumbuh terus lintas semua company dan selalu di-query `->latest()->paginate()`: `audit_logs`, `ledger_entries`, `history_user_login`, `voucher_histories`, `payment_transactions`. Kalau sudah jutaan baris, tiap buka halaman list-nya bakal full-table-scan + filesort. Sebagai perbandingan, `wa_conversations`/`wa_message_schedule_logs` sudah punya index yang tepat — tinggal terapkan pola yang sama ke 5 tabel ini (migration tambah index, risiko rendah untuk dikerjakan).

### 9.7 Temuan tambahan (Risiko Sedang/Rendah, dicatat untuk referensi — belum masuk checklist prioritas)

- **`WaApiKeyController::generate()/data()`** tidak cek device yang diminta benar milik company yang login sebelum bikin/baca baris `WaApiKey`. Saat ini masih "selamat" karena ada lapis kedua (`assertOwnership` di Go), tapi bukan defense-in-depth yang sesungguhnya — kalau logic Go itu berubah, celah ini langsung jadi IDOR beneran. (Sedang)
- Kombinasi `X-API-KEY` shared-secret di `g_backend` + CORS untuk browser — berpotensi bocor ke client-side kalau frontend Next.js benar-benar manggil Go backend langsung tanpa lewat Laravel. Perlu diklarifikasi dulu arsitekturnya sebelum jadi masalah nyata. (Sedang)
- `WaIncomingMessageWebhookController` log `$request->all()` — isi pesan customer + nomor telepon ter-log mentah, kontras dengan disiplin jalur outbound yang sudah tidak pernah log isi pesan. (Sedang)
- `DealController::index()` (papan Kanban CRM) fetch semua deal tanpa pagination; `ContactController::list()` cuma hard-cap 500 baris tanpa pagination sungguhan (kontak lama diam-diam tidak pernah muncul di list). (Sedang)
- Tidak ada timeout/retry eksplisit dari Laravel saat manggil Go backend (`InboxService`/`ConnectDeviceService`/`GolangAuthService`) — bergantung kontrak tak tertulis `sendSlotMaxWait=25s` di sisi Go, rapuh kalau salah satu sisi berubah. (Sedang)
- Dokumentasi `docs/api/wa-api-v1.openapi.json` untuk response 422 tidak cocok dengan response asli Laravel (`{"message":..., "errors":...}` vs yang didokumentasikan). (Sedang)
- JWT di `g_backend` (`auth-service.go`) tidak membatasi algoritma secara eksplisit (`jwt.WithValidMethods`) — defense-in-depth murah untuk dikerjakan. (Rendah)
- Nomor telepon/JID ter-log polos di beberapa tempat di `g_backend` (isi pesan sendiri sudah aman, tidak pernah di-log). (Rendah)
- Header `Content-Disposition` di `wa-media-controller.go` dibangun tanpa escape nama file. (Rendah)
- `trigger_count` di 3 tempat (`SendAutoReplyMessage`, `SendAiBotReply`, `GoogleFormWebhookController`) pakai baca-tulis non-atomik alih-alih `->increment()` — murni counter statistik tampilan, dampak kosmetik saja, bukan kuota/uang. (Rendah)
- `GoogleFormWebhookController` terima payload tanpa batas ukuran/jumlah field — relevan karena pola integrasi ini jadi TEMPLATE untuk integrasi pihak ketiga berikutnya (Visi Produk poin 8). (Rendah-Sedang)

### 9.8 Catatan: kenapa temuan terus berulang tiap audit

Dibahas 27 Agustus 2026 — dicatat supaya jadi konteks di sesi berikutnya, bukan sekadar keluhan. Pola "tiap audit selalu nemu yang baru" di 3 audit sejauh ini (14, 21, 27 Agustus) bukan soal audit kurang teliti — dua sebab konkretnya:

1. Cakupan tiap audit belum pernah sama persis. `g_backend` (Go) misalnya baru diperiksa pertama kali di audit 27 Agustus ini — bukan kelewat, memang belum pernah disentuh sebelumnya.
2. Proteksi kirim WA (`requireActivePackage`/`reserve`/`BroadcastThrottleService`) saat ini DITEMPEL manual di tiap pemanggil, bukan dipaksa lewat satu titik. Jadi tiap kali ada job/controller baru yang kirim WA (`SendChatbotFlowMessages`, `SendCsatSurvey`, `GoogleFormWebhookController`), developer harus INGAT copy proteksi itu ke file baru — kalau lupa atau fiturnya dibuat buru-buru, bolong lagi. Ini akar kenapa checklist poin 3 (sentralisasi ke `InboxService`) itu prioritas struktural, bukan cuma 1 dari 14 tugas: begitu semua jalur kirim WA WAJIB lewat 1 fungsi, kelas bug "jalur baru yang bypass proteksi" ini seharusnya berhenti muncul di audit-audit berikutnya, bukan lagi ditutup satu-satu tiap ketemu.

**Rekomendasi ke depan**: jalankan section 0 (Prinsip Dasar, 5 pertanyaan) SAAT menulis fitur/job baru yang menyentuh pengiriman WA — bukan menunggu audit berikutnya nemuin belakangan.


## 10. Known Issues (per audit 14 Agustus 2026, diverifikasi ulang 21 Agustus 2026)

Catatan untuk sesi mendatang — ini ditemukan lewat investigasi.

### Masih terbuka

1. **Dua sistem assignment chat berjalan sendiri-sendiri**: `WaContact.assigned_to` (lama, dipakai UI Inbox) vs `WaConversation.assigned_to` + status Chat Ops (baru, backend lengkap tapi belum ada UI). Perlu diputuskan mau disatukan ke arah mana.
2. Konsep "Category" untuk chat belum ada (baru ada Label). Pelacakan "assigned oleh siapa" (audit) juga belum ada di kedua sistem assignment.
3. Verifikasi `CACHE_STORE`/`QUEUE_CONNECTION` di `.env` produksi — pastikan bukan `file`/`array`/`sync` supaya rate limiter dan queue tetap aman untuk banyak worker. (Di `.env` lokal per 21 Agustus 2026 sudah `database` untuk keduanya — tetap cek ulang khusus di server production.)

### Sudah diperbaiki

- **Race condition di `DispatchDueWaMessageSchedules::claimAndDispatch()`** — sekarang sudah pakai `lockForUpdate()` + try/catch `QueryException`, pola yang sama dengan `PackageLimitService::lockOrCreateUsage()`. Diverifikasi 21 Agustus 2026.
- **`SendAutoReplyMessage` dan `SendAiBotReply` tidak melewati `BroadcastThrottleService`** — sekarang keduanya sudah memanggil `$throttle->attempt($deviceId, $companyId)` sebelum kirim, sama seperti `SendScheduledWaMessage`. Diverifikasi 21 Agustus 2026.
