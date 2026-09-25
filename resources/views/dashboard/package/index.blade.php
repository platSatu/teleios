@extends('layouts.dashboard')

@section('content')
    {{--
        Desain kartu package di bawah ini disamakan dengan pricing card
        di halaman publik fe-konexa (resources/views/frontend/partials/
        packages.blade.php + public/css/frontend.css di repo itu) --
        badge "TERPOPULER" mengambang, harga besar, daftar spesifikasi
        berikon dari App\Models\PackageLimit/LimitMetric.

        Beda dengan fe-konexa (sengaja, bukan kelewatan):
        - SEMUA limit package ditampilkan di sini, bukan cuma 3 metric
          broadcast/device/kontak yang dikurasi untuk landing page
          publik -- user yang login di sini sedang benar-benar memilih
          package untuk dibeli, butuh detail lengkap.
        - Tombol CTA tetap ke checkout (route dashboard.package.checkout),
          bukan link WhatsApp seperti di fe-konexa (itu untuk prospek
          anonim yang belum jadi user).
        - Ikon pakai Remix Icon (ri-*), bukan Bootstrap Icons (bi-*),
          karena itu yang dipakai seluruh dashboard Teleios.
    --}}
    <style>
        .package-card {
            position: relative;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .04);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .package-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 28px rgba(0, 0, 0, .09);
        }

        .package-card--featured {
            border-color: var(--bs-primary);
            box-shadow: 0 10px 30px rgba(13, 110, 253, .15);
        }

        .package-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--bs-primary);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            padding: .35rem 1rem;
            border-radius: 999px;
            white-space: nowrap;
        }

        .package-price-amount {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }

        .package-feature-list li {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            margin-bottom: .6rem;
            font-size: .85rem;
            color: #333;
        }

        .package-feature-list li i {
            color: var(--bs-primary);
            margin-top: .15rem;
        }
    </style>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">Packages</h4>
            <p class="text-muted mb-0">Pilih paket sesuai layanan yang dibutuhkan. Paket berlaku untuk masing-masing branch.</p>
        </div>
    </div>

    {{-- Renders the 'error' flash message set by
         App\Http\Middleware\EnsureActivePackage::deny() when a browser
         navigation gets redirected here ("Masa aktif package Anda sudah
         habis..."). This page is that middleware's redirect target
         (route('dashboard.package.index')), so without this include the
         flash was set but never actually rendered anywhere. --}}
    @include('components.notifikasi')

    {{-- Paket berlaku PER BRANCH (alur Company -> Branch -> Paket). Kartu
         di bawah hanya untuk owner (satu-satunya yang bisa membeli paket).
         "Pilih Paket" menyimpan branch tujuan di query string supaya
         otomatis terpilih di halaman checkout. --}}
    @if ($branchStatuses->isNotEmpty())
        <h6 class="mb-2">Paket per Branch</h6>
        <div class="row g-3 mb-4">
            @foreach ($branchStatuses as $status)
                @php
                    $statusBranch = $status['branch'];
                    $statusVoucher = $status['voucher'];
                @endphp
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100 mb-0 {{ $selectedBranchId === $statusBranch->id ? 'border border-primary' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold text-truncate"><i class="ri-store-2-line me-1"></i>{{ $statusBranch->name }}</span>
                                @if ($statusVoucher)
                                    <span class="badge bg-success-subtle text-success">{{ $statusVoucher->package?->is_trial ? 'Trial' : 'Aktif' }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Belum berlangganan</span>
                                @endif
                            </div>
                            <p class="text-muted fs-13 mb-3">
                                @if ($statusVoucher)
                                    {{ $statusVoucher->package?->name }} &middot; s/d {{ $statusVoucher->valid_until->format('d M Y') }}
                                @else
                                    Pilih paket untuk membuka layanan di branch ini.
                                @endif
                            </p>
                            <div class="mt-auto">
                                @if ($statusVoucher?->package?->is_trial)
                                    <a href="{{ route('dashboard.package.index', ['branch_office_id' => $statusBranch->id]) }}#paket"
                                        class="btn btn-sm btn-primary w-100">
                                        <i class="ri-vip-crown-line"></i> Pilih Paket Berbayar
                                    </a>
                                @elseif ($statusVoucher)
                                    <a href="{{ route('dashboard.package.checkout', ['package' => $statusVoucher->package_id, 'branch_office_id' => $statusBranch->id]) }}"
                                        class="btn btn-sm btn-outline-primary w-100">
                                        <i class="ri-refresh-line"></i> Perpanjang
                                    </a>
                                @else
                                    <a href="{{ route('dashboard.package.index', ['branch_office_id' => $statusBranch->id]) }}#paket"
                                        class="btn btn-sm btn-primary w-100">
                                        <i class="ri-shopping-cart-2-line"></i> Pilih Paket
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.package.index') }}" class="row g-2 align-items-center">
                @if ($selectedBranchId)
                    <input type="hidden" name="branch_office_id" value="{{ $selectedBranchId }}">
                @endif
                <div class="col-12 col-md">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0"
                            placeholder="Cari nama package...">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Category Application</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-filter-3-line"></i> Filter
                    </button>
                </div>
                @if ($search || $categoryId)
                    <div class="col-6 col-md-auto">
                        <a href="{{ route('dashboard.package.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="ri-close-line"></i> Reset
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @if ($packageGroups->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ri-inbox-line fs-1 text-muted d-block mb-3"></i>
                <h5 class="mb-1">Tidak ada package ditemukan</h5>
                <p class="text-muted mb-0">Coba ubah kata kunci pencarian atau filter category.</p>
            </div>
        </div>
    @else
        @php
            // LimitMetric.key -> ikon Remix Icon. Urutan tampil: Pengiriman
            // -> Device -> Kontak, metric lain menyusul.
            $iconMap = [
                'device' => 'ri-smartphone-line',
                'user' => 'ri-team-line',
                'contact' => 'ri-contacts-book-line',
                'broadcast' => 'ri-megaphone-line',
                'message' => 'ri-message-3-line',
                'storage' => 'ri-hard-drive-2-line',
                'branch' => 'ri-git-branch-line',
                'agent' => 'ri-customer-service-2-line',
            ];
            $limitOrder = ['broadcast', 'device', 'contact'];
            $limitRank = function ($limit) use ($limitOrder) {
                $key = strtolower($limit->limitMetric->key ?? '');
                foreach ($limitOrder as $rank => $needle) {
                    if (str_contains($key, $needle)) {
                        return $rank;
                    }
                }

                return count($limitOrder);
            };
        @endphp

        <div id="paket">
            @foreach ($packageGroups as $group)
                <div class="{{ $loop->last ? '' : 'mb-5' }}">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom">
                        <h5 class="mb-0 me-2">{{ $group['label'] }}</h5>
                        @foreach ($group['services'] as $service)
                            <span class="badge bg-primary-subtle text-primary fw-medium">{{ $service }}</span>
                        @endforeach
                    </div>

                    <div class="row g-4">
                        @foreach ($group['packages'] as $package)
                            @php
                                $isFeatured = (bool) $package->is_featured;
                                $isTrial = (bool) $package->is_trial;
                                $isFree = (float) $package->price <= 0;
                                $savings = $group['savings'][$package->id] ?? 0;
                                $limits = $package->limits->sortBy($limitRank)->values();
                                $features = $package->featureLines();
                            @endphp
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card package-card h-100 border-0 {{ $isFeatured ? 'package-card--featured' : '' }}">
                                    <div class="card-body d-flex flex-column p-4">
                                        @if ($isFeatured)
                                            <span class="package-badge">TERPOPULER</span>
                                        @endif

                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <span class="badge {{ $isTrial ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }} fs-12">
                                                {{ $package->durationLabel() }}
                                            </span>
                                            @if ($savings > 0)
                                                <span class="badge bg-warning-subtle text-warning fs-12">Hemat {{ $savings }}%</span>
                                            @endif
                                        </div>

                                        <h5 class="mb-2">{{ $package->name }}</h5>

                                        <div class="d-flex align-items-baseline gap-1 mb-1">
                                            @if ($isFree)
                                                <span class="package-price-amount">Gratis</span>
                                            @else
                                                <span class="fw-semibold text-muted">Rp</span>
                                                <span class="package-price-amount">{{ number_format($package->price, 0, ',', '.') }}</span>
                                            @endif
                                        </div>
                                        <p class="text-muted fs-13 mb-3">
                                            Masa aktif {{ $package->duration }} hari
                                            @if (! $isFree && $package->months() > 1)
                                                &middot; setara <strong>Rp {{ number_format($package->monthlyPrice(), 0, ',', '.') }}</strong>/bulan
                                            @endif
                                        </p>

                                        <a href="{{ route('dashboard.package.checkout', array_filter(['package' => $package->id, 'branch_office_id' => $selectedBranchId])) }}"
                                            class="btn {{ $isFeatured ? 'btn-primary' : 'btn-outline-primary' }} w-100 mb-3">
                                            @if ($isTrial)
                                                <i class="ri-gift-line"></i> Coba Gratis
                                            @else
                                                <i class="ri-shopping-cart-2-line"></i> Pilih Paket
                                            @endif
                                        </a>

                                        <ul class="package-feature-list list-unstyled mb-0 flex-grow-1">
                                            @foreach ($limits as $limit)
                                                @php
                                                    $metric = $limit->limitMetric;
                                                    $metricKey = strtolower($metric->key ?? '');
                                                    $icon = 'ri-checkbox-circle-line';
                                                    foreach ($iconMap as $needle => $mappedIcon) {
                                                        if (str_contains($metricKey, $needle)) {
                                                            $icon = $mappedIcon;
                                                            break;
                                                        }
                                                    }
                                                    // Kuota kiriman berlaku per bulan (PackageLimitService::currentPeriod()).
                                                    $perMonth = $metric?->isConsumable() && ! $isTrial;
                                                @endphp
                                                <li>
                                                    <i class="{{ $icon }}"></i>
                                                    <span>
                                                        {{ $metric->name ?? 'Limit' }}:
                                                        <strong>{{ number_format((float) $limit->max_value, 0, ',', '.') }}</strong>
                                                        {{ $metric?->unit }}{{ $perMonth ? '/bulan' : '' }}
                                                    </span>
                                                </li>
                                            @endforeach
                                            @foreach ($features as $feature)
                                                <li>
                                                    <i class="ri-checkbox-circle-line"></i>
                                                    <span>{{ $feature }}</span>
                                                </li>
                                            @endforeach
                                            @if ($limits->isEmpty() && empty($features))
                                                <li>
                                                    <i class="ri-checkbox-circle-line"></i>
                                                    <span>Fitur lengkap sesuai kebutuhan bisnis Anda</span>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
