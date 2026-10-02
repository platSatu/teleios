@extends('layouts.dashboard')

@section('content')
    {{--
        Superadmin > Backup Database (lihat Superadmin\DatabaseBackupController &
        App\Services\Backup\DatabaseBackupService). Satu modal PIN dipakai bersama
        untuk Buat / Download / Hapus -- action & judulnya diisi JS saat dibuka.
    --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">Backup Database</h4>
            <p class="text-muted mb-0">
                Backup otomatis tiap malam pukul 01:00 WIB, disimpan {{ $keepDays }} hari (backup sukses terakhir selalu disimpan).
                @if ($diskFree !== null)
                    Sisa disk server: <strong>{{ number_format($diskFree / 1073741824, 1, ',', '.') }} GB</strong>.
                @endif
            </p>
        </div>
        <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#backupPinModal"
            data-action="{{ route('database-backup.store') }}" data-method="POST"
            data-title="Buat Backup Sekarang" data-button="Buat Backup" @disabled($running)>
            <i class="ri-database-2-line"></i> Buat Backup Sekarang
        </button>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($running)
        <div class="alert alert-info">Backup sedang berjalan. Muat ulang halaman ini beberapa saat lagi.</div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama File</th>
                            <th class="text-end">Ukuran</th>
                            <th>Dibuat Oleh</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($backups as $backup)
                            <tr>
                                <td>{{ $backup->created_at->format('d M Y H:i') }}</td>
                                <td><code>{{ $backup->filename ?? '-' }}</code></td>
                                <td class="text-end">{{ $backup->size_bytes ? $backup->humanSize() : '-' }}</td>
                                <td>{{ $backup->trigger === 'manual' ? ($backup->creator->name ?? '-').' (manual)' : 'Otomatis' }}</td>
                                <td>
                                    @php
                                        [$badge, $label] = match ($backup->status) {
                                            'success' => ['bg-success-subtle text-success', 'Berhasil'],
                                            'running' => ['bg-info-subtle text-info', 'Sedang berjalan'],
                                            default => ['bg-danger-subtle text-danger', 'Gagal'],
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $label }}</span>
                                    @if ($backup->status === 'failed' && $backup->error)
                                        <div class="text-danger small text-wrap" style="max-width: 320px;">{{ \Illuminate\Support\Str::limit($backup->error, 160) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($backup->isDownloadable())
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#backupPinModal"
                                            data-action="{{ route('database-backup.download', $backup->id) }}" data-method="POST"
                                            data-title="Download {{ $backup->filename }}" data-button="Download">
                                            <i class="ri-download-2-line"></i> Download
                                        </button>
                                    @endif
                                    @if ($backup->status !== 'running')
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#backupPinModal"
                                            data-action="{{ route('database-backup.destroy', $backup->id) }}" data-method="DELETE"
                                            data-title="Hapus {{ $backup->filename ?? 'catatan backup gagal' }}" data-button="Hapus">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada backup. Backup pertama dibuat otomatis malam ini, atau klik "Buat Backup Sekarang".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $backups->links('pagination::bootstrap-5') }}
            </div>

            <p class="text-muted small mb-0 mt-3">
                File berisi seluruh data aplikasi (format MySQL, .sql.gz) -- simpan di tempat aman dan jangan dibagikan.
                Data terenkripsi di dalamnya hanya bisa dibaca dengan APP_KEY yang sama.
            </p>
        </div>
    </div>

    <div class="modal fade" id="backupPinModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="backupPinForm" method="POST" action="">
                    @csrf
                    <input type="hidden" name="_method" id="backupPinMethod" value="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="backupPinTitle">Konfirmasi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <x-transaction-pin-input />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="backupPinSubmit">Lanjut</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('backupPinModal');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (!trigger) return;

                var form = document.getElementById('backupPinForm');
                form.setAttribute('action', trigger.getAttribute('data-action'));
                document.getElementById('backupPinMethod').value = trigger.getAttribute('data-method') || 'POST';
                document.getElementById('backupPinTitle').textContent = trigger.getAttribute('data-title') || 'Konfirmasi';
                document.getElementById('backupPinSubmit').textContent = trigger.getAttribute('data-button') || 'Lanjut';

                var pin = form.querySelector('input[name="pin"]');
                if (pin) pin.value = '';
            });

            // Download tidak memuat ulang halaman: tutup modal setelah dikirim.
            document.getElementById('backupPinForm').addEventListener('submit', function () {
                var submit = document.getElementById('backupPinSubmit');
                if (submit.textContent === 'Download') {
                    setTimeout(function () { bootstrap.Modal.getInstance(modal)?.hide(); }, 300);
                }
            });
        })();
    </script>
@endsection
