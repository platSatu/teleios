@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ri-google-line" style="font-size: 3rem; color: #999;"></i>
                <h4 class="mt-3 mb-2">Google Contact</h4>
                <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                    Sinkronisasi otomatis dari Google Contacts belum tersedia — fitur ini butuh izin akses Google
                    (People API) yang terpisah dari login "Sign in with Google" yang sudah ada, dan perlu pengaturan
                    tambahan di Google Cloud Console (verifikasi izin, dsb) sebelum bisa dipakai.
                </p>
                <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                    Untuk sekarang, kamu tetap bisa menambahkan banyak kontak sekaligus lewat <strong>Import</strong>
                    di halaman Buku Telepon (format <code>.xlsx</code>/<code>.xls</code>/<code>.csv</code> — bisa
                    diekspor dulu dari Google Contacts, lalu diimpor ke sini).
                </p>
                <a href="{{ route('chat.phone-books.index') }}" class="btn btn-primary">
                    <i class="ri-contacts-book-line"></i> Buka Buku Telepon
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
