@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Permintaan Reschedule Jadwal</h4>
                        <p class="text-muted mb-0">Permintaan ubah jadwal dari orang tua/murid via WhatsApp, menunggu direview.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)
                            <a href="{{ route('jadwal.reschedule-requests.index', ['status' => $value]) }}"
                                class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-light' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>

                @forelse ($requests as $reschedule)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">
                                    {{ $reschedule->jadwalStudent->name ?? 'Murid tidak dikenali' }}
                                    <span class="text-muted fw-normal">— {{ $reschedule->requester_phone ?: 'nomor tidak dikenali' }}</span>
                                </div>
                                @if($reschedule->jadwalStudent)
                                    <div class="text-muted small">
                                        {{ $reschedule->jadwalStudent->mataPelajaran->name ?? '-' }}
                                        @if($reschedule->jadwalStudent->pengajar) &middot; Pengajar {{ $reschedule->jadwalStudent->pengajar->name }} @endif
                                    </div>
                                @endif
                            </div>
                            <span class="badge {{ match($reschedule->status) {
                                'approved' => 'bg-success-subtle text-success',
                                'rejected' => 'bg-danger-subtle text-danger',
                                default => 'bg-warning-subtle text-warning',
                            } }} text-capitalize">{{ $reschedule->status }}</span>
                        </div>

                        <div class="bg-light rounded p-2 mb-2 small" style="white-space: pre-line;">{{ $reschedule->detail_request }}</div>

                        @if($reschedule->jadwal_kelas_id)
                            <div class="small text-muted mb-2">Terhubung ke Jadwal Kelas: {{ $reschedule->jadwalKelas->start_time?->format('d/m/Y H:i') ?? $reschedule->jadwal_kelas_id }}</div>
                        @endif

                        @if($reschedule->requested_new_start_time)
                            <div class="small text-muted mb-2">
                                Jadwal baru yang diminta (otomatis dari chat): {{ $reschedule->requested_new_start_time->format('d/m/Y H:i') }}
                                @if($reschedule->requested_new_end_time) - {{ $reschedule->requested_new_end_time->format('H:i') }} @endif
                            </div>
                        @endif

                        @if($reschedule->staff_notes)
                            <div class="small text-muted mb-2">Catatan staff: {{ $reschedule->staff_notes }} @if($reschedule->reviewer) &middot; {{ $reschedule->reviewer->name }} @endif</div>
                        @endif

                        @if($reschedule->status === 'pending')
                            <div class="row g-2 mt-1">
                                <div class="col-md-6">
                                    <form action="{{ route('jadwal.reschedule-requests.approve', $reschedule->id) }}" method="POST" class="border rounded p-2 h-100">
                                        @csrf
                                        <div class="fw-semibold small mb-2">Setujui</div>
                                        @if($reschedule->jadwal_student_id && ($kelasOptions[$reschedule->jadwal_student_id] ?? null))
                                            <select name="jadwal_kelas_id" class="form-select form-select-sm mb-2">
                                                <option value="">- Jangan ubah Jadwal Kelas otomatis -</option>
                                                @foreach ($kelasOptions[$reschedule->jadwal_student_id] as $kelasOpt)
                                                    <option value="{{ $kelasOpt->id }}" @selected($kelasOpt->id === $reschedule->jadwal_kelas_id)>{{ $kelasOpt->start_time?->format('d/m/Y H:i') ?? '-' }} - {{ $kelasOpt->end_time?->format('H:i') ?? '-' }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <input type="datetime-local" name="new_start_time" class="form-control form-control-sm" placeholder="Waktu mulai baru"
                                                    value="{{ $reschedule->requested_new_start_time?->format('Y-m-d\TH:i') }}">
                                            </div>
                                            <div class="col-6">
                                                <input type="datetime-local" name="new_end_time" class="form-control form-control-sm" placeholder="Waktu selesai baru"
                                                    value="{{ $reschedule->requested_new_end_time?->format('Y-m-d\TH:i') }}">
                                            </div>
                                        </div>
                                        <input type="text" name="staff_notes" class="form-control form-control-sm mb-2" placeholder="Catatan (opsional)">
                                        <button type="submit" class="btn btn-sm btn-success">Setujui</button>
                                    </form>
                                </div>
                                <div class="col-md-6">
                                    <form action="{{ route('jadwal.reschedule-requests.reject', $reschedule->id) }}" method="POST" class="border rounded p-2 h-100">
                                        @csrf
                                        <div class="fw-semibold small mb-2">Tolak</div>
                                        <input type="text" name="staff_notes" class="form-control form-control-sm mb-2" placeholder="Alasan penolakan (wajib)" required>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Tolak</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Belum ada permintaan reschedule dengan status ini.</div>
                @endforelse

                <div class="mt-3">
                    {{ $requests->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
