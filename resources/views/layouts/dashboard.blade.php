<!DOCTYPE html>
<html lang="en">
<head>

    @php
        // Auto-derives the page title/breadcrumb from the current URL
        // instead of the theme's hardcoded "Blank" — every one of this
        // app's 172 views extends this one layout and none of them ever
        // set a per-page title, so deriving it once here from the route
        // itself is what actually makes every page correct with zero
        // per-view changes needed, rather than a 172-file migration.
        //
        // A child view CAN still override this by defining
        // @section('title', '...') and/or @section('page-section', '...')
        // before @endsection — Blade captures a child's @section blocks
        // before the parent layout runs, so $__env->yieldContent() below
        // already sees them even though this code sits above @yield('content').
        $__segments = collect(request()->segments())
            ->reject(fn ($s) => $s === 'dashboard')
            ->reject(fn ($s) => is_numeric($s) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s))
            ->map(fn ($s) => \Illuminate\Support\Str::of($s)->replace(['-', '_'], ' ')->title()->toString())
            ->values();

        // A bare action word ("Edit", "Create", "History"...) means
        // nothing on its own once the id segment it followed has been
        // filtered out above — pair it with whatever came before it
        // ("Message Schedules — Edit") instead of just showing "Edit".
        $__genericActions = ['Edit', 'Create', 'Show', 'History', 'Add', 'Detail'];
        if ($__segments->count() > 1 && in_array($__segments->last(), $__genericActions, true)) {
            $__autoTitle = $__segments->slice(-2)->implode(' — ');
        } else {
            $__autoTitle = $__segments->last() ?: 'Dashboard';
        }

        $pageTitle = trim($__env->yieldContent('title')) ?: $__autoTitle;
        $pageSection = trim($__env->yieldContent('page-section')) ?: ($__segments->count() > 1 ? $__segments->first() : 'Dashboard');
    @endphp

    <meta charset="utf-8" />
    <title>{{ $pageTitle }} | Konexa | Dashboard </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta content="Konexa Dashboard adalah platform manajemen yang membantu Anda mengelola data, memantau aktivitas, dan meningkatkan produktivitas melalui dashboard yang cepat, aman, dan mudah digunakan." name="description" />
    <meta name="keywords" content="Konexa, Dashboard, Manajemen, Sistem Informasi, Monitoring, Data, Laporan, Aplikasi Bisnis, ERP, CRM" />
    <meta content="SRBThemes" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- layout setup -->
    <script type="module" src="{{asset('be')}}/assets/js/layout-setup.js"></script>
    
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{asset('be')}}/assets/images/favicon.png">
    <!-- Simplebar Css -->
    <link rel="stylesheet" href="{{asset('be')}}/assets/libs/simplebar/simplebar.min.css">
    
    <!-- Swiper Css -->
    <link href="{{asset('be')}}/assets/libs/swiper/swiper-bundle.min.css" rel="stylesheet">
    
    <!-- Nouislider Css -->
    <link href="{{asset('be')}}/assets/libs/nouislider/nouislider.min.css" rel="stylesheet">
    
    <!-- Bootstrap Css -->
    <link href="{{asset('be')}}/assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">
    
    <!--icons css-->
    <link href="{{asset('be')}}/assets/css/icons.min.css" rel="stylesheet" type="text/css">
    
    <!-- App Css-->
    <link href="{{asset('be')}}/assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">

    {{--
        Fix: item menu "Dashboards" di sidebar itu link biasa (tanpa
        submenu), tapi rule bawaan tema untuk menyembunyikan teks saat
        sidebar mode "icon" (terlipat) cuma menyasar `.pe-slide.pe-has-sub`
        (item yang punya submenu). Karena Dashboards tidak punya submenu,
        teksnya ('Dashboards') tidak ikut disembunyikan dan malah
        terpotong jadi 'D' oleh lebar sidebar yang sempit saat terlipat.
        Rule di bawah menambahkan perilaku sembunyi-teks yang sama untuk
        item pe-slide TANPA submenu, supaya saat terlipat cuma ikonnya
        yang tampil, konsisten dengan menu lain.
    --}}
    <style>
        [data-sidebar="icon"] .pe-app-sidebar .pe-main-menu > li.pe-slide:not(.pe-has-sub) > .pe-nav-link .pe-nav-content {
            display: none;
        }
    </style>
</head>

<body>
<!-- <div class="h-200px w-200px bg-danger"></div> -->

<!-- begin::App -->
<div id="layout-wrapper">

    <!-- Begin Header -->
    @include('layouts.partials.header')
    <!-- END Header -->
    
    <div class="header-wrapper"></div>
    
    
    @include('layouts.partials.menu')
    
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <main class="app-wrapper">
        <div class="container-fluid">

            <div class="main-breadcrumb d-flex flex-wrap align-items-center my-4 position-relative gap-3">
                <h2 class="breadcrumb-title mb-0 flex-grow-1 fs-16">{{ $pageTitle }}</h2>
                <div class="flex-shrink-0">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-end mb-0">
                            <li><a href="{{ route('dashboard') }}"><i class="ri-home-4-line fs-16 me-2 lh-sm text-primary"></i></a></li>
                            @if($pageSection !== $pageTitle)
                                <li class="breadcrumb-item"><a href="javascript:void(0)">{{ $pageSection }}</a></li>
                            @endif
                            <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                        </ol>
                    </nav>
                </div>
                <div class="gap-4 content-title d-none">
                    <div class="text-end">
                        <h5 class="mb-2 text-white fs-16">{{ $pageTitle }}</h5>
                        <h6 class="text-opacity-50 text-white fs-14 fw-medium mb-0">Page Overview</h6>
                    </div>
                    <div class="avatar-item avatar-lg rounded avatar-title text-white bg-white bg-opacity-10 border-0">
                        <i class="uil uil-layers fs-4"></i>
                    </div>
                </div>
            </div>
            @yield('content')
        </div><!--End container-fluid-->
    </main><!--End app-wrapper-->

   
    <!-- Begin scroll top -->
   @include('layouts.partials.scroll')
    <!-- END scroll top -->
    <!-- Begin Footer -->
   @include('layouts.partials.footer')
    <!-- END Footer -->
</div>
<!-- End Begin page -->

<!-- Swiper bundle js -->
<script src="{{asset('be')}}/assets/libs/swiper/swiper-bundle.min.js"></script>

<!-- Bootstrap bundle js -->
<script src="{{asset('be')}}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- simplebar js -->
<script src="{{asset('be')}}/assets/libs/simplebar/simplebar.min.js"></script>

<!-- Scroll Top init -->
<script src="{{asset('be')}}/assets/js/scroll-top.init.js"></script>
<!-- App js -->
<script src="{{asset('be')}}/assets/js/app.js"></script>

<script>
    // Second, client-side layer against Back/Forward showing a stale
    // authenticated page after logout — App\Http\Middleware\
    // PreventBackHistoryCache (Cache-Control: no-store on every
    // response) is the first layer and is normally enough on its own,
    // but current Chrome's bfcache (back/forward cache) can restore a
    // page's exact in-memory JS/DOM state on Back WITHOUT re-requesting
    // it from the server at all in some cases — meaning the no-store
    // header is never even re-evaluated, since no network request
    // happens. That's what let Back resurrect the Duitku checkout
    // widget (resources/views/user/deposit/pay.blade.php) with its
    // already-initialized state still "live", re-triggering the
    // payment call with no server round-trip for 'auth' to catch.
    //
    // 'pageshow' with event.persisted === true fires specifically when
    // a page is being restored from bfcache (never fires on a normal
    // fresh load). Forcing a hard reload here makes that restoration
    // behave like a real fresh request — hitting the server, going
    // through 'auth' again, and correctly bouncing to /login if the
    // session is gone — instead of silently reusing the frozen page.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>

</body>

</html>