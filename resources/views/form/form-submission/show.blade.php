@extends('layouts.dashboard')

@section('content')
<style>
    @media print {
        .app-header, #sidebar, .main-breadcrumb, .no-print {
            display: none !important;
        }

        main.app-wrapper {
            margin: 0 !important;
        }

        .submission-print-card {
            max-width: 100% !important;
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>
<div class="row">
    <div class="col-12">

        <nav aria-label="breadcrumb" class="mb-2 no-print">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('form.branch.index') }}">Branch</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.category.index', ['branch_office_id' => $header->branch_office_id]) }}">Form Category</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.header.index', $header->form_category_id) }}">{{ $header->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.submission.index', $header->id) }}">Submission</a></li>
                <li class="breadcrumb-item active" aria-current="page">Detail</li>
            </ol>
        </nav>

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Detail Submission</h4>
                <p class="text-muted mb-0">
                    Dikirim {{ $submission->submitted_at?->translatedFormat('d M Y H:i') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="ri-printer-line"></i> Print
                </button>
                <a href="{{ route('form.submission.index', $header->id) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali ke Daftar
                </a>
                <form action="{{ route('form.submission.destroy', [$header->id, $submission->id]) }}" method="POST" onsubmit="return confirm('Hapus submission ini beserta seluruh jawabannya?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                </form>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm submission-print-card">
                    <div class="card-body">
                        @forelse($submission->answers as $answer)
                            <div class="mb-4 pb-4 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="fw-semibold mb-2">
                                    {{ $answer->formContent->name ?? '(Pertanyaan sudah dihapus)' }}
                                </div>

                                @php $decoded = $answer->decodedValue(); @endphp

                                @if ($answer->file_path)
                                    <a href="{{ \App\Helpers\FormImageUploader::url($answer->file_path) }}" target="_blank" class="btn btn-sm btn-light">
                                        <i class="ri-download-2-line"></i> Lihat / Unduh File
                                    </a>
                                @elseif (is_array($decoded))
                                    <div class="d-flex flex-wrap gap-1">
                                        @forelse($decoded as $item)
                                            <span class="badge bg-light text-dark border fw-normal">{{ $item }}</span>
                                        @empty
                                            <span class="text-muted">(kosong)</span>
                                        @endforelse
                                    </div>
                                @else
                                    <div class="text-body" style="white-space: pre-line;">{{ $decoded ?: '(kosong)' }}</div>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">Tidak ada jawaban tercatat untuk submission ini.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
