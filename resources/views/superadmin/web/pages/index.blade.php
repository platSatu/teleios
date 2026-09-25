@extends('layouts.dashboard')

@section('content')
    @php $frontendUrl = rtrim((string) config('app.frontend_url', 'https://bizbos.id'), '/'); @endphp
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Halaman</h4>
                    <p class="text-muted mb-0">Halaman dinamis website di alamat /page/nama-halaman (Kebijakan Privasi, Affiliate, Tentang Kami, dll).</p>
                </div>
                <a href="{{ route('web.pages.create') }}" class="btn btn-primary"><i class="ri-add-line"></i> Tambah Halaman</a>
            </div>

            @include('components.notifikasi')

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul / alamat..." value="{{ $search }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Judul</th>
                            <th>Tipe</th>
                            <th>Tampil di</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $page->title }}</div>
                                    <a href="{{ $frontendUrl }}/page/{{ $page->slug }}" target="_blank" rel="noopener" class="small text-muted">/page/{{ $page->slug }} <i class="ri-external-link-line"></i></a>
                                </td>
                                <td>
                                    <span class="badge {{ $page->isLanding() ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">{{ $page->isLanding() ? 'Landing' : 'Dokumen' }}</span>
                                    @if ($page->isLanding())
                                        <div class="text-muted small mt-1">{{ $page->sections_count }} section</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($page->show_in_navbar)
                                        <div><i class="ri-menu-line"></i> Navbar (urutan {{ $page->navbar_order }})</div>
                                    @endif
                                    @if ($page->show_in_footer)
                                        <div><i class="ri-layout-bottom-line"></i> Footer · {{ $page->footer_group }}</div>
                                    @endif
                                    @if (! $page->show_in_navbar && ! $page->show_in_footer)
                                        <span class="text-muted">Hanya lewat link</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $page->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $page->status === 'active' ? 'Tayang' : 'Draft' }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('web.pages.edit', $page->id) }}" class="btn btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        <button type="submit" form="delete-page-{{ $page->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus halaman ini beserta seluruh isinya?');"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                    <form id="delete-page-{{ $page->id }}" action="{{ route('web.pages.destroy', $page->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada halaman.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pages->hasPages())
                <div class="mt-3">{{ $pages->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
@endsection
