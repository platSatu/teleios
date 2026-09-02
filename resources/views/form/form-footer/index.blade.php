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
                <li class="breadcrumb-item active" aria-current="page">Footer</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Footer — {{ $header->name }}</h4>
                        <p class="text-muted mb-0">Blok penutup yang tampil di bawah form publik (mis. ucapan terima kasih, info lanjutan).</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('form.header.index', $header->form_category_id) }}" class="btn btn-light">
                            <i class="ri-arrow-left-line"></i> Kembali ke Form Header
                        </a>
                        <a href="{{ route('form.footer.create', $header->id) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Footer
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Isi</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($footers as $footer)
                                <tr>
                                    <td>{{ \Illuminate\Support\Str::limit($footer->name, 120) }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $footer->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $footer->status }}</span>
                                    </td>
                                    <td class="text-nowrap text-end">
                                        <a href="{{ route('form.footer.edit', [$header->id, $footer->id]) }}" class="btn btn-sm btn-light">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <form action="{{ route('form.footer.destroy', [$header->id, $footer->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Footer ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Belum ada Footer. Klik "Tambah Footer" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $footers->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
