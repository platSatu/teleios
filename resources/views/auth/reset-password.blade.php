<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8" />
    <title>Reset Password | Konexa - Reset Password</title>
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
</head>

<body>
    <div class="min-vh-100 d-flex align-items-center justify-content-center py-10 px-5 auth-bg">
        <div
            class="main-wrapper border bg-white rounded-4 d-flex flex-column flex-lg-row gap-xl-5 position-relative overflow-hidden w-100 shadow">
            <div class="decoration-section m-5 bg-dark-subtle rounded-3 me-0 mb-0 mb-lg-5 overflow-hidden">
                <img src="{{ asset('be') }}/images/login.jpg" alt="Konexa" class="w-100 h-100" style="object-fit: cover;">
            </div>
            <div class="login-section bg-white rounded-4 p-6 px-xl-12">
                <a href="{{ route('login') }}"
                    class="d-flex justify-content-end align-items-center gap-2 logo-main mt-lg-2 mb-5 mb-lg-0">
                    <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                        src="{{ asset('be') }}/assets/images/favicon.png">
                    <h3 class="mb-0 lh-base fw-semibold">Konexa</h3>
                </a>
                <div class="d-flex flex-column justify-content-center h-100 ">
                    <div class="mb-12">
                        <h5 class="mb-2">Create new password</h5>
                        <p class="text-muted mb-0">Your new password must be different from previously used passwords.</p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.store') }}">
                        @csrf

                        <!-- Password Reset Token -->
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div class="mb-10">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                id="email" name="email" value="{{ old('email', $request->email) }}"
                                placeholder="Email Address" required autofocus autocomplete="username">

                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-10">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                id="password" name="password" placeholder="New Password" required
                                autocomplete="new-password">

                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-10">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input type="password"
                                class="form-control @error('password_confirmation') is-invalid @enderror"
                                id="password_confirmation" name="password_confirmation"
                                placeholder="Confirm New Password" required autocomplete="new-password">

                            @error('password_confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </div>
                    </form>
                    <p class="text-center text-muted fs-14 my-6">Remembered your password? <a
                            href="{{ route('login') }}" class="link link-primary">Sign In</a></p>
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
