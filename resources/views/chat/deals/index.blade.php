@extends('layouts.dashboard')

@php
    $stageBadge = [
        'lead' => 'bg-secondary-subtle text-secondary',
        'qualified' => 'bg-info-subtle text-info',
        'negotiation' => 'bg-warning-subtle text-warning',
        'won' => 'bg-success-subtle text-success',
        'lost' => 'bg-danger-subtle text-danger',
    ];
@endphp

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Sales Pipeline</h4>
                        <p class="text-muted mb-0">
                            Tahapan penjualan tiap pelanggan. Buat deal baru dari halaman Customer 360
                            tiap pelanggan (Kontak / Buku Telepon &rarr; tombol "360").
                        </p>
                    </div>
                </div>

                <div class="d-flex gap-3 overflow-auto pb-2" style="align-items: stretch;">
                    @foreach ($columns as $column)
                        <div style="min-width: 280px; max-width: 280px;">
                            <div class="bg-light rounded p-2 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold">{{ $column['label'] }}</span>
                                    <span class="badge bg-white text-dark border">{{ $column['deals']->count() }}</span>
                                </div>
                                <div class="text-muted small">Rp {{ number_format($column['total'], 0, ',', '.') }}</div>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                @forelse ($column['deals'] as $deal)
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-start gap-1">
                                                <div class="fw-semibold small">{{ $deal->title }}</div>
                                                <div class="d-flex flex-nowrap gap-1">
                                                    <a href="{{ route('chat.deals.edit', $deal->id) }}" class="btn btn-sm btn-light p-1" title="Edit" style="line-height: 1;">
                                                        <i class="ri-edit-line"></i>
                                                    </a>
                                                    <form action="{{ route('chat.deals.destroy', $deal->id) }}" method="POST" onsubmit="return confirm('Hapus deal ini?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-light text-danger p-1" title="Hapus" style="line-height: 1;">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            @if ($deal->customer)
                                                <a href="{{ route('chat.contacts.show', ['customer' => $deal->customer->id]) }}" class="d-block small text-muted mb-1">
                                                    <i class="ri-user-3-line"></i> {{ $deal->customer->name ?: ('+'.$deal->customer->phone) }}
                                                </a>
                                            @endif

                                            <div class="small mb-1">Rp {{ number_format($deal->value, 0, ',', '.') }}</div>

                                            @if ($deal->expected_close_at)
                                                <div class="text-muted small mb-1">
                                                    <i class="ri-calendar-event-line"></i> Target: {{ $deal->expected_close_at->translatedFormat('d M Y') }}
                                                </div>
                                            @endif

                                            @if ($deal->assignee)
                                                <div class="text-muted small mb-2">{{ $deal->assignee->name }}</div>
                                            @endif

                                            <form action="{{ route('chat.deals.move-stage', $deal->id) }}" method="POST">
                                                @csrf
                                                <select name="stage" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    @foreach ($stageLabels as $stageValue => $stageLabel)
                                                        <option value="{{ $stageValue }}" @selected($deal->stage === $stageValue)>{{ $stageLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small text-center py-3 mb-0">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
