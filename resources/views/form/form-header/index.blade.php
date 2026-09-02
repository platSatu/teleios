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

        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('form.branch.index') }}">Branch</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.category.index', ['branch_office_id' => $category->branch_office_id]) }}">{{ $category->branchOffice->name ?? 'Form Category' }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
                <li class="breadcrumb-item active" aria-current="page">Form Header</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Form Header — {{ $category->name }}</h4>
                        <p class="text-muted mb-0">Setiap baris adalah satu form publik yang bisa diisi lewat URL uniknya sendiri.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('form.category.index', ['branch_office_id' => $category->branch_office_id]) }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Form Category
                        </a>
                        <a href="{{ route('form.header.create', $category->id) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Form Header
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th class="text-nowrap">Nama</th>
                                <th class="text-nowrap">URL Publik</th>
                                <th class="text-nowrap">Periode</th>
                                <th class="text-nowrap">Pertanyaan</th>
                                <th class="text-nowrap">Submission</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($headers as $header)
                                <tr>
                                    <td style="width: 48px;">
                                        @if ($header->background_url)
                                            <img src="{{ $header->background_url }}" alt="" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                        @else
                                            <span class="avatar avatar-md rounded d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary">
                                                <i class="uil uil-file-alt"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold text-nowrap">{{ $header->name }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('form.public.show', $header->slug) }}" target="_blank" class="fs-12">
                                            /{{ $header->slug }} <i class="ri-external-link-line align-middle"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-light py-0 px-1 ms-1 fc-qr-btn"
                                            title="QR Code"
                                            data-url="{{ route('form.public.show', $header->slug) }}"
                                            data-name="{{ $header->name }}"
                                            data-slug="{{ $header->slug }}"
                                            data-regenerate-url="{{ route('form.header.regenerate-slug', [$category->id, $header->id]) }}">
                                            <i class="ri-qr-code-line"></i>
                                        </button>
                                    </td>
                                    <td class="text-nowrap fs-12">
                                        {{ $header->start_date?->translatedFormat('d M Y') }} &ndash; {{ $header->end_date?->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('form.content.index', $header->id) }}" class="badge bg-light text-dark border fw-normal text-decoration-none">
                                            <i class="ri-list-check-2 align-middle"></i> {{ $header->contents_count }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('form.submission.index', $header->id) }}" class="badge bg-info-subtle text-info border-0 fw-normal text-decoration-none">
                                            <i class="ri-inbox-line align-middle"></i> {{ $header->submissions_count }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $header->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $header->status }}</span>
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('form.content.index', $header->id) }}" class="btn btn-sm btn-outline-primary" title="Pertanyaan">
                                            <i class="ri-list-check-2"></i>
                                        </a>
                                        <a href="{{ route('form.footer.index', $header->id) }}" class="btn btn-sm btn-light" title="Footer">
                                            <i class="ri-layout-bottom-line"></i>
                                        </a>
                                        <a href="{{ route('form.setting.edit', $header->id) }}" class="btn btn-sm btn-light" title="Pengaturan">
                                            <i class="ri-settings-3-line"></i>
                                        </a>
                                        <a href="{{ route('form.header.edit', [$category->id, $header->id]) }}" class="btn btn-sm btn-light" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('form.header.destroy', [$category->id, $header->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Form Header ini? Seluruh Pertanyaan/Footer/Setting/Submission di dalamnya ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada Form Header. Klik "Tambah Form Header" untuk membuat form publik pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $headers->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: QR Code URL publik -- lihat resources/views/chat/konekdevice/konekdevice.blade.php
     untuk pola qrcodejs yang sama persis dipakai di sini (device WA). --}}
<div class="modal fade" id="fh-qr-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fh-qr-modal-title">QR Code Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="fh-qr-container" class="d-inline-block p-3 bg-white border rounded mb-3"></div>
                <div class="mb-3">
                    <div class="form-text mb-1">URL publik</div>
                    <div class="input-group input-group-sm">
                        <input type="text" id="fh-qr-url" class="form-control" readonly>
                        <button type="button" class="btn btn-outline-secondary" id="fh-qr-copy-btn"><i class="ri-file-copy-line"></i></button>
                    </div>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="#" id="fh-qr-download-btn" class="btn btn-primary btn-sm"><i class="ri-download-2-line"></i> Download PNG</a>
                    <form id="fh-qr-regenerate-form" method="POST" onsubmit="return confirm('Buat URL & QR Code baru untuk form ini? URL/QR yang LAMA (yang mungkin sudah dibagikan/dicetak) akan langsung berhenti berfungsi.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="ri-refresh-line"></i> Regenerate URL &amp; QR</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const qrModalEl = document.getElementById('fh-qr-modal');
    const qrModal = new bootstrap.Modal(qrModalEl);
    const qrContainer = document.getElementById('fh-qr-container');
    const qrUrlInput = document.getElementById('fh-qr-url');
    const qrTitle = document.getElementById('fh-qr-modal-title');
    const downloadBtn = document.getElementById('fh-qr-download-btn');
    const regenerateForm = document.getElementById('fh-qr-regenerate-form');
    let qrInstance = null;

    document.querySelectorAll('.fc-qr-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = btn.getAttribute('data-url');
            const name = btn.getAttribute('data-name');

            qrTitle.textContent = 'QR Code — ' + name;
            qrUrlInput.value = url;
            regenerateForm.action = btn.getAttribute('data-regenerate-url');

            qrContainer.innerHTML = '';
            // Selalu buat instance BARU (bukan qrInstance.makeCode()) --
            // beda header/slug berarti ukuran & isi bisa beda per buka
            // modal, dan davidshimjs/qrcodejs kadang menyisakan elemen
            // lama kalau cuma di-clear() saat container di-reset manual.
            qrInstance = new QRCode(qrContainer, {
                text: url,
                width: 220,
                height: 220,
                correctLevel: QRCode.CorrectLevel.M,
            });

            // qrcodejs merender <canvas> (dipakai untuk download) + <img>
            // fallback di dalam container yang sama -- tunggu 1 tick
            // supaya elemen <canvas>-nya sudah ada sebelum dibaca toDataURL().
            setTimeout(function () {
                const canvas = qrContainer.querySelector('canvas');
                if (canvas) {
                    downloadBtn.href = canvas.toDataURL('image/png');
                }
            }, 50);

            downloadBtn.setAttribute('download', 'qr-form-' + (btn.getAttribute('data-slug') || 'form') + '.png');

            qrModal.show();
        });
    });

    document.getElementById('fh-qr-copy-btn').addEventListener('click', function () {
        qrUrlInput.select();
        navigator.clipboard?.writeText(qrUrlInput.value).catch(function () {});
    });
});
</script>
@endsection
