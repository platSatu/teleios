@extends('layouts.dashboard')

@section('content')
<div class="row g-3">
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
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h4 class="mb-1">Automasi</h4>
                <p class="text-muted mb-3">
                    Buat tugas follow-up otomatis saat sesuatu terjadi pada pelanggan — deal pindah tahap,
                    tag tertentu ditambahkan, atau pelanggan sudah lama tidak dihubungi.
                </p>

                @include('chat.automation-rules._form', [
                    'formAction' => route('chat.automation-rules.store'),
                    'method' => 'POST',
                    'rule' => null,
                    'tags' => $tags,
                    'dealStages' => $dealStages,
                    'teamMembers' => $teamMembers,
                    'formIdSuffix' => 'create',
                ])
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Daftar Aturan</h5>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Trigger</th>
                                <th>Aksi</th>
                                <th>Status</th>
                                <th class="text-end">Kelola</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rules as $rule)
                                <tr>
                                    <td class="fw-semibold">{{ $rule->name }}</td>
                                    <td class="text-muted small">{{ \App\Models\WaCustomerAutomationRule::TRIGGER_LABELS[$rule->trigger_type] ?? $rule->trigger_type }}</td>
                                    <td class="text-muted small">Buat tugas: "{{ $rule->action_config['title'] ?? '-' }}"</td>
                                    <td>
                                        @if ($rule->is_active)
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end" style="white-space: nowrap;">
                                        <div class="d-flex flex-nowrap justify-content-end gap-1">
                                            <form action="{{ route('chat.automation-rules.toggle-active', $rule->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light" title="{{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                    <i class="{{ $rule->is_active ? 'ri-pause-line' : 'ri-play-line' }}"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('chat.automation-rules.edit', $rule->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('chat.automation-rules.destroy', $rule->id) }}" method="POST" onsubmit="return confirm('Hapus aturan ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada aturan automasi. Buat dari form di atas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
