@extends('layouts.dashboard')

@php
    $statusMeta = [
        'open' => ['label' => 'Baru / Menunggu Agent', 'badge' => 'bg-warning-subtle text-warning', 'icon' => 'ri-time-line'],
        'pending' => ['label' => 'Menunggu Pelanggan', 'badge' => 'bg-info-subtle text-info', 'icon' => 'ri-hourglass-line'],
        'resolved' => ['label' => 'Selesai', 'badge' => 'bg-success-subtle text-success', 'icon' => 'ri-check-double-line'],
    ];
    $dealStageBadge = [
        'lead' => 'bg-secondary-subtle text-secondary',
        'qualified' => 'bg-info-subtle text-info',
        'negotiation' => 'bg-warning-subtle text-warning',
        'won' => 'bg-success-subtle text-success',
        'lost' => 'bg-danger-subtle text-danger',
    ];
    $dealStageLabel = \App\Models\WaDeal::STAGE_LABELS;
@endphp

@section('content')
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

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $customer->name ?: ('+'.$customer->phone) }}</h4>
            <p class="text-muted mb-0">Riwayat lengkap pelanggan ini — percakapan, tugas, catatan, dan data kontak dalam satu halaman.</p>
        </div>
        <a href="{{ url()->previous() }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="row g-3">
        {{-- Identity card --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Identitas Pelanggan</h6>

                    <div class="mb-2">
                        <span class="text-muted small d-block">Nomor WhatsApp</span>
                        <span class="fw-semibold">+{{ $customer->phone }}</span>
                    </div>

                    <div class="mb-2">
                        <span class="text-muted small d-block">Nama</span>
                        <span>{{ $customer->name ?: '-' }}</span>
                    </div>

                    <div class="mb-2">
                        <span class="text-muted small d-block mb-1">Tag</span>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @forelse ($customer->tags as $tag)
                                <span class="badge bg-{{ $tag->color }}-subtle text-{{ $tag->color }}">
                                    {{ $tag->name }}
                                    <form action="{{ route('chat.customer-tags.detach', ['customer' => $customer->id, 'tag' => $tag->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus tag ini dari pelanggan?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" style="color: inherit; font-size: 0.75rem;" title="Hapus tag">&times;</button>
                                    </form>
                                </span>
                            @empty
                                <span class="text-muted small">Belum ada tag.</span>
                            @endforelse
                        </div>
                        @php $unusedTags = $availableTags->diff($customer->tags); @endphp
                        @if ($unusedTags->isNotEmpty())
                            <form action="{{ route('chat.customer-tags.attach', $customer->id) }}" method="POST" class="d-flex gap-1">
                                @csrf
                                <select name="wa_customer_tag_id" class="form-select form-select-sm">
                                    @foreach ($unusedTags as $tag)
                                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-light"><i class="ri-add-line"></i></button>
                            </form>
                        @endif
                        <a href="{{ route('chat.segments.index') }}" class="small">Kelola daftar tag &rarr;</a>
                    </div>

                    <div class="mb-2">
                        <span class="text-muted small d-block">Cabang</span>
                        <span>{{ $customer->branchOffice->name ?? '-' }}</span>
                    </div>

                    <div class="mb-2">
                        <span class="text-muted small d-block">Ditugaskan ke</span>
                        <span>{{ $customer->assignee->name ?? '-' }}</span>
                    </div>

                    <div class="mb-2">
                        <span class="text-muted small d-block">Pertama Terlihat</span>
                        <span>{{ $customer->first_seen_at?->translatedFormat('d M Y, H:i') ?? '-' }}</span>
                    </div>

                    <div class="mb-0">
                        <span class="text-muted small d-block">Terakhir Dihubungi</span>
                        <span>{{ $customer->last_contacted_at?->translatedFormat('d M Y, H:i') ?? '-' }}</span>
                    </div>

                    <hr>

                    <h6 class="text-muted text-uppercase small mb-2">Sumber Data</h6>
                    <div class="d-flex flex-column gap-1">
                        <span>
                            <i class="ri-chat-3-line text-success"></i> Kontak Chat
                            @if ($customer->contact)
                                <span class="badge bg-success-subtle text-success ms-1">Ada</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary ms-1">Belum ada</span>
                            @endif
                        </span>
                        <span>
                            <i class="ri-book-2-line text-primary"></i> Buku Telepon
                            @if ($customer->phoneBookEntry)
                                <span class="badge bg-success-subtle text-success ms-1">Ada</span>
                                @if ($customer->phoneBookEntry->is_blacklisted)
                                    <span class="badge bg-danger-subtle text-danger ms-1"><i class="ri-forbid-line"></i> Blacklist</span>
                                @endif
                            @else
                                <span class="badge bg-secondary-subtle text-secondary ms-1">Belum ada</span>
                            @endif
                        </span>
                    </div>

                    @if ($customer->phoneBookEntry)
                        <hr>
                        <h6 class="text-muted text-uppercase small mb-2">Detail Buku Telepon</h6>
                        <div class="mb-2">
                            <span class="text-muted small d-block">Kelompok</span>
                            <span>{{ $customer->phoneBookEntry->category->name ?? '-' }}</span>
                        </div>
                        <div class="mb-0">
                            <span class="text-muted small d-block">Email</span>
                            <span>{{ $customer->phoneBookEntry->email ?? '-' }}</span>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('chat.phone-books.edit', $customer->phoneBookEntry->id) }}" class="btn btn-sm btn-light">
                                <i class="ri-external-link-line"></i> Buka di Buku Telepon
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Timeline: conversations + notes --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Riwayat Percakapan</h6>

                    @if ($conversations->isEmpty())
                        <p class="text-muted mb-0">Belum ada percakapan WhatsApp dengan pelanggan ini.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-centered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nomor Device</th>
                                        <th>Status</th>
                                        <th>Pesan Masuk Terakhir</th>
                                        <th>Ditugaskan ke</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($conversations as $conversation)
                                        @php $meta = $statusMeta[$conversation->status] ?? ['label' => $conversation->status, 'badge' => 'bg-secondary-subtle text-secondary', 'icon' => 'ri-question-line']; @endphp
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark-subtle text-dark">
                                                    <i class="ri-smartphone-line"></i>
                                                    {{ $conversation->device_phone_number ? '+'.$conversation->device_phone_number : 'Device' }}
                                                </span>
                                            </td>
                                            <td><span class="badge {{ $meta['badge'] }}"><i class="{{ $meta['icon'] }}"></i> {{ $meta['label'] }}</span></td>
                                            <td class="text-muted small">{{ $conversation->last_inbound_at?->translatedFormat('d M Y, H:i') ?? '-' }}</td>
                                            <td>{{ $conversation->assignee->name ?? '-' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('inbox.index', $conversation->device_id) }}?chat={{ urlencode($conversation->chat_jid) }}" class="btn btn-sm btn-light" title="Buka di Inbox">
                                                    <i class="ri-external-link-line"></i> Buka di Inbox
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Tugas &amp; Follow-up</h6>

                    <form action="{{ route('chat.tasks.store') }}" method="POST" class="row g-2 mb-3">
                        @csrf
                        <input type="hidden" name="wa_customer_id" value="{{ $customer->id }}">
                        <div class="col-md-4">
                            <input type="text" name="title" class="form-control form-control-sm" placeholder="Judul tugas, mis. Follow-up pembayaran" required maxlength="200">
                        </div>
                        <div class="col-md-3">
                            <input type="datetime-local" name="due_at" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <select name="assigned_to" class="form-select form-select-sm">
                                <option value="">Belum ditugaskan</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="ri-add-line"></i> Tambah
                            </button>
                        </div>
                    </form>

                    @if ($tasks->isEmpty())
                        <p class="text-muted mb-0">Belum ada tugas follow-up untuk pelanggan ini.</p>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach ($tasks as $task)
                                @php $isOverdue = $task->status === 'pending' && $task->due_at && $task->due_at->isPast(); @endphp
                                <div class="border rounded p-2 {{ $task->status === 'done' ? 'bg-light' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-semibold {{ $task->status === 'done' ? 'text-decoration-line-through text-muted' : '' }}">
                                                {{ $task->title }}
                                            </div>
                                            @if ($task->description)
                                                <div class="text-muted small">{{ $task->description }}</div>
                                            @endif
                                            <div class="text-muted small mt-1">
                                                @if ($task->due_at)
                                                    <span class="{{ $isOverdue ? 'text-danger fw-semibold' : '' }}">
                                                        @if ($isOverdue)<i class="ri-alarm-warning-line"></i>@endif
                                                        Jatuh tempo {{ $task->due_at->translatedFormat('d M Y, H:i') }}
                                                    </span>
                                                @endif
                                                @if ($task->assignee)
                                                    &middot; {{ $task->assignee->name }}
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex flex-nowrap gap-1">
                                            @if ($task->status === 'done')
                                                <form action="{{ route('chat.tasks.reopen', $task->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light" title="Buka Lagi">
                                                        <i class="ri-arrow-go-back-line"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('chat.tasks.complete', $task->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light text-success" title="Tandai Selesai">
                                                        <i class="ri-check-line"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form action="{{ route('chat.tasks.destroy', $task->id) }}" method="POST" onsubmit="return confirm('Hapus tugas ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Pipeline Penjualan</h6>

                    <form action="{{ route('chat.deals.store') }}" method="POST" class="row g-2 mb-3">
                        @csrf
                        <input type="hidden" name="wa_customer_id" value="{{ $customer->id }}">
                        <div class="col-md-4">
                            <input type="text" name="title" class="form-control form-control-sm" placeholder="Judul deal, mis. Paket Langganan Tahunan" required maxlength="200">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="value" class="form-control form-control-sm" placeholder="Nilai (Rp)" min="0" step="0.01">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="expected_close_at" class="form-control form-control-sm" title="Target closing">
                        </div>
                        <div class="col-md-2">
                            <select name="assigned_to" class="form-select form-select-sm">
                                <option value="">Belum ditugaskan</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="ri-add-line"></i> Tambah Deal
                            </button>
                        </div>
                    </form>

                    @if ($deals->isEmpty())
                        <p class="text-muted mb-0">Belum ada deal untuk pelanggan ini.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-centered table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Judul</th>
                                        <th>Nilai</th>
                                        <th>Tahap</th>
                                        <th>Target Closing</th>
                                        <th>Ditugaskan ke</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deals as $deal)
                                        <tr>
                                            <td>{{ $deal->title }}</td>
                                            <td>Rp {{ number_format($deal->value, 0, ',', '.') }}</td>
                                            <td><span class="badge {{ $dealStageBadge[$deal->stage] ?? 'bg-secondary-subtle text-secondary' }}">{{ $dealStageLabel[$deal->stage] ?? $deal->stage }}</span></td>
                                            <td class="text-muted small">{{ $deal->expected_close_at?->translatedFormat('d M Y') ?? '-' }}</td>
                                            <td>{{ $deal->assignee->name ?? '-' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('chat.deals.edit', $deal->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Catatan</h6>

                    @if ($notes->isEmpty())
                        <p class="text-muted mb-0">Belum ada catatan internal untuk pelanggan ini.</p>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach ($notes as $note)
                                <div class="border rounded p-2">
                                    <div class="d-flex justify-content-between text-muted small mb-1">
                                        <span>{{ $note->author->name ?? 'Tidak diketahui' }}</span>
                                        <span>{{ $note->created_at?->translatedFormat('d M Y, H:i') }}</span>
                                    </div>
                                    <div>{{ $note->note }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Transaksi</h6>
                    <p class="text-muted mb-0">Belum ada data transaksi. Fitur ini akan tersedia setelah integrasi transaksi/pesanan ditambahkan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
