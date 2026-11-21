<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chicken Crunchy Roll | Login</title>
    <meta name="description"
        content="Halaman login Chicken Crunchy Roll untuk mengelola penjualan dengan mudah.">
    <meta name="author" content="Aigars Silkalns / Colorlib">
    <link rel="canonical" href="https://preview.colorlib.com/theme/login-forms/forms/creative/">
    @include('layouts.partials.favicon')
    <meta property="og:type" content="website">
    <meta property="og:title" content="Portal Login Chicken Crunchy Roll">
    <meta property="og:description"
        content="Halaman login sistem manajemen Chicken Crunchy Roll.">
    <meta property="og:url" content="https://preview.colorlib.com/theme/login-forms/forms/creative/">
    <meta property="og:image" content="https://preview.colorlib.com/theme/login-forms/assets/screenshots/creative.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Portal Login Chicken Crunchy Roll">
    <meta name="twitter:description"
        content="Halaman login sistem manajemen Chicken Crunchy Roll (Admin & Kasir).">
    <meta name="twitter:image" content="https://preview.colorlib.com/theme/login-forms/assets/screenshots/creative.png">
    <link rel="stylesheet" href="{{ asset('assets/assets_login/style.css') }}">
</head>

<body>
    <div class="creative-bg">
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
            <div class="shape shape-5"></div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="card-decoration">
                <div class="deco-line line-1"></div>
                <div class="deco-line line-2"></div>
                <div class="deco-line line-3"></div>
            </div>

            <div class="login-header">
                <div class="creative-logo">
                    <div class="logo-circle circle-1"></div>
                    <div class="logo-circle circle-2"></div>
                    <div class="logo-circle circle-3"></div>
                </div>
                <h2>Login Chicken Roll</h2>
                <p>Silakan masuk untuk melanjutkan</p>
            </div>

            <form class="login-form" id="loginForm" method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Address -->

                <div class="form-group">
                    <div class="input-wrapper">
                        <div class="input-decoration"></div>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required
                            autofocus autocomplete="username">
                        <label for="email">Alamat Email</label>
                        <div class="input-waves">
                            <div class="wave wave-1"></div>
                            <div class="wave wave-2"></div>
                            <div class="wave wave-3"></div>
                        </div>
                    </div>
                    @error('email')
                        <span class="error-message show">{{ $message }}</span>
                    @else
                        <span class="error-message" id="emailError"></span>
                    @enderror
                </div>

                <div class="form-group">
                    <div class="input-wrapper password-wrapper">
                        <div class="input-decoration"></div>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <label for="password">Kata Sandi</label>
                        <button type="button" class="password-toggle" id="passwordToggle"
                            aria-label="Toggle password visibility">
                            <span class="toggle-icon"></span>
                        </button>
                        <div class="input-waves">
                            <div class="wave wave-1"></div>
                            <div class="wave wave-2"></div>
                            <div class="wave wave-3"></div>
                        </div>
                    </div>
                    @error('password')
                        <span class="error-message show">{{ $message }}</span>
                    @else
                        <span class="error-message" id="passwordError"></span>
                    @enderror
                </div>

                <div class="form-options">
                    <div class="remember-wrapper">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember" class="checkbox-label">
                            <div class="checkbox-creative">
                                <div class="check-mark"></div>
                            </div>
                            Ingat saya
                        </label>
                    </div>
                    <a href="#" class="forgot-password">Lupa password?</a>
                </div>

                <!-- <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button> -->


                <button type="submit" class="login-btn">
                    <span class="btn-bg"></span>
                    <span class="btn-text">Masuk</span>
                    <span class="btn-loader">
                        <div class="loader-dot dot-1"></div>
                        <div class="loader-dot dot-2"></div>
                        <div class="loader-dot dot-3"></div>
                    </span>
                </button>
            </form>

            <div class="signup-link">
                <p>Butuh akses akun? Hubungi Admin/IT</p>
            </div>

            <div class="success-message" id="successMessage">
                <div class="success-animation">
                    <div class="success-circle"></div>
                    <div class="success-checkmark">✓</div>
                </div>
                <h3>Login Berhasil!</h3>
                <p>Mengarahkan ke dashboard...</p>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/assets_login/shared/form-utils.js') }}"></script>
    <script src="{{ asset('assets/assets_login/script.js') }}"></script>
</body>

</html>
