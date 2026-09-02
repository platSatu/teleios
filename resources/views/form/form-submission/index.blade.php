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
                <li class="breadcrumb-item"><a href="{{ route('form.category.index', ['branch_office_id' => $header->branch_office_id]) }}">Form Category</a></li>
                <li class="breadcrumb-item"><a href="{{ route('form.header.index', $header->form_category_id) }}">{{ $header->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Submission</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Submission — {{ $header->name }}</h4>
                        <p class="text-muted mb-0">Daftar semua orang yang sudah mengisi form ini lewat URL publiknya.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('form.content.index', $header->id) }}" class="btn btn-light">
                            <i class="ri-list-check-2"></i> Pertanyaan
                        </a>
                        <a href="{{ route('form.header.index', $header->form_category_id) }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Waktu Submit</th>
                                @if ($summaryContent)
                                    <th class="text-nowrap">{{ $summaryContent->name }}</th>
                                @endif
                                <th class="text-nowrap">Jumlah Jawaban</th>
                                <th class="text-nowrap">IP</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $submission)
                                @php
                                    $summaryAnswer = $summaryContent
                                        ? $submission->answers->firstWhere('form_content_id', $summaryContent->id)
                                        : null;
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $submission->submitted_at?->translatedFormat('d M Y H:i') }}</td>
                                    @if ($summaryContent)
                                        <td class="text-nowrap">{{ \Illuminate\Support\Str::limit($summaryAnswer->value ?? '-', 40) }}</td>
                                    @endif
                                    <td class="text-nowrap">
                                        <span class="badge bg-light text-dark border fw-normal">{{ $submission->answers->count() }}</span>
                                    </td>
                                    <td class="text-nowrap fs-12 text-muted">{{ $submission->ip_address ?? '-' }}</td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('form.submission.show', [$header->id, $submission->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-eye-line"></i> Detail
                                        </a>
                                        <form action="{{ route('form.submission.destroy', [$header->id, $submission->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus submission ini beserta seluruh jawabannya?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $summaryContent ? 5 : 4 }}" class="text-center text-muted py-4">
                                        Belum ada yang submit form ini.
                                        <a href="{{ route('form.public.show', $header->slug) }}" target="_blank">Lihat form publik</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $submissions->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
