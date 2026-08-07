<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <title>Sign Up | Konexa - Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Daftar akun Konexa sekarang untuk mengakses dashboard yang aman, mengelola data dengan lebih mudah, memantau aktivitas, dan meningkatkan produktivitas dalam satu platform." />
    <meta content="SRBThemes" name="konexa" />

    <!-- layout setup -->
    <script type="module" src="{{ asset('be') }}/assets/js/layout-setup.js"></script>

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <!-- Simplebar Css -->
    <link rel="stylesheet" href="{{ asset('be') }}/assets/libs/simplebar/simplebar.min.css">

    <!-- Swiper Css -->
    <link href="{{ asset('be') }}/assets/libs/swiper/swiper-bundle.min.css" rel="stylesheet">

    <!-- Nouislider Css -->
    <link href="{{ asset('be') }}/assets/libs/nouislider/nouislider.min.css" rel="stylesheet">

    <!-- Bootstrap Css -->
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">

    <!--icons css-->
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css">

    <!-- App Css-->
    <link href="{{ asset('be') }}/assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">

    <!-- Cloudflare Turnstile (captcha) -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>

<body>
    <div class="min-vh-200 d-flex align-items-center justify-content-center px-5 py-10 auth-bg">
        <div
            class="main-wrapper border bg-white rounded-4 d-flex flex-column flex-lg-row gap-xl-5 position-relative overflow-hidden w-100 shadow">
            <div class="decoration-section m-5 bg-dark-subtle rounded-3 me-0 mb-0 mb-lg-5"></div>
            <div class="login-section bg-white rounded-4 p-6 px-xl-12">
                {{-- <a href="{{ route('login') }}"
                    class="d-flex justify-content-end align-items-center gap-2 logo-main mt-lg-2 mb-3">
                    <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                        src="{{ asset('be') }}/assets/images/favicon.png">
                    <h3 class="mb-0 lh-base fw-semibold">Konexa</h3>
                </a> --}}
                {{-- <div class="mb-8">
                    <h5 class="mb-2">Join Konexa Now</h5>
                    <p class="text-muted mb-0">
                        Getting started is quick and easy. Create your account to securely access your dashboard, organize your data, and take advantage of features built to simplify your workflow.
                    </p>
                </div> --}}
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- Name -->
                    <div class="mb-4">
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name') }}" placeholder="Name" required autofocus
                            autocomplete="name">

                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                            name="email" value="{{ old('email') }}" placeholder="Email" required
                            autocomplete="username">

                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Handphone (WhatsApp) -->
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" inputmode="numeric" class="form-control @error('handphone') is-invalid @enderror"
                                id="handphone" name="handphone" value="{{ old('handphone') }}"
                                placeholder="81234567890" required maxlength="14" autocomplete="tel-national">
                        </div>
                        <div class="form-text" style="font-size: 0.7rem;">Nomor WhatsApp aktif, tanpa awalan 0 atau 62 — cukup 10-14 digit setelahnya.</div>

                        @error('handphone')
                            <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <div class="position-relative">
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                id="password" name="password" placeholder="Password" required
                                autocomplete="new-password">

                            <button type="button"
                                class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted toggle-password"
                                data-target="password">
                                <i class="ri-eye-off-line align-middle"></i>
                            </button>

                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <div class="position-relative">
                            <input type="password"
                                class="form-control @error('password_confirmation') is-invalid @enderror"
                                id="password_confirmation" name="password_confirmation" placeholder="Confirm Password"
                                required autocomplete="new-password">

                            <button type="button"
                                class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted toggle-password"
                                data-target="password_confirmation">
                                <i class="ri-eye-off-line align-middle"></i>
                            </button>

                            @error('password_confirmation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input @error('terms') is-invalid @enderror"
                                id="terms" name="terms" value="1"
                                @checked(old('terms')) @disabled(! old('terms')) required>
                            <label class="form-check-label" for="terms">
                                Saya menyetujui
                                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat dan Ketentuan</a>
                            </label>
                            <div class="form-text" id="termsHint" style="{{ old('terms') ? 'display:none' : '' }}"><span style="font-size: 0.7rem;">
                                Buka dan baca Syarat &amp; Ketentuan di atas terlebih dahulu.
                                </span>
                            </div>

                            @error('terms')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    
                    <div class="mb-4">
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                        @error('cf-turnstile-response')
                            <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    
                    <div class="text-center mb-4">
                        <button type="submit" class="btn btn-primary w-100">
                            Create An Account
                        </button>
                    </div>

                  
                </form>

                <p class="text-center text-muted fs-14 my-6">Already have an account? <a href="{{ route('login') }}"
                        class="link link-primary">Sign In</a></p>
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
                    <a href="{{ route('auth.google') }}" class="btn btn-outline-light text-black d-flex align-items-center justify-content-center gap-2 w-100">
                        <img src="{{ asset('be') }}/assets/images/auth/google-icon.svg" alt="Google"
                            class="w-16px">Sign up via Google
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Swiper bundle js -->
    <script src="{{ asset('be') }}/assets/libs/swiper/swiper-bundle.min.js"></script>

    <!-- Bootstrap bundle js -->
    <script src="{{ asset('be') }}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- simplebar js -->
    <script src="{{ asset('be') }}/assets/libs/simplebar/simplebar.min.js"></script>

    <!-- Scroll Top init -->
    <script src="{{ asset('be') }}/assets/js/scroll-top.init.js"></script>
    <!-- Gsap Animation -->
    <script src="{{ asset('be') }}/assets/libs/gsap/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/robin-dela/hover-effect@latest/dist/hover-effect.umd.js"></script>

    <!-- Common init -->
    <script src="{{ asset('be') }}/assets/js/pages/common.init.js"></script>

    <!-- Auth init -->
    <script src="{{ asset('be') }}/assets/js/auth/auth.init.js"></script>

    <!-- Syarat & Ketentuan popup — moved here, as a direct child of
         <body>, instead of living inside .main-wrapper (which has
         overflow-hidden + is inside a transformed/animated decoration
         wrapper on this page). Bootstrap's .modal/.modal-backdrop are
         position: fixed, which is normally relative to the viewport —
         but nested inside an ancestor with overflow-hidden (and,
         depending on the theme's CSS, a transform on top of that), fixed
         positioning becomes relative to THAT ancestor instead, which is
         exactly what clipped the popup and made the backdrop only cover
         part of the screen (and made outside-clicks land outside the
         real clickable area, so Tutup/X felt broken). Content is
         App\Models\WebTermCondition::current() — whichever row a
         superadmin has marked Active (see
         Superadmin\Web\TermConditionController). -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $currentTerms->name ?? 'Syarat dan Ketentuan' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($currentTerms)
                        <div style="white-space: pre-line;">{{ $currentTerms->descriptions }}</div>
                    @else
                        <p class="text-muted mb-0">Syarat dan Ketentuan belum tersedia.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Ticks (and re-enables) the "Saya menyetujui..." checkbox the
        // instant #termsModal finishes closing — via the X, the Tutup
        // button, clicking the backdrop, or pressing Escape, all of which
        // fire this same 'hidden.bs.modal' event, so every way of
        // dismissing it is covered by one listener. The checkbox itself
        // starts disabled (see the form field above) precisely so this is
        // the ONLY way to ever check it — there's no path to a checked
        // box without the popup having actually been opened first.
        document.addEventListener('DOMContentLoaded', function () {
            var termsModalEl = document.getElementById('termsModal');
            var termsCheckbox = document.getElementById('terms');
            var termsHint = document.getElementById('termsHint');

            if (!termsModalEl || !termsCheckbox) return;

            termsModalEl.addEventListener('hidden.bs.modal', function () {
                termsCheckbox.disabled = false;
                termsCheckbox.checked = true;
                if (termsHint) termsHint.style.display = 'none';
            });
        });
    </script>

</body>

</html>
