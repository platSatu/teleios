{{-- Tombol "Tambah Section" + tabel section (urut, status, aksi).
     Dipakai Susunan Beranda ($pageId null) dan halaman landing. --}}
@php
    $pageId = $pageId ?? null;
    $usedBuiltins = $sections->filter(fn ($section) => $section->isBuiltin())->pluck('type')->all();
@endphp
<div class="d-flex justify-content-end mb-3">
    <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ri-add-line"></i> Tambah Section
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach (\App\Models\WebHomeSection::TYPES as $type => $config)
                            @continue(($config['builtin'] ?? false) && in_array($type, $usedBuiltins, true))
                            @continue($pageId && ! \App\Models\WebHomeSection::allowedOnPage($type))
                            <li>
                                <a class="dropdown-item" href="{{ route('web.home-sections.create', array_filter(['type' => $type, 'page' => $pageId])) }}">
                                    {{ $config['label'] }}
                                    @if ($config['builtin'] ?? false)
                                        <span class="badge bg-secondary-subtle text-secondary ms-1">bawaan</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
</div>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 110px;">Urutan</th>
                            <th>Section</th>
                            <th>Jenis</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sections as $section)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold">{{ $loop->iteration }}</span>
                                        @include('superadmin.web.home-sections._move', ['route' => 'web.home-sections.move', 'id' => $section->id, 'first' => $loop->first, 'last' => $loop->last])
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $section->title ?: $section->label() }}</div>
                                    @if ($section->hasItems())
                                        <div class="text-muted small">{{ $section->items_count }} item</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $section->isBuiltin() ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }}">{{ $section->label() }}</span>
                                    @if ($section->typeConfig('source'))
                                        <div class="text-muted small mt-1">Data: {{ $section->typeConfig('source') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $section->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $section->status === 'active' ? 'Tampil' : 'Disembunyikan' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('web.home-sections.edit', $section->id) }}" class="btn btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        <button type="submit" form="delete-section-{{ $section->id }}" class="btn btn-outline-danger"
                                            onclick="return confirm('Hapus section ini dari beranda?{{ $section->isBuiltin() ? ' Data di menu '.$section->typeConfig('source').' tetap aman.' : '' }}');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-section-{{ $section->id }}" action="{{ route('web.home-sections.destroy', $section->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">{{ $pageId ? 'Belum ada section di halaman ini.' : 'Belum ada section. Beranda memakai susunan default.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
