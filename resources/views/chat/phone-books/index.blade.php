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

        {{-- Import result — flashed by PhoneBookController::import(). --}}
        @if (session('importResult'))
            @php $importResult = session('importResult'); @endphp
            <div class="alert {{ empty($importResult['errors']) ? 'alert-success' : 'alert-warning' }}">
                <div class="fw-semibold mb-1">
                    Import selesai: {{ count($importResult['created']) }} kontak berhasil dibuat{{ empty($importResult['errors']) ? '.' : ', ' . count($importResult['errors']) . ' baris gagal.' }}
                </div>
                @if (!empty($importResult['errors']))
                    <details open>
                        <summary class="small text-muted" style="cursor: pointer;">Lihat baris yang gagal</summary>
                        <ul class="small mb-0 mt-2">
                            @foreach ($importResult['errors'] as $err)
                                <li>Baris {{ $err['row'] }}{{ $err['name'] ? ' (' . $err['name'] . ')' : '' }}: {{ implode(' ', $err['messages']) }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Buku Telepon</h4>
                        <p class="text-muted mb-0">Kontak yang kamu kelola sendiri — dipakai sebagai tujuan pengiriman WA Template / Pesan Terjadwal.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('chat.phone-books.export') }}" class="btn btn-light">
                            <i class="ri-download-2-line"></i> Export
                        </a>
                        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#importPhoneBookModal">
                            <i class="ri-upload-2-line"></i> Import
                        </button>
                        <a href="{{ route('chat.phone-books.create') }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Kontak
                        </a>
                        {{-- "Hapus Semua" (bahasa lain buat resetAll() di controller) selalu
                             menghapus SELURUH Buku Telepon (company/branch scope), TIDAK
                             ngikutin filter pencarian/kelompok/status yang lagi aktif —
                             makanya label & konfirmasinya sengaja ditulis tegas begitu,
                             supaya tidak dikira "hapus yang lagi tampil di filter ini". --}}
                        <form action="{{ route('chat.phone-books.reset-all') }}" method="POST" class="m-0 js-danger-confirm-form"
                            data-confirm="Hapus SEMUA kontak di Buku Telepon (bukan cuma yang lagi tampil di filter ini)?&#10;&#10;Semua kontak akan terhapus PERMANEN dan tidak bisa dikembalikan. Lanjutkan?"
                            data-loading-text="Menghapus...">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="ri-delete-bin-line js-btn-icon"></i>
                                <span class="js-btn-label">Hapus Semua Kontak</span>
                            </button>
                        </form>
                    </div>
                </div>

                <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="input-group" style="max-width: 260px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, nomor, email..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    </div>
                    <select name="wa_category_phone_book_id" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Kelompok</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('wa_category_phone_book_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select" style="max-width: 160px;" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="active" @selected(request('status') == 'active')>Active</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Inactive</option>
                    </select>
                    <select name="blacklist" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">Blacklist + Normal</option>
                        <option value="0" @selected(request('blacklist') === '0')>Bukan Blacklist</option>
                        <option value="1" @selected(request('blacklist') === '1')>Blacklist Saja</option>
                    </select>
                    @if(request('search') || request('wa_category_phone_book_id') || request('status') || request('blacklist') !== null)
                        <a href="{{ route('chat.phone-books.index') }}" class="btn btn-light">Reset</a>
                    @endif

                    {{-- Muncul cuma pas satu Kelompok spesifik lagi difilter — "reset per
                         grup/category" dari PhoneBookController::resetByCategory(), beda
                         dari tombol "Reset" di atas (yang cuma bersihin filter, bukan
                         hapus data). --}}
                    @if (request()->filled('wa_category_phone_book_id'))
                        @php
                            $selectedCategory = $categories->firstWhere('id', request('wa_category_phone_book_id'));
                        @endphp
                        @if ($selectedCategory)
                            <form action="{{ route('chat.phone-books.reset-category', $selectedCategory->id) }}" method="POST" class="m-0 js-danger-confirm-form"
                                data-confirm="Hapus semua kontak di kelompok &quot;{{ $selectedCategory->name }}&quot;?&#10;&#10;Kontak di kelompok ini akan terhapus PERMANEN dan tidak bisa dikembalikan. Lanjutkan?"
                                data-loading-text="Menghapus...">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="ri-delete-bin-line js-btn-icon"></i>
                                    <span class="js-btn-label">Hapus Kontak di "{{ $selectedCategory->name }}"</span>
                                </button>
                            </form>
                        @endif
                    @endif
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0" style="min-width: 1000px;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 160px;">Nama</th>
                                <th style="min-width: 140px;">Nomor</th>
                                <th style="min-width: 160px;">Email</th>
                                <th style="min-width: 140px;">Kelompok</th>
                                <th style="min-width: 120px;">Branch</th>
                                <th style="min-width: 120px;">Status</th>
                                <th class="text-end" style="min-width: 160px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($phoneBooks as $phoneBook)
                                <tr>
                                    <td class="fw-semibold">
                                        {{ $phoneBook->name }}
                                        @if ($phoneBook->is_blacklisted)
                                            <span class="badge bg-danger-subtle text-danger ms-1" title="{{ $phoneBook->blacklist_reason }}">
                                                <i class="ri-forbid-line"></i> Blacklist
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $phoneBook->phone }}</td>
                                    <td>{{ $phoneBook->email ?? '-' }}</td>
                                    <td>{{ $phoneBook->category->name ?? '-' }}</td>
                                    <td>{{ $phoneBook->branchOffice->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $phoneBook->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $phoneBook->status }}</span>
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <div class="d-flex flex-nowrap justify-content-end gap-1">
                                            @if ($phoneBook->wa_customer_id)
                                                <a href="{{ route('chat.contacts.show', ['customer' => $phoneBook->wa_customer_id]) }}" class="btn btn-sm btn-light" title="Lihat Customer 360">
                                                    <i class="ri-user-3-line"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('chat.phone-books.edit', $phoneBook->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            @if ($phoneBook->is_blacklisted)
                                                <form action="{{ route('chat.phone-books.unblacklist', $phoneBook->id) }}" method="POST" onsubmit="return confirm('Keluarkan nomor ini dari blacklist?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light text-success" title="Keluarkan dari Blacklist">
                                                        <i class="ri-check-line"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('chat.phone-books.blacklist', $phoneBook->id) }}" method="POST" class="js-blacklist-form">
                                                    @csrf
                                                    <input type="hidden" name="reason" class="js-blacklist-reason">
                                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Masukkan ke Blacklist">
                                                        <i class="ri-forbid-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('chat.phone-books.destroy', $phoneBook->id) }}" method="POST" onsubmit="return confirm('Hapus kontak ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada kontak. Klik "Tambah Kontak" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $phoneBooks->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importPhoneBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('chat.phone-books.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import Kontak dari Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Upload file <code>.xlsx</code>/<code>.xls</code>/<code>.csv</code> untuk menambah banyak
                        kontak sekaligus.
                    </p>

                    {{-- Inline format example — mirrors the columns/example row baked into
                         PhoneBookImportTemplateExport, so the format is visible without having
                         to download anything first. --}}
                    <div class="border rounded p-2 mb-3 bg-light-subtle">
                        <div class="small fw-semibold mb-2">Contoh format kolom:</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-2 bg-white" style="min-width: 640px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>nama <span class="text-danger">*</span></th>
                                        <th>nomor_telepon <span class="text-danger">*</span></th>
                                        <th>email</th>
                                        <th>kelompok <span class="text-danger">*</span></th>
                                        <th>branch</th>
                                        <th>status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Budi Santoso</td>
                                        <td>081234567890</td>
                                        <td>budi@contoh.com</td>
                                        <td>Pelanggan VIP</td>
                                        <td>Cabang Jakarta</td>
                                        <td>active</td>
                                    </tr>
                                    <tr>
                                        <td>Siti Aminah</td>
                                        <td>6281298765432</td>
                                        <td class="text-muted">(kosong)</td>
                                        <td>Pelanggan Reguler</td>
                                        <td class="text-muted">(kosong)</td>
                                        <td class="text-muted">(kosong)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ul class="small text-muted mb-0 ps-3">
                            <li><span class="text-danger">*</span> wajib diisi — <code>email</code>, <code>branch</code>, <code>status</code> opsional (status default <code>active</code>).</li>
                            <li><code>kelompok</code> harus sudah ada di menu Kelompok pada company Anda; nama tidak cocok = baris ditolak.</li>
                            <li><code>status</code> hanya boleh <code>active</code> atau <code>inactive</code>.</li>
                            <li>Nomor boleh format <code>08xx</code> atau <code>62xx</code>, otomatis dinormalkan.</li>
                        </ul>
                    </div>

                    <a href="{{ route('chat.phone-books.import-template') }}" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="ri-file-download-line"></i> Download Template (terisi Kelompok &amp; Branch Anda)
                    </a>

                    <div class="mb-1">
                        <label for="import_file_phone_book" class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="file" id="import_file_phone_book" class="form-control @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Maks 2MB, maks {{ \App\Imports\PhoneBookImport::MAX_ROWS }} kontak per file.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="ri-upload-2-line"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // "Hapus Semua Kontak" / "Hapus Kontak di [Kelompok]" — konfirmasi lewat
    // data-confirm (bukan inline onsubmit biasa) karena setelah dikonfirmasi
    // kita juga perlu MENONAKTIFKAN tombolnya + ganti jadi spinner "Menghapus...",
    // supaya jelas ke user request-nya lagi diproses (bulk delete company-wide
    // bisa makan waktu lebih dari sedetik kalau kontaknya banyak) — request
    // sebelumnya sempat kelihatan "diam saja" tanpa ini, jadi dikira gagal.
    document.querySelectorAll('.js-danger-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var message = form.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?';

            if (!confirm(message)) {
                e.preventDefault();
                return;
            }

            var button = form.querySelector('button[type="submit"]');
            if (!button) return;

            button.disabled = true;

            var icon = button.querySelector('.js-btn-icon');
            var label = button.querySelector('.js-btn-label');
            var loadingText = form.getAttribute('data-loading-text') || 'Memproses...';
            var spinner = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>';

            if (icon) icon.style.display = 'none';

            if (label) {
                label.innerHTML = spinner + loadingText;
            } else {
                button.innerHTML = spinner + loadingText;
            }
        });
    });

    // Blacklist reason is optional — a plain confirm() (not a full
    // modal) keeps this a one-click action from the table, same
    // lightweight treatment as the destroy forms right next to it.
    document.querySelectorAll('.js-blacklist-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Masukkan nomor ini ke blacklist? Nomor blacklist tidak akan muncul di pilihan tujuan pengiriman.')) {
                e.preventDefault();
                return;
            }
            var reason = prompt('Alasan blacklist (opsional):', '');
            form.querySelector('.js-blacklist-reason').value = reason || '';
        });
    });

    @if ($errors->has('file'))
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('importPhoneBookModal');
            if (modalEl && window.bootstrap) {
                new bootstrap.Modal(modalEl).show();
            }
        });
    @endif
</script>
@endsection
