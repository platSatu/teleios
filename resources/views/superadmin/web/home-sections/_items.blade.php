<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0">Item ({{ $section->items->count() }})</h5>
            <a href="{{ route('web.home-sections.items.create', $section->id) }}" class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Tambah Item</a>
        </div>
        <div class="table-responsive">
            <table class="table table-centered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 110px;">Urutan</th>
                        <th style="width: 70px;"></th>
                        <th>Item</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section->items as $item)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold">{{ $loop->iteration }}</span>
                                    @include('superadmin.web.home-sections._move', ['route' => 'web.home-section-items.move', 'id' => $item->id, 'first' => $loop->first, 'last' => $loop->last])
                                </div>
                            </td>
                            <td>
                                @if ($item->image)
                                    <img src="{{ $item->image_url }}" alt="" style="width: 48px; height: 48px; object-fit: contain;" class="rounded border">
                                @elseif ($item->icon)
                                    <i class="bi bi-{{ $item->icon }} fs-3 text-primary"></i>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->value ? $item->value.' · ' : '' }}{{ $item->title ?: '-' }}</div>
                                @if ($item->description)
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($item->description, 90) }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('web.home-section-items.edit', $item->id) }}" class="btn btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                    <button type="submit" form="delete-item-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus item ini?');"><i class="ri-delete-bin-line"></i></button>
                                </div>
                                <form id="delete-item-{{ $item->id }}" action="{{ route('web.home-section-items.destroy', $item->id) }}" method="POST" class="d-none">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Belum ada item. Section ini tidak tampil di beranda sampai ada item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
