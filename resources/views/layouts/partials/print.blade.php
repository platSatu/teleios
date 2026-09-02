{{--
    Print CSS bersama untuk halaman yang punya tombol Print/window.print()
    (mis. Invoice, Detail Submission Form) -- supaya hasil print/PDF-nya
    cuma berisi konten utamanya saja (dibungkus class "print-card"), tanpa
    header/sidebar/breadcrumb/footer aplikasi maupun tombol aksi (class
    "no-print"), dan tanpa area kosong panjang di bawahnya -- footer
    aplikasi biasanya didorong ke bawah pakai tinggi penuh layar
    (min-height: 100vh) yang ikut kebawa ke halaman print kalau tidak
    di-reset manual di sini.

    Cara pakai: @include('layouts.partials.print') di dalam
    @section('content') pada view yang butuh, lalu tandai elemen yang
    tidak boleh ikut ke-print dengan class "no-print", dan bungkus
    kartu/konten utamanya dengan class "print-card".
--}}
<style>
    @media print {
        .app-header, #sidebar, .main-breadcrumb, .footer, #progress-scroll, .no-print {
            display: none !important;
        }

        html, body, #layout-wrapper, main.app-wrapper, .container-fluid {
            min-height: 0 !important;
            height: auto !important;
        }

        main.app-wrapper {
            margin: 0 !important;
        }

        .print-card {
            max-width: 100% !important;
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>
