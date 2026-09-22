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

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Tagihan (Invoice)</h4>
                        <p class="text-muted mb-0">Periode tagihan, mis. "Uang Sekolah Januari 2026". Membuat Tagihan baru otomatis menagih semua pelanggan yang berlangganan kategorinya.</p>
                    </div>
                    <a href="{{ route('tagihan.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Buat Tagihan
                    </a>
                </div>

                <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                    <select name="tagihan_category_id" class="form-select" style="max-width: 260px;" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama</th>
                                <th>Kategori</th>
                                <th>Branch</th>
                                <th>Nominal</th>
                                <th>Jatuh Tempo</th>
                                <th>Jumlah Penerima</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tagihanList as $t)
                                <tr>
                                    <td class="fw-semibold">{{ $t->name }}</td>
                                    <td>{{ $t->category->name ?? '-' }}</td>
                                    <td>{{ $t->branchOffice->name ?? '-' }}</td>
                                    <td>Rp {{ number_format($t->amount, 0, ',', '.') }}</td>
                                    <td>{{ $t->due_date->format('d M Y') }}</td>
                                    <td>{{ $t->penerima_count }}</td>
                                    <td>
                                        <span class="badge {{ $t->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $t->status }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('tagihan.show', $t->id) }}" class="btn btn-sm btn-outline-primary">Lihat</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada Tagihan. Klik "Buat Tagihan" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $tagihanList->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
