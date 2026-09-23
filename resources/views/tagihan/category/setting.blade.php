@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-9">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-1">Setting Tagihan</h4>
                <p class="text-muted small mb-0">{{ $category->name }}</p>
            </div>
            <a href="{{ route('tagihan.category.edit', $category->id) }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>

        <div class="card">
            <div class="card-header bg-transparent">
                <ul class="nav nav-tabs card-header-tabs" id="settingTagihanTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-denda-btn" data-bs-toggle="tab" data-bs-target="#tab-denda" type="button" role="tab">
                            Denda
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-pengingat-btn" data-bs-toggle="tab" data-bs-target="#tab-pengingat" type="button" role="tab">
                            Pengingat
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-invoice-btn" data-bs-toggle="tab" data-bs-target="#tab-invoice" type="button" role="tab">
                            Invoice &amp; Notifikasi
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="settingTagihanTabContent">

                    {{-- TAB 1: DENDA --}}
                    <div class="tab-pane fade show active" id="tab-denda" role="tabpanel">
                        <p class="text-muted small">Pilih salah satu cara hitung denda keterlambatan buat kategori ini.</p>

                        <form action="{{ route('tagihan.category.setting.denda', $category->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <div class="btn-group w-100" role="group" id="dendaModeSwitch">
                                    <input type="radio" class="btn-check" name="denda_mode" id="mode-flat" value="flat"
                                        {{ old('denda_mode', $category->denda_mode) === 'flat' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-primary" for="mode-flat">Flat</label>

                                    <input type="radio" class="btn-check" name="denda_mode" id="mode-persentase" value="persentase"
                                        {{ old('denda_mode', $category->denda_mode) === 'persentase' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-primary" for="mode-persentase">Persentase</label>

                                    <input type="radio" class="btn-check" name="denda_mode" id="mode-persentase_flat" value="persentase_flat"
                                        {{ old('denda_mode', $category->denda_mode) === 'persentase_flat' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-primary" for="mode-persentase_flat">Persentase + Flat</label>
                                </div>
                                @error('denda_mode', 'denda')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            {{-- Flat: nominal tetap --}}
                            <div class="denda-mode-fields" data-mode="flat">
                                <div class="mb-3">
                                    <label class="form-label">Nominal Denda (Rp)</label>
                                    <input type="number" step="1" min="0" name="denda_flat_amount" class="form-control @error('denda_flat_amount', 'denda') is-invalid @enderror"
                                        value="{{ old('denda_flat_amount', $category->denda_flat_amount) }}" placeholder="Contoh: 10000">
                                    <div class="form-text">Dikenakan sekali, berapa pun lama keterlambatannya.</div>
                                    @error('denda_flat_amount', 'denda')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            {{-- Persentase: % per hari/bulan --}}
                            <div class="denda-mode-fields" data-mode="persentase">
                                <div class="mb-3">
                                    <label class="form-label">Persentase (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="denda_persen" class="form-control @error('denda_persen', 'denda') is-invalid @enderror"
                                        value="{{ old('denda_persen', $category->denda_persen) }}" placeholder="Contoh: 2">
                                    @error('denda_persen', 'denda')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label d-block">Dihitung per</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="denda_persen_frekuensi" id="frekuensi-hari" value="per_hari"
                                            {{ old('denda_persen_frekuensi', $category->denda_persen_frekuensi ?? 'per_hari') === 'per_hari' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="frekuensi-hari">Per hari</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="denda_persen_frekuensi" id="frekuensi-bulan" value="per_bulan"
                                            {{ old('denda_persen_frekuensi', $category->denda_persen_frekuensi) === 'per_bulan' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="frekuensi-bulan">Per bulan</label>
                                    </div>
                                    @error('denda_persen_frekuensi', 'denda')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            {{-- Persentase + Flat --}}
                            <div class="denda-mode-fields" data-mode="persentase_flat">
                                <div class="row">
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label">Persentase per hari (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="denda_persen" class="form-control @error('denda_persen', 'denda') is-invalid @enderror"
                                            value="{{ old('denda_persen', $category->denda_persen) }}" placeholder="Contoh: 1">
                                        @error('denda_persen', 'denda')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label class="form-label">Sampai dengan hari ke-</label>
                                        <input type="number" step="1" min="1" max="365" name="denda_persen_sampai_hari" class="form-control @error('denda_persen_sampai_hari', 'denda') is-invalid @enderror"
                                            value="{{ old('denda_persen_sampai_hari', $category->denda_persen_sampai_hari) }}" placeholder="Contoh: 15">
                                        @error('denda_persen_sampai_hari', 'denda')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Flat per hari setelah itu (Rp)</label>
                                    <input type="number" step="1" min="0" name="denda_flat_amount" class="form-control @error('denda_flat_amount', 'denda') is-invalid @enderror"
                                        value="{{ old('denda_flat_amount', $category->denda_flat_amount) }}" placeholder="Contoh: 5000">
                                    <div class="form-text">
                                        Contoh: jatuh tempo tanggal 10, mulai tanggal 11 kena persentase per hari.
                                        Setelah lewat hari ke- di atas, persentase yang sudah terkumpul + flat per hari ini terus berjalan sampai dibayar.
                                    </div>
                                    @error('denda_flat_amount', 'denda')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Simpan Aturan Denda</button>
                        </form>
                    </div>

                    {{-- TAB 2: PENGINGAT --}}
                    <div class="tab-pane fade" id="tab-pengingat" role="tabpanel">
                        <p class="text-muted small">Aturan pengingat di sini otomatis disalin ke tiap Tagihan baru yang dibuat di kategori ini.</p>

                        @if($category->reminderRuleTemplates->isEmpty())
                            <p class="text-muted">Belum ada aturan pengingat.</p>
                        @else
                            <table class="table table-sm align-middle mb-4">
                                <thead>
                                    <tr>
                                        <th>Ingatkan</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($category->reminderRuleTemplates as $rule)
                                        <tr>
                                            <td>{{ $rule->remind_value }} {{ $rule->remind_unit === 'hours' ? 'jam' : 'hari' }} sebelum jatuh tempo</td>
                                            <td class="text-end">
                                                <form action="{{ route('tagihan.category.setting.reminder-rule.remove', [$category->id, $rule->id]) }}" method="POST"
                                                    onsubmit="return confirm('Hapus aturan pengingat ini?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <form action="{{ route('tagihan.category.setting.reminder-rule.add', $category->id) }}" method="POST" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-4">
                                <label class="form-label">Ingatkan</label>
                                <input type="number" min="1" max="999" name="remind_value" class="form-control" placeholder="Contoh: 3" required>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label">Satuan</label>
                                <select name="remind_unit" class="form-select" required>
                                    <option value="days">Hari</option>
                                    <option value="hours">Jam</option>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <button type="submit" class="btn btn-outline-primary w-100">+ Tambah Aturan</button>
                            </div>
                        </form>
                    </div>

                    {{-- TAB 3: INVOICE & NOTIFIKASI --}}
                    <div class="tab-pane fade" id="tab-invoice" role="tabpanel">
                        <form action="{{ route('tagihan.category.setting.invoice', $category->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Prefix Nomor Invoice</label>
                                <input type="text" name="invoice_prefix" class="form-control @error('invoice_prefix') is-invalid @enderror"
                                    value="{{ old('invoice_prefix', $category->invoice_prefix) }}" placeholder="Contoh: PST" maxlength="20">
                                <div class="form-text">
                                    Nomor invoice ditampilkan ke pelanggan sebagai <code>{{ old('invoice_prefix', $category->invoice_prefix ?: 'PST') }}-XXXXXX</code> (angka acak).
                                </div>
                                @error('invoice_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="notifikasi_email_aktif" name="notifikasi_email_aktif" value="1"
                                    {{ old('notifikasi_email_aktif', $category->notifikasi_email_aktif) ? 'checked' : '' }}>
                                <label class="form-check-label" for="notifikasi_email_aktif">Aktifkan notifikasi email</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .denda-mode-fields { display: none; }
    .denda-mode-fields.active { display: block; }
</style>

<script>
    (function () {
        function syncDendaMode() {
            var checked = document.querySelector('#dendaModeSwitch input[name="denda_mode"]:checked');
            var mode = checked ? checked.value : null;
            document.querySelectorAll('.denda-mode-fields').forEach(function (el) {
                el.classList.toggle('active', el.dataset.mode === mode);
            });
        }

        document.querySelectorAll('#dendaModeSwitch input[name="denda_mode"]').forEach(function (input) {
            input.addEventListener('change', syncDendaMode);
        });

        syncDendaMode();
    })();
</script>
@endsection
