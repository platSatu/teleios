@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($mataPelajaran)
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.branch.index') }}">Branch</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('jadwal.mata-pelajaran.index') }}">Mata Pelajaran / Bidang</a></li>
                    @if($gradeId ?? null)
                        <li class="breadcrumb-item"><a href="{{ route('jadwal.pengajar.index', ['jadwal_grade_id' => $gradeId]) }}">{{ $mataPelajaran->name }}</a></li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $mataPelajaran->name }}</li>
                    @endif
                    @if($pengajar)
                        <li class="breadcrumb-item active" aria-current="page">{{ $pengajar->name }}</li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">Student</li>
                </ol>
            </nav>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Student{{ $pengajar ? ' — '.$pengajar->name : '' }}</h4>
                        <p class="text-muted mb-0">Daftar murid. Sesuai company/branch Anda — bukan seluruh user.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($gradeId ?? null)
                            <a href="{{ route('jadwal.pengajar.index', ['jadwal_grade_id' => $gradeId]) }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Pengajar
                            </a>
                        @elseif($mataPelajaran)
                            <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="btn btn-light">
                                <i class="ri-arrow-left-line"></i> Kembali ke Mata Pelajaran / Bidang
                            </a>
                        @endif
                        <a href="{{ route('jadwal.student.create', array_filter(['jadwal_mata_pelajaran_id' => $mataPelajaranId, 'pengajar_id' => $pengajarId, 'jadwal_grade_id' => $gradeId ?? null])) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Student
                        </a>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    @if($mataPelajaranId)
                        <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $mataPelajaranId }}">
                    @endif
                    @if($pengajarId)
                        <input type="hidden" name="pengajar_id" value="{{ $pengajarId }}">
                    @endif
                    @if($gradeId ?? null)
                        <input type="hidden" name="jadwal_grade_id" value="{{ $gradeId }}">
                    @endif
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama student..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    @if(request('search') || request('status'))
                        <a href="{{ route('jadwal.student.index', array_filter(['jadwal_mata_pelajaran_id' => $mataPelajaranId, 'pengajar_id' => $pengajarId, 'jadwal_grade_id' => $gradeId ?? null])) }}" class="btn btn-light">Reset</a>
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                @unless($mataPelajaran)
                                    <th>Mata Pelajaran / Bidang</th>
                                @endunless
                                @unless($pengajar)
                                    <th>Pengajar</th>
                                @endunless
                                <th>Kategori</th>
                                <th>Branch</th>
                                <th>No. HP</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $student)
                                <tr>
                                    <td class="fw-semibold">{{ $student->name }}</td>
                                    @unless($mataPelajaran)
                                        <td>
                                            {{-- Fix 14 September 2026 (laporan user via screenshot: kolom
                                            ini bisa beda dengan Jadwal Kelas/index Pengajar) -- SAMA POLA
                                            dengan "Kategori" di bawah, DI-DERIVE dari Jadwal Rutin aktif
                                            murid ini, BUKAN field jadwal_mata_pelajaran_id tersimpan
                                            langsung di baris Student (bisa basi, lihat docblock
                                            JadwalCountsService::activeMataPelajaranNamesByStudent()). --}}
                                            @forelse($student->mata_pelajaran_names as $mataPelajaranName)
                                                <span class="badge bg-light text-dark border fw-normal">{{ $mataPelajaranName }}</span>
                                            @empty
                                                <span class="text-muted">-</span>
                                            @endforelse
                                        </td>
                                    @endunless
                                    @unless($pengajar)
                                        <td>
                                            {{-- Fix 14 September 2026 -- sama seperti kolom Mata
                                            Pelajaran/Bidang tepat di atas, lihat docblock
                                            JadwalCountsService::activePengajarNamesByStudent(). --}}
                                            @forelse($student->pengajar_names as $pengajarName)
                                                <span class="badge bg-light text-dark border fw-normal">{{ $pengajarName }}</span>
                                            @empty
                                                <span class="text-muted">-</span>
                                            @endforelse
                                        </td>
                                    @endunless
                                    <td>
                                        {{-- Update 4 September 2026: "Kategori" DI-DERIVE dari Jadwal
                                        Rutin aktif murid ini (lihat JadwalStudentController::index()),
                                        BUKAN field tersimpan langsung -- kalau murid belum punya Jadwal
                                        Rutin aktif sama sekali, belum ada Kategori yang bisa ditentukan. --}}
                                        @forelse($student->kategori_names as $kategoriName)
                                            <span class="badge bg-light text-dark border fw-normal">{{ $kategoriName }}</span>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                    </td>
                                    <td>{{ $student->branchOffice->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        @if($student->parent_phone_number || $student->student_phone_number)
                                            <span title="{{ $student->parent_phone_number ? 'Orang tua' : 'Murid' }}">{{ $student->parent_phone_number ?: $student->student_phone_number }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $student->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $student->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        {{-- Update 4 September 2026 (permintaan user): tombol "Jadwal
                                        Rutin" & "Add Jadwal" DIHAPUS dari sini -- keduanya jadi jalan
                                        pintas yang bisa dipakai bikin jadwal murid tanpa lewat checklist
                                        ketersediaan Pengajar di halaman Edit (lihat
                                        JadwalStudentController::pengajarSlotsPanel()). Admin yang mau
                                        kelola Jadwal Rutin/Jadwal Kelas tetap bisa lewat menu
                                        sidebar-nya sendiri -- cuma shortcut per-baris ini yang hilang,
                                        Aksi di sini disederhanakan jadi Edit + Delete saja, sama
                                        seperti pola baris Pengajar (lihat jadwal-pengajar/index.blade.php). --}}
                                        {{-- Daftarkan Tagihan (7 Okt 2026): branch ikut Student, nominal awal dari harga Grade. --}}
                                        @if($canTagihan && $student->status === 'active')
                                            @php $langganan = $student->tagihanPelanggan?->categoryPelanggan->first(); @endphp
                                            <button type="button" class="btn btn-sm {{ $langganan ? 'btn-success' : 'btn-outline-success' }}"
                                                data-bs-toggle="modal" data-bs-target="#studentTagihanModal"
                                                data-url="{{ route('jadwal.student.tagihan', $student->id) }}"
                                                data-name="{{ $student->name }}"
                                                data-phone="{{ $student->parent_phone_number ?: $student->student_phone_number }}"
                                                data-branch-id="{{ $student->branch_office_id }}"
                                                data-branch-name="{{ $student->branchOffice->name ?? '' }}"
                                                data-category="{{ $langganan?->tagihan_category_id }}"
                                                data-amount="{{ $langganan ? (int) $langganan->nominal_override : ($tagihanAmounts[$student->id] ?? 0) }}"
                                                data-grade-amount="{{ $tagihanAmounts[$student->id] ?? 0 }}"
                                                data-kirim="{{ $student->tagihanPelanggan?->kirim_link_otomatis ?? true ? 1 : 0 }}"
                                                title="{{ $langganan ? 'Tagihan: '.($langganan->category->name ?? '-').' · Rp '.number_format((int) $langganan->nominal_override, 0, ',', '.') : 'Daftarkan murid ini ke Tagihan bulanan' }}">
                                                <i class="ri-wallet-3-line"></i> {{ $langganan ? 'Tagihan aktif' : 'Daftarkan Tagihan' }}
                                            </button>
                                        @endif
                                        <a href="{{ route('jadwal.student.edit', $student->id) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        {{-- Update 4 September 2026 (permintaan user, laporan "fungsi
                                        delete di table student tidak berfungsi"): tombol Hapus lama
                                        SELALU gagal kalau murid sudah punya sesi Jadwal Kelas (FK
                                        restrictOnDelete, lihat migration perbaikannya). Sekarang ada 2
                                        aksi terpisah: "Nonaktifkan" (aman, status=inactive, riwayat
                                        jadwal & fee tetap tersimpan tapi tidak lagi ikut dihitung di
                                        laporan -- lihat JadwalStudentController::deactivate()) dan
                                        "Hapus Total" (permanen, ikut menghapus SELURUH riwayat jadwal &
                                        fee-nya, tidak bisa dibatalkan). Tombol Nonaktifkan hanya
                                        muncul kalau murid masih aktif -- tidak ada gunanya nonaktifkan
                                        murid yang sudah inactive. --}}
                                        @if($student->status === 'active')
                                            <form action="{{ route('jadwal.student.deactivate', $student->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Nonaktifkan Student ini? Riwayat jadwal & fee-nya tetap tersimpan, tapi tidak lagi ikut dihitung di laporan.');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-light text-warning" title="Nonaktifkan"><i class="ri-pause-circle-line"></i></button>
                                            </form>
                                        @endif
                                        <form action="{{ route('jadwal.student.destroy', $student->id) }}" method="POST" class="d-inline" onsubmit="return confirm('HAPUS TOTAL Student ini beserta SELURUH riwayat jadwal & fee-nya? Tindakan ini TIDAK BISA DIBATALKAN. Kalau cuma ingin murid ini tidak aktif lagi tapi datanya tetap tersimpan, gunakan tombol Nonaktifkan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus Total"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 6 + ($mataPelajaran ? 0 : 1) + ($pengajar ? 0 : 1) }}" class="text-center text-muted py-4">Belum ada Student. Klik "Tambah Student" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $students->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@if($canTagihan)
{{-- Popup "Daftarkan Tagihan" -- lihat App\Http\Controllers\Jadwal\JadwalStudentTagihanController. --}}
<div class="modal fade" id="studentTagihanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="studentTagihanForm" action="">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title mb-0"><i class="ri-wallet-3-line"></i> Tagihan Bulanan: <span id="stName"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1">Branch</label>
                        <input type="text" class="form-control form-control-sm" id="stBranch" disabled>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">No. HP (penerima link)</label>
                        <input type="text" class="form-control form-control-sm" id="stPhone" disabled>
                    </div>
                </div>
                <div id="stNoCategory" class="alert alert-warning small py-2" style="display:none;">
                    Branch ini belum punya Kategori Tagihan.
                    <a href="#" id="stCreateCategory" class="alert-link">Buat Kategori Tagihan</a> dulu, lalu buka lagi popup ini.
                </div>
                <div id="stFields">
                    <div class="mb-3">
                        <label class="form-label">Kategori Tagihan</label>
                        <select name="tagihan_category_id" id="stCategory" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal per bulan (Rp)</label>
                        <input type="number" name="nominal" id="stAmount" class="form-control" min="0" step="1" required>
                        <div class="form-text" id="stAmountHint"></div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kirim_link_otomatis" value="1" id="stKirim">
                        <label class="form-check-label" for="stKirim">Kirim link tagihan otomatis lewat WhatsApp</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success btn-sm" id="stSubmit">Simpan</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var categories = @json($tagihanCategoriesByBranch);
    var createUrl = @json(route('tagihan.category.create'));
    var modal = document.getElementById('studentTagihanModal');
    var el = function (id) { return document.getElementById(id); };
    var rupiah = function (n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); };

    modal.addEventListener('show.bs.modal', function (event) {
        var b = event.relatedTarget;
        if (!b) return;
        var d = b.dataset, list = categories[d.branchId] || [];
        el('studentTagihanForm').action = d.url;
        el('stName').textContent = d.name;
        el('stBranch').value = d.branchName || '-';
        el('stPhone').value = d.phone || '-';
        el('stAmount').value = d.amount;
        el('stAmountHint').textContent = 'Dari harga bulanan Grade yang diambil: ' + rupiah(d.gradeAmount) + '. Boleh diubah (diskon/beasiswa).';
        el('stKirim').checked = d.kirim === '1';

        var select = el('stCategory');
        select.innerHTML = '';
        list.forEach(function (c) { select.appendChild(new Option(c.name, c.id, false, c.id === d.category)); });

        var empty = list.length === 0;
        el('stNoCategory').style.display = empty ? '' : 'none';
        el('stFields').style.display = empty ? 'none' : '';
        el('stSubmit').disabled = empty;
        el('stCreateCategory').href = createUrl + '?branch_office_id=' + encodeURIComponent(d.branchId || '');
    });
})();
</script>
@endif
@endsection
