@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">AI Bot</h4>
                        <p class="text-muted mb-0">Aktifkan asisten AI otomatis pada device WhatsApp yang sedang terhubung.</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBotModal" @disabled($providers->isEmpty())>
                        <i class="ri-add-line"></i> Tambah AI Bot
                    </button>
                </div>

                @if ($providers->isEmpty())
                    <div class="alert alert-warning">
                        Belum ada provider AI yang Active. Hubungi superadmin untuk menambahkan provider &amp; model di menu "AI Bot" terlebih dahulu.
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Provider</th>
                                <th>Model</th>
                                @if ($isOwner)
                                    <th>Cabang</th>
                                @endif
                                <th>Aktivasi</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bots as $bot)
                                <tr>
                                    <td>{{ $bot->provider->name ?? $bot->ai_provider }}</td>
                                    <td>{{ $bot->model->name ?? ($bot->ai_model ?: '-') }}</td>
                                    @if ($isOwner)
                                        <td>{{ $bot->branchOffice->name ?? '-' }}</td>
                                    @endif
                                    <td>
                                        @if($bot->custom_activation_time && $bot->activation_start_at)
                                            Terjadwal: {{ $bot->activation_start_at->translatedFormat('d M Y H:i') }}
                                            @if($bot->activation_end_at)
                                                &ndash; {{ $bot->activation_end_at->translatedFormat('d M Y H:i') }}
                                            @endif
                                        @elseif($bot->active_bot_immediately)
                                            Langsung aktif
                                        @else
                                            Manual
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $bot->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $bot->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#editBotModal{{ $bot->id }}">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <form action="{{ route('chat.ai-bots.destroy', $bot->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus konfigurasi AI Bot ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editBotModal{{ $bot->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form action="{{ route('chat.ai-bots.update', $bot->id) }}" method="POST" enctype="multipart/form-data">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit AI Bot</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    @include('chat.ai-bots._form', ['bot' => $bot, 'errorBag' => 'editBot'.$bot->id, 'providers' => $providers, 'branchOffices' => $branchOffices, 'isOwner' => $isOwner, 'lockedBranchOffice' => $lockedBranchOffice])
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="{{ $isOwner ? 6 : 5 }}" class="text-center text-muted py-4">Belum ada konfigurasi AI Bot.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $bots->links() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addBotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('chat.ai-bots.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah AI Bot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('chat.ai-bots._form', ['bot' => null, 'errorBag' => 'newBot', 'providers' => $providers, 'branchOffices' => $branchOffices, 'isOwner' => $isOwner, 'lockedBranchOffice' => $lockedBranchOffice])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('chat.partials.device-select-script')
@endsection
