@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Data Users</h4>
                    <p class="text-muted mb-0">Seluruh user terdaftar.</p>
                </div>
                <a href="{{ route('superadmin-users.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah User
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama / email..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Saldo Wallet</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->email }}</td>
                                <td>
                                    <span class="badge {{ $item->user_type === 'SUPERADMIN' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $item->user_type }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td>Rp {{ number_format($item->wallet->balance ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('superadmin-users.show', $item->id) }}" class="btn btn-outline-primary">
                                            <i class="ri-eye-line"></i> Show
                                        </a>
                                        <a href="{{ route('superadmin-users.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="reset-user-{{ $item->id }}" class="btn btn-outline-warning" onclick="return confirm('Reset seluruh riwayat user ini (login, deposit, voucher, ledger, subscription, referral, keanggotaan company, dll) dan nolkan saldo wallet? Akun user itu sendiri TIDAK akan dihapus. Data finansial permanen (ledger entry, audit log, deposit sukses, dsb) tidak ikut dihapus. Tindakan ini tidak bisa dibatalkan.');">
                                            <i class="ri-refresh-line"></i> Reset
                                        </button>
                                        @if ($item->id !== auth()->id())
                                            <button type="submit" form="delete-user-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus user ini? Ini akan gagal jika user masih punya data terkait (wallet/deposit/dsb).');">
                                                <i class="ri-delete-bin-line"></i> Hapus
                                            </button>
                                        @endif
                                    </div>
                                    <form id="reset-user-{{ $item->id }}" action="{{ route('superadmin-users.reset', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                    @if ($item->id !== auth()->id())
                                        <form id="delete-user-{{ $item->id }}" action="{{ route('superadmin-users.destroy', $item->id) }}" method="POST" class="d-none">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
