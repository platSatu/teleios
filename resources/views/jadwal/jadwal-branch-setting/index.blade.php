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
                <li class="breadcrumb-item"><a href="{{ route('jadwal.branch.index') }}">Branch</a></li>
                <li class="breadcrumb-item"><a href="{{ route('jadwal.ruangan.index', ['branch_office_id' => $branch->id]) }}">Ruangan</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $ruangan->name }}</li>
                <li class="breadcrumb-item active" aria-current="page">Jam Operasional</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Jam Operasional <span class="text-muted fs-6 fw-normal">— {{ $branch->name }}</span></h4>
                        <p class="text-muted mb-0">Berlaku untuk SELURUH ruangan di branch "{{ $branch->name }}" (termasuk "{{ $ruangan->name }}"), bukan cuma ruangan ini — jam operasional memang satu per branch, dibuka lewat ruangan sekadar jalan pintas.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('jadwal.ruangan.index', ['branch_office_id' => $branch->id]) }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Ruangan
                        </a>
                        @if(! $setting)
                            <a href="{{ route('jadwal.branch-settings.edit', ['branchOfficeId' => $branch->id, 'ruangan_id' => $ruangan->id]) }}" class="btn btn-primary">
                                <i class="ri-add-line"></i> Tambah Jam Operasional
                            </a>
                        @endif
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">Hari Operasional</th>
                                <th class="text-nowrap">Jam Buka - Tutup</th>
                                <th class="text-nowrap">Jam Istirahat</th>
                                <th class="text-nowrap">Default Durasi/Sesi per Bulan</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($setting)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ collect($setting->hari_operasional)->map(fn($d) => \App\Models\JadwalRutin::HARI_LABELS[$d] ?? '?')->implode(', ') }}
                                    </td>
                                    <td class="text-nowrap">{{ substr($setting->jam_buka, 0, 5) }} - {{ substr($setting->jam_tutup, 0, 5) }}</td>
                                    <td class="text-nowrap">
                                        @if($setting->jam_istirahat_mulai)
                                            {{ substr($setting->jam_istirahat_mulai, 0, 5) }} - {{ substr($setting->jam_istirahat_selesai, 0, 5) }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $setting->durasi_sesi_default_menit }} menit / {{ $setting->sesi_per_bulan_default }}x</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $setting->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $setting->status }}</span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('jadwal.mata-pelajaran.index', ['branch_office_id' => $branch->id, 'ruangan_id' => $ruangan->id]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-add-line"></i> Add Mata Pelajaran
                                        </a>
                                        <a href="{{ route('jadwal.branch-settings.edit', ['branchOfficeId' => $branch->id, 'ruangan_id' => $ruangan->id]) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('jadwal.branch-settings.destroy', $branch->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jam Operasional branch &quot;{{ $branch->name }}&quot;? Ini berlaku untuk SEMUA ruangan di branch ini, bukan cuma &quot;{{ $ruangan->name }}&quot;.');">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="ruangan_id" value="{{ $ruangan->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Branch "{{ $branch->name }}" belum punya Jam Operasional. Klik "Tambah Jam Operasional" untuk mengaturnya.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
