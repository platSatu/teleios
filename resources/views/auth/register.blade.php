<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <title>Sign Up | Mirbal - Bootstrap Admin & Dashboard Template</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta content="Bootstrap Admin & Dashboard Template" name="description" />
    <meta content="SRBThemes" name="author" />

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
    <div class="min-vh-100 d-flex align-items-center justify-content-center px-5 py-10 auth-bg">
        <div
            class="main-wrapper border bg-white rounded-4 d-flex flex-column flex-lg-row gap-xl-5 position-relative overflow-hidden w-100 shadow">
            <div class="decoration-section m-5 bg-dark-subtle rounded-3 me-0 mb-0 mb-lg-5"></div>
            <div class="login-section bg-white rounded-4 p-6 px-xl-12">
                <a href="index.html"
                    class="d-flex justify-content-end align-items-center gap-2 logo-main mt-lg-2 mb-10">
                    <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                        src="{{ asset('be') }}/assets/images/logo-md.png">
                    <h3 class="mb-0 lh-base fw-semibold">Mirbal</h3>
                </a>
                <div class="mb-8">
                    <h5 class="mb-2">Join Mirbal Now</h5>
                    <p class="text-muted mb-0">
                        It only takes a moment to create your account
                    </p>
                </div>
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- Name -->
                    <div class="mb-4">
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name') }}" placeholder="Username" required autofocus
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

                    <!-- Remember Me (Optional) -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">

                                <label class="form-check-label" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Captcha -->
                    <div class="mb-4">
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                        @error('cf-turnstile-response')
                            <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Register Button -->
                    <div class="text-center mb-4">
                        <button type="submit" class="btn btn-primary w-100">
                            Create An Account
                        </button>
                    </div>

                    <!-- Login Link -->
                    <div class="text-center">
                        <a href="{{ route('login') }}">
                            Already registered?
                        </a>
                    </div>
                </form>
                <p class="text-center text-muted fs-14 my-6">Already have an account? <a href="{{ route('login') }}"
                        class="link link-primary">Sign In</a></p>
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
                    <a href="{{ route('auth.google') }}" class="btn btn-outline-light text-black d-flex align-items-center justify-content-center gap-2 w-100">
                        <img src="{{ asset('be') }}/assets/images/auth/google-icon.svg" alt="Google"
                            class="w-16px">Sign up via Google
                    </a>
                    <button type="button" class="btn btn-outline-light text-black d-flex align-items-center justify-content-center gap-2 w-100">
                        <img src="{{ asset('be') }}/assets/images/auth/apple-black.svg" alt="Apple"
                            class="w-16px">Sign up via Apple
                    </button>
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

</body>

</html>
