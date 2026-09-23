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

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">{{ $tagihan->name }}</h4>
                        <p class="text-muted mb-0">
                            {{ $tagihan->category->name ?? '-' }} &middot; {{ $tagihan->branchOffice->name ?? '-' }} &middot;
                            Jatuh tempo {{ $tagihan->due_date->format('d M Y') }} &middot;
                            Rp {{ number_format($tagihan->amount, 0, ',', '.') }} &middot;
                            Denda: {{ $tagihan->pakai_denda ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('tagihan.edit', $tagihan->id) }}" class="btn btn-light"><i class="ri-edit-line"></i> Edit</a>
                        <a href="{{ route('tagihan.index') }}" class="btn btn-light">Kembali</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="mb-0">Penerima ({{ $tagihan->penerima->count() }})</h5>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-centered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pelanggan</th>
                                <th>Nominal</th>
                                <th>Denda Saat Ini</th>
                                <th>Status</th>
                                <th>Link Bayar</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tagihan->penerima as $p)
                                <tr>
                                    <td>{{ $p->pelanggan->name ?? '-' }}</td>
                                    <td>Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($p->hitungDenda(), 0, ',', '.') }}</td>
                                    <td>
                                        @php
                                            $statusBadge = [
                                                'belum_bayar' => 'bg-warning-subtle text-warning',
                                                'lunas' => 'bg-success-subtle text-success',
                                                'kadaluarsa' => 'bg-secondary-subtle text-secondary',
                                                'dibatalkan' => 'bg-danger-subtle text-danger',
                                            ][$p->status] ?? 'bg-secondary-subtle text-secondary';
                                        @endphp
                                        <span class="badge {{ $statusBadge }}">{{ str_replace('_', ' ', $p->status) }}</span>
                                    </td>
                                    <td>
                                        @if($tagihan->branchOffice?->slug)
                                            @php
                                                $payLink = route('tagihan.public.show', ['branchSlug' => $tagihan->branchOffice->slug, 'token' => $p->public_token]);
                                            @endphp
                                            {{-- "Buka Link" alone only ever opened it in a new tab here — no
                                                 way to actually hand the URL to the pelanggan (they pay from
                                                 their own phone, not this admin's browser). "Salin Link" copies
                                                 it to the clipboard so it can be pasted straight into a WhatsApp
                                                 chat/email/SMS (23 September 2026 request: "mau share link nya
                                                 ke user bagaimana caranya"). --}}
                                            <div class="d-flex align-items-center gap-2">
                                                <a href="{{ $payLink }}" target="_blank" class="small">
                                                    Buka <i class="ri-external-link-line"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-light py-0 px-2" data-copy-link="{{ $payLink }}" onclick="copyTagihanLink(this)" title="Salin link untuk dikirim ke pelanggan">
                                                    <i class="ri-file-copy-line"></i> Salin
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('tagihan.laporan.show', $p->id) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                        @if($p->status === 'belum_bayar')
                                            <form action="{{ route('tagihan.penerima.remove', [$tagihan->id, $p->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus penerima ini dari Tagihan?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Belum ada penerima.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($availablePelanggan->isNotEmpty())
                    <div class="border rounded p-3" id="settingUserPicker">
                        <h6 class="mb-2">Setting User -- Tambah Penerima</h6>
                        <p class="text-muted small mb-2">Pilih pelanggan yang mau ditagih untuk periode ini (tidak mengubah daftar langganan kategori).</p>

                        <input type="text" id="settingUserSearch" class="form-control form-control-sm mb-2" placeholder="Cari nama atau nomor telepon...">

                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="settingUserSelectAll">
                            <label class="form-check-label small" for="settingUserSelectAll">Pilih semua</label>
                        </div>

                        <form action="{{ route('tagihan.penerima.add-bulk', $tagihan->id) }}" method="POST">
                            @csrf
                            <div class="list-group mb-2" style="max-height: 260px; overflow-y: auto;">
                                @foreach($availablePelanggan as $ap)
                                    <label class="list-group-item d-flex align-items-center gap-2 setting-user-row" data-name="{{ strtolower($ap->name) }}" data-phone="{{ strtolower($ap->phone_number ?? '') }}">
                                        <input type="checkbox" class="form-check-input setting-user-checkbox" name="tagihan_pelanggan_ids[]" value="{{ $ap->id }}">
                                        <span>{{ $ap->name }}</span>
                                        @if($ap->phone_number)
                                            <span class="text-muted small">({{ $ap->phone_number }})</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm">Tambah Penerima Terpilih</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-1">Aturan Pengingat</h5>
                <p class="text-muted small mb-3">
                    Belum terhubung ke WhatsApp -- aturan ini disiapkan dulu strukturnya, pengiriman pesan menyusul.
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-centered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Kapan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tagihan->reminderRules as $rule)
                                <tr>
                                    <td>{{ $rule->label() }}</td>
                                    <td>{{ $rule->remind_value }} {{ $rule->remind_unit === 'hours' ? 'jam' : 'hari' }} sebelum jatuh tempo</td>
                                    <td class="text-end">
                                        <form action="{{ route('tagihan.reminder-rule.remove', [$tagihan->id, $rule->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus aturan pengingat ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Belum ada aturan pengingat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form action="{{ route('tagihan.reminder-rule.add', $tagihan->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small">Jumlah</label>
                        <input type="number" name="remind_value" min="0" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Satuan</label>
                        <select name="remind_unit" class="form-select form-select-sm">
                            <option value="days">Hari</option>
                            <option value="hours">Jam</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Clipboard API needs a secure context (https, which app.konexa.id
    // already is) — the execCommand('copy') fallback covers the rare
    // browser/embedded-webview combo where navigator.clipboard isn't
    // available at all.
    function copyTagihanLink(btn) {
        const link = btn.getAttribute('data-copy-link');
        const showCopied = function () {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="ri-check-line"></i> Tersalin';
            setTimeout(function () { btn.innerHTML = original; }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(showCopied);
            return;
        }

        const tmp = document.createElement('textarea');
        tmp.value = link;
        tmp.style.position = 'fixed';
        tmp.style.opacity = '0';
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        showCopied();
    }

    (function () {
        var search = document.getElementById('settingUserSearch');
        var selectAll = document.getElementById('settingUserSelectAll');
        var rows = document.querySelectorAll('.setting-user-row');

        if (search) {
            search.addEventListener('input', function () {
                var term = search.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    var match = row.dataset.name.indexOf(term) !== -1 || row.dataset.phone.indexOf(term) !== -1;
                    row.style.display = match ? '' : 'none';
                });
            });
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rows.forEach(function (row) {
                    if (row.style.display === 'none') return;
                    var checkbox = row.querySelector('.setting-user-checkbox');
                    if (checkbox) checkbox.checked = selectAll.checked;
                });
            });
        }
    })();
</script>
@endsection
