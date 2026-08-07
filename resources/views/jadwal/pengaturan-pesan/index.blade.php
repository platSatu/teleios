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
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Pengaturan Pesan Jadwal</h4>
                <p class="text-muted mb-0">
                    Redaksi pesan WA otomatis untuk pengingat & konfirmasi Jadwal Kelas — bisa diedit sendiri, langsung aktif tanpa perlu review.
                    Kosongkan untuk kembali memakai teks default.
                </p>
            </div>
            <a href="{{ route('jadwal.jadwal-kelas.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali ke Jadwal Kelas
            </a>
        </div>

        <form action="{{ route('jadwal.pengaturan-pesan.update') }}" method="POST">
            @csrf
            @method('PUT')

            @foreach ($items as $item)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                            <h6 class="mb-0">
                                {{ $item['label'] }}
                                @if ($item['is_customized'])
                                    <span class="badge bg-primary-subtle text-primary ms-1">Custom</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary ms-1">Default</span>
                                @endif
                            </h6>
                            @if ($item['is_customized'])
                                <form action="{{ route('jadwal.pengaturan-pesan.reset', $item['key']) }}" method="POST" onsubmit="return confirm('Kembalikan pesan ini ke teks default?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger">Reset ke Default</button>
                                </form>
                            @endif
                        </div>

                        <textarea name="body[{{ $item['key'] }}]" class="form-control @error('body.'.$item['key']) is-invalid @enderror" rows="3">{{ old('body.'.$item['key'], $item['body']) }}</textarea>
                        @error('body.'.$item['key'])
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror

                        <div class="form-text mt-1">
                            Placeholder tersedia:
                            @foreach ($item['placeholders'] as $ph)
                                {!! '<code>' . e('{{'.$ph.'}}') . '</code>' !!}@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn btn-primary">Simpan Semua</button>
        </form>

    </div>
</div>
@endsection
