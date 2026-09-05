@php
    // Locked-vs-free (pola "ina" project's University Album Photo):
    // create() mengunci jadwal_mata_pelajaran_id & pengajar_id kalau
    // datang dari query string (tombol "+ Add Student" di index
    // Pengajar). edit() TIDAK PERNAH mengunci (lihat
    // JadwalStudentController::edit()) -- $selectedMataPelajaranId/
    // $selectedPengajarId cuma pernah ke-set oleh create().
    //
    // Branch TIDAK pernah datang terkunci lewat drill-down (tombol
    // "+ Add Student" cuma bawa jadwal_mata_pelajaran_id & pengajar_id,
    // branch-nya otomatis mengikuti mata pelajaran yang dipilih di
    // JadwalStudentController::store()/update()) -- tapi tetap
    // ditampilkan sebagai select manual di sini supaya akses langsung
    // lewat menu sidebar "Student" bisa pilih branch sendiri tanpa
    // harus lewat index Branch/Mata Pelajaran dulu.
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? null);
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;

    $lockedMataPelajaranId = old('jadwal_mata_pelajaran_id', $selectedMataPelajaranId ?? null);
    $lockedMataPelajaran = $lockedMataPelajaranId ? $mataPelajarans->firstWhere('id', $lockedMataPelajaranId) : null;

    // Update 4 September 2026 (bug fix, laporan user: "pada form tambah
    // student tidak keluar ya jadwal pengajar nya"): Pengajar SEKARANG
    // cuma dikunci (disabled) kalau controller eksplisit bilang
    // `$pengajarLocked` true -- SEBELUMNYA field ini terkunci begitu
    // saja tiap kali `$selectedPengajarId` (atau `old('pengajar_id')`
    // dari redisplay validasi gagal) ada nilainya, termasuk di skenario
    // dropdown BEBAS yang cuma reload lewat query string `pengajar_id`
    // (bukan drill-down penuh) -- akibatnya field jadi disabled padahal
    // seharusnya tetap bisa diganti-ganti buat lihat checklist Pengajar
    // lain. create() cuma set true di skenario drill-down PENUH
    // (Kategori+Pengajar sekaligus dari tombol "+ Add Student" index
    // Pengajar); edit() SELALU false (tidak pernah mengunci, lihat
    // class docblock controller).
    $pengajarLocked = $pengajarLocked ?? false;
    $lockedPengajarId = $pengajarLocked ? old('pengajar_id', $selectedPengajarId ?? null) : null;
    $lockedPengajar = $lockedPengajarId ? $teamMembers->firstWhere('id', $lockedPengajarId) : null;
@endphp

<div class="mb-3">
    <label class="form-label">Branch (opsional)</label>
    @if ($lockedBranch && !$errors->has('branch_office_id'))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
        <div class="form-text">
            Student ini akan dikaitkan ke branch di atas.
            @if ($branchOffices->count() > 1)
                <a href="{{ route('jadwal.student.create') }}">Ganti branch</a>
            @endif
        </div>
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        {{-- Branch-locked member: satu-satunya opsi otomatis dipakai. --}}
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror">
            <option value="">- Ikuti Mata Pelajaran / Bidang -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $student->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Kalau tidak dipilih, ikut branch dari Mata Pelajaran / Bidang yang dipilih di bawah.</div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Mata Pelajaran / Bidang</label>
    @if ($lockedMataPelajaran && !$errors->has('jadwal_mata_pelajaran_id'))
        <input type="text" class="form-control" value="{{ $lockedMataPelajaran->name }}" disabled readonly>
        <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $lockedMataPelajaran->id }}">
        <div class="form-text">
            Student ini akan dikaitkan ke Mata Pelajaran / Bidang di atas.
            <a href="{{ route('jadwal.student.create') }}">Ganti Mata Pelajaran / Bidang</a>
        </div>
    @else
        {{--
            Fix 5 September 2026 (bukti user: Student "Vallery Jocelyn
            Nathania" -- Bidang tersimpan "Piano" tapi Jadwal Rutin
            aktifnya ternyata di bawah Kategori "Jazz" milik Bidang
            "Bass", karena checklist Kategori di panel bawah TIDAK
            PERNAH difilter ke Bidang yang dipilih di sini -- lihat
            JadwalStudentController::pengajarSlotsPanel()). Dropdown
            ini sekarang IKUT jadi pemicu reload (pola sama seperti
            dropdown Pengajar di bawah), supaya panel checklist selalu
            konsisten dengan Bidang yang lagi dipilih -- bukan cuma
            gabungan SEMUA Kategori Pengajar itu lintas Bidang.
            `previewMataPelajaranId` (dari query string, default ke
            Bidang tersimpan Student) HANYA ada di Edit Student --
            fallback ke `$selectedMataPelajaranId` (drill-down/reload
            Create Student) lalu ke Bidang tersimpan Student kalau
            keduanya kosong.
        --}}
        @php
            $mataPelajaranReloadUrl = ($student ?? null)
                ? route('jadwal.student.edit', $student->id)
                : route('jadwal.student.create', array_filter([
                    'jadwal_kategori_id' => $selectedKategoriId ?? null,
                ]));
            $mataPelajaranReloadSeparator = str_contains($mataPelajaranReloadUrl, '?') ? '&' : '?';
            $currentPengajarIdForReload = old('pengajar_id', $previewPengajarId ?? ($student->pengajar_id ?? null));
            $currentMataPelajaranIdSelected = old('jadwal_mata_pelajaran_id', $previewMataPelajaranId ?? $selectedMataPelajaranId ?? ($student->jadwal_mata_pelajaran_id ?? ''));
        @endphp
        <select name="jadwal_mata_pelajaran_id" class="form-select @error('jadwal_mata_pelajaran_id') is-invalid @enderror" required
            onchange="var u = '{{ $mataPelajaranReloadUrl }}' + '{{ $mataPelajaranReloadSeparator }}jadwal_mata_pelajaran_id=' + encodeURIComponent(this.value); @if($currentPengajarIdForReload) u += '&pengajar_id={{ $currentPengajarIdForReload }}'; @endif window.location.href = u;">
            <option value="">- Pilih Mata Pelajaran / Bidang -</option>
            @foreach ($mataPelajarans as $mp)
                <option value="{{ $mp->id }}" @selected($currentMataPelajaranIdSelected == $mp->id)>{{ $mp->name }}</option>
            @endforeach
        </select>
        @error('jadwal_mata_pelajaran_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Ganti Bidang akan memuat ulang daftar Kategori/jadwal Pengajar di bawah supaya sesuai Bidang ini.</div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Pengajar</label>
    @if ($lockedPengajar && !$errors->has('pengajar_id'))
        <input type="text" class="form-control" value="{{ $lockedPengajar->name }}" disabled readonly>
        <input type="hidden" name="pengajar_id" value="{{ $lockedPengajar->id }}">
        <div class="form-text">
            Student ini akan dikaitkan ke Pengajar di atas.
            <a href="{{ route('jadwal.student.create', array_filter(['jadwal_mata_pelajaran_id' => $lockedMataPelajaranId])) }}">Ganti Pengajar</a>
        </div>
    @else
        {{--
            Update 4 September 2026 (permintaan user, revisi kedua, lalu
            bug fix lanjutan): dropdown INI SENDIRI yang jadi pemicu
            reload panel ketersediaan Pengajar (lihat
            JadwalStudentController::edit()/pengajarSlotsPanel() DAN
            create()) -- SATU dropdown saja, bukan dropdown terpisah
            untuk "preview", berlaku di KEDUA form (awalnya cuma Edit
            Student, sekarang Tambah Student juga ikut, supaya konsisten
            -- lihat komentar $pengajarLocked di atas). Reload target-nya
            beda per form: Edit balik ke `jadwal.student.edit` (entity
            sudah ada); Create balik ke `jadwal.student.create` sambil
            membawa `jadwal_mata_pelajaran_id`/`jadwal_kategori_id` yang
            mungkin masih terkunci (supaya konteks itu tidak hilang
            waktu ganti Pengajar). `($student ?? null)` truthy HANYA di
            Edit (create selalu meng-override `$student` jadi null lewat
            `@include('_form', ['student' => null])`). Reload murni GET
            -- TIDAK menyimpan apa pun, field lain yang mungkin sedang
            diisi admin akan ke-reset ke nilai tersimpan (trade-off yang
            sama seperti link "Ganti Pengajar"/"Ganti Mata Pelajaran /
            Bidang" di atas, pola yang sudah ada).
        --}}
        @php
            $pengajarReloadUrl = ($student ?? null)
                ? route('jadwal.student.edit', $student->id)
                : route('jadwal.student.create', array_filter([
                    'jadwal_mata_pelajaran_id' => $selectedMataPelajaranId ?? null,
                    'jadwal_kategori_id' => $selectedKategoriId ?? null,
                ]));
            // create() bisa menghasilkan URL yang SUDAH punya query
            // string sendiri (jadwal_mata_pelajaran_id/jadwal_kategori_id)
            // -- pakai `&` kalau begitu, `?` kalau belum ada query sama
            // sekali (selalu kasusnya di edit()), supaya tidak jadi dua
            // "?" yang malah bikin `pengajar_id` gagal ke-parse.
            $pengajarReloadSeparator = str_contains($pengajarReloadUrl, '?') ? '&' : '?';
        @endphp
        <select name="pengajar_id" class="form-select @error('pengajar_id') is-invalid @enderror" required
            onchange="window.location.href = '{{ $pengajarReloadUrl }}' + (this.value ? ('{{ $pengajarReloadSeparator }}pengajar_id=' + encodeURIComponent(this.value)) : '')">
            <option value="">- Pilih Pengajar -</option>
            @foreach ($teamMembers as $member)
                <option value="{{ $member->id }}" @selected(old('pengajar_id', $previewPengajarId ?? ($student->pengajar_id ?? '')) == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('pengajar_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

{{--
    Update 4 September 2026 (permintaan user: "tambahkan kolom ruangan
    pada edit student ya dan add student juga") -- satu dropdown Ruangan
    (opsional) yang berlaku untuk SEMUA jadwal murid ini (bukan per
    Kategori/tab), sama semangatnya dengan field Pengajar di atas: satu
    murid = satu Ruangan yang sama untuk semua Kategori yang dia ambil.
    Diterapkan ke Jadwal Rutin BARU yang dibuat dari checklist di bawah
    MAUPUN (khusus Edit Student) baris yang sudah ada & tetap dipakai --
    lihat JadwalStudentController::update()'s reconciliation Ruangan.
    Sesi (Jadwal Kelas) yang sudah ter-generate bulan ini TIDAK ikut
    berubah Ruangannya (konsisten dengan kebijakan jam/hari di atas).

    $ruangans (dari JadwalStudentController::formData()) di-scope ke
    branch yang sama seperti Mata Pelajaran/Pengajar; App\Models\
    JadwalStudent sendiri TIDAK menyimpan Ruangan (lihat komentar
    $selectedRuanganId/$ruanganMixed di JadwalStudentController::edit())
    -- nilai "sekarang" DI-DERIVE dari Jadwal Rutin aktif murid ini, null
    kalau belum pernah diisi atau kalau ternyata beda-beda antar baris
    (`$ruanganMixed`, admin perlu pilih satu untuk menyeragamkan).
--}}
<div class="mb-3">
    <label class="form-label">Ruangan (opsional)</label>
    @if ($ruanganMixed ?? false)
        <div class="alert alert-warning py-2 px-3 mb-2 small">
            <i class="ri-error-warning-line"></i> Murid ini punya lebih dari satu Ruangan berbeda di jadwalnya saat ini. Pilih satu Ruangan di bawah untuk menyeragamkan semua jadwalnya, atau biarkan "- Tanpa Ruangan -" untuk mengosongkan semuanya.
        </div>
    @endif
    <select name="jadwal_ruangan_id" class="form-select @error('jadwal_ruangan_id') is-invalid @enderror">
        <option value="">- Tanpa Ruangan -</option>
        @foreach ($ruangans as $r)
            <option value="{{ $r->id }}" @selected(old('jadwal_ruangan_id', $selectedRuanganId ?? '') == $r->id)>
                {{ $r->name }}{{ ($branchOffices->count() > 1 && $r->branchOffice) ? ' -- '.$r->branchOffice->name : '' }}
            </option>
        @endforeach
    </select>
    @error('jadwal_ruangan_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Diterapkan ke semua Jadwal Rutin murid ini. Ruangan yang sudah dipakai murid lain di jam yang bentrok akan dilewati/tidak diubah (dilaporkan setelah Simpan).</div>
</div>

<div class="mb-3">
    <label class="form-label">Nama Student</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $student->name ?? '') }}" placeholder="Nama murid" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">No. HP Orang Tua (opsional)</label>
        <input type="text" name="parent_phone_number" class="form-control @error('parent_phone_number') is-invalid @enderror"
            value="{{ old('parent_phone_number', $student->parent_phone_number ?? '') }}" placeholder="Contoh: 6281234567890">
        @error('parent_phone_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Dipakai untuk pengingat &amp; notifikasi WhatsApp (kalau layanan Chat aktif).</div>
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">No. HP Murid (opsional)</label>
        <input type="text" name="student_phone_number" class="form-control @error('student_phone_number') is-invalid @enderror"
            value="{{ old('student_phone_number', $student->student_phone_number ?? '') }}" placeholder="Contoh: 6281234567890">
        @error('student_phone_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $student->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
