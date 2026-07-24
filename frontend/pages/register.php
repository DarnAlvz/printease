<?php
require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../../backend/includes/session.php';
secureSession();
require_once __DIR__ . '/../../backend/includes/functions.php';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/auth_brand_panel.php';

$register_alert_message = '';

$register_error_messages = [
    'invalid_role' => 'Please choose a valid account type.',
    'invalid_email' => 'Please enter a valid email address.',
    'invalid_email_domain' => 'That email domain cannot receive mail. Please use a working email address.',
    'duplicate_email' => 'That email is already registered. Please sign in or use another email.',
    'registration_failed' => "We couldn't complete your registration. Please verify your details and try again.",
    'rate_limited' => 'Too many registration attempts. Please try again in a few minutes.',
    'weak_password' => 'Password must be at least 8 characters long.',
    'password_mismatch' => 'Passwords do not match. Please retype your password.',
    'mail_failed' => 'We could not send the verification code. Please check your email or try again.',
];

$flash_error = getFlash('auth_error');

if ($flash_error !== '' && isset($register_error_messages[$flash_error])) {
    $register_alert_message = $register_error_messages[$flash_error];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PrintEase</title>
    <?php renderPrintEaseIcons(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #070566;
            --blue: #006fa6;
            --cyan: #09b8cf;
            --cyan-dark: #008fc1;
            --input-bg: #d9f5fb;
            --input-border: #75daf0;
            --text-blue: #0070bf;
            --panel-soft: rgba(255, 255, 255, .13);
            --shadow: 0 10px 22px rgba(7, 5, 102, .14);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--navy);
            font-family: 'Inter', Arial, sans-serif;
            background: #fff;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .register-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(480px, 1fr);
        }

        .brand-panel {
            min-height: 100vh;
            padding: 52px 56px;
            color: #fff;
            background:
                radial-gradient(circle at 58% 18%, rgba(28, 120, 196, .42), transparent 32%),
                linear-gradient(145deg, #070566 0%, #094c91 48%, #00709d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-content {
            width: min(560px, 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .brand-logo {
            width: min(280px, 52vw);
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 18px 32px rgba(0, 0, 0, .24));
        }

        .brand-copy {
            margin-top: 36px;
        }

        .brand-copy h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(36px, 4vw, 48px);
            font-weight: 800;
            line-height: 1.1;
        }

        .brand-copy p {
            max-width: 560px;
            margin: 15px auto 0;
            color: rgba(255, 255, 255, .9);
            font-size: 17px;
            font-weight: 500;
            line-height: 1.55;
        }

        .benefits-card {
            width: min(410px, 100%);
            margin-top: 22px;
            padding: 21px 26px;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 14px;
            background: rgba(255, 255, 255, .14);
            box-shadow: 0 20px 44px rgba(0, 26, 75, .22), inset 0 1px 0 rgba(255, 255, 255, .16);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            text-align: left;
        }

        .benefits-card h2 {
            margin: 0 0 13px;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
        }

        .benefits-card ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 9px;
        }

        .benefits-card li {
            position: relative;
            padding-left: 22px;
            color: rgba(255, 255, 255, .94);
            font-size: 13px;
            line-height: 1.45;
        }

        .benefits-card li::before {
            content: "";
            position: absolute;
            left: 0;
            top: .38em;
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: var(--cyan);
            box-shadow: 0 0 0 4px rgba(9, 184, 207, .16);
        }

        .form-panel {
            min-height: 100vh;
            padding: 34px 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .register-card {
            width: min(430px, 100%);
        }

        .register-card h2 {
            margin: 0;
            color: var(--navy);
            font-size: 34px;
            font-weight: 800;
            line-height: 1.12;
        }

        .register-card > p {
            margin: 7px 0 16px;
            color: var(--text-blue);
            font-size: 14px;
            line-height: 1.45;
        }

        .auth-alert {
            margin: 0 0 14px;
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #b91c1c;
            background: #fef2f2;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
        }

        .auth-alert svg {
            width: 18px;
            height: 18px;
            margin-top: 1px;
            flex: 0 0 auto;
        }

        .field-group {
            margin-bottom: 11px;
        }

        .field-group label,
        .role-label {
            display: block;
            margin-bottom: 6px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 700;
        }

        .role-grid {
            margin-bottom: 12px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .role-card {
            position: relative;
            min-height: 62px;
            padding: 10px 12px;
            border: 1px solid var(--cyan);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-blue);
            background: #fff;
            cursor: pointer;
            transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, color .18s ease, transform .18s ease;
        }

        .role-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .role-card svg {
            width: 23px;
            height: 23px;
            flex: 0 0 auto;
            stroke-width: 2;
        }

        .role-card span {
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
        }

        .role-card:has(input:checked) {
            border-color: var(--navy);
            background: #eaf8ff;
            color: var(--navy);
            box-shadow: 0 0 0 1px var(--navy), 0 10px 22px rgba(7, 5, 102, .12);
        }

        .role-card:hover {
            border-color: var(--navy);
            color: var(--navy);
            background: #f4fbff;
            box-shadow: 0 7px 16px rgba(9, 184, 207, .12);
            transform: translateY(-1px);
        }

        .role-card:focus-within {
            outline: 3px solid rgba(9, 184, 207, .2);
            outline-offset: 3px;
        }

        .field-error {
            display: none;
            margin: 5px 0 0;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
        }

        .field-error.visible {
            display: block;
        }

        .input-wrap.has-error {
            border-color: #f87171;
            background: #fef2f2;
        }

        .input-wrap.has-error:focus-within {
            border-color: #f87171;
            box-shadow: 0 0 0 4px rgba(248, 113, 113, .13);
        }

        .input-wrap {
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 12px;
            border: 1px solid var(--input-border);
            border-radius: 9px;
            background: var(--input-bg);
            color: var(--text-blue);
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .input-wrap:focus-within {
            border-color: var(--cyan);
            box-shadow: 0 0 0 4px rgba(9, 184, 207, .13);
            background: #eefcff;
        }

        .input-wrap svg {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }

        .input-wrap input {
            width: 100%;
            min-width: 0;
            border: 0;
            outline: 0;
            color: var(--navy);
            background: transparent;
            font: inherit;
            font-size: 13px;
            font-weight: 500;
        }

        .input-wrap input::placeholder {
            color: #5a87ab;
        }

        .password-toggle {
            width: 24px;
            height: 24px;
            padding: 0;
            border: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: var(--text-blue);
            background: transparent;
            cursor: pointer;
            border-radius: 5px;
        }

        .password-toggle:hover {
            color: var(--navy);
        }

        .password-toggle:focus-visible {
            outline: 2px solid var(--cyan);
            outline-offset: 2px;
        }

        .password-toggle .password-icon-hidden,
        .password-toggle[aria-pressed="true"] .password-icon-visible {
            display: none;
        }

        .password-toggle[aria-pressed="true"] .password-icon-hidden {
            display: block;
        }

        .btn {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--navy);
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: var(--navy);
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            background: #05035f;
            box-shadow: var(--shadow);
        }

        .social-stack {
            margin: 12px 0 0;
            display: grid;
        }

        .registration-divider {
            margin: 13px 0;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 12px;
            color: #5a87ab;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .registration-divider::before,
        .registration-divider::after {
            content: "";
            height: 1px;
            background: #c7dfea;
        }

        .registration-option-note {
            margin: 0 0 10px;
            color: #3d6384;
            font-size: 13px;
            line-height: 1.5;
        }

        .btn-social {
            min-height: 44px;
            border-color: #c7dfea;
            color: var(--navy);
            background: #fff;
            gap: 11px;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(15, 76, 117, .06);
        }

        .btn-social:hover {
            border-color: #8ed8ed;
            color: var(--navy);
            background: #f7fcff;
            box-shadow: 0 8px 18px rgba(15, 76, 117, .1);
        }

        .google-mark {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
        }

        .form-footer {
            margin-top: 13px;
            text-align: center;
            display: grid;
            gap: 9px;
            color: var(--text-blue);
            font-size: 13px;
            font-weight: 500;
        }

        .form-footer a {
            color: var(--cyan-dark);
            font-weight: 700;
            text-underline-offset: 3px;
        }

        .form-footer a:hover,
        .form-footer a:focus-visible {
            color: var(--navy);
            text-decoration: underline;
            outline: none;
        }

        .form-footer .home-link {
            color: var(--navy);
            font-weight: 500;
        }

        @media (max-width: 980px) {
            .register-shell {
                grid-template-columns: 1fr;
            }

            .form-panel {
                order: 1;
            }

            .brand-panel {
                order: 2;
            }

            .brand-panel,
            .form-panel {
                min-height: auto;
            }

            .brand-panel {
                padding: 48px 28px 56px;
            }

            .brand-copy {
                margin-top: 38px;
            }

            .form-panel {
                padding: 44px 28px 56px;
            }
        }

        @media (max-width: 560px) {
            .brand-panel {
                padding: 42px 22px;
            }

            .brand-logo {
                width: min(240px, 72vw);
            }

            .brand-copy h1 {
                font-size: 34px;
            }

            .brand-copy p {
                font-size: 17px;
            }

            .benefits-card {
                padding: 22px;
            }

            .form-panel {
                padding: 34px 22px 48px;
            }

            .register-card h2 {
                font-size: 32px;
            }

            .role-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main class="register-shell">
        <?php
        renderAuthBrandPanel([
            'aria_label' => 'PrintEase benefits',
            'heading' => 'Print Made Easy!',
            'description' => 'Create an account to place orders, manage print requests, and connect with verified print shops.',
            'supporting_html' => '<div class="benefits-card">
                <h2>Benefits:</h2>
                <ul>
                    <li>Browse multiple print shops</li>
                    <li>Compare prices and services</li>
                    <li>Track orders in real-time</li>
                    <li>Secure and verified transactions</li>
                </ul>
            </div>',
        ]);
        ?>

        <section class="form-panel" aria-label="Create account form">
            <div class="register-card">
                <h2>Create Account</h2>
                <p>Create your PrintEase account</p>

                <?php if ($register_alert_message !== ''): ?>
                    <div class="auth-alert auth-alert-error" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 8v5" />
                            <path d="M12 16h.01" />
                        </svg>
                        <span><?php echo htmlspecialchars($register_alert_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form action="../../backend/actions/register_process.php" method="POST">
                    <?php echo csrfField(); ?>
                    <span class="role-label">I want to register as:</span>
                    <div class="role-grid">
                        <label class="role-card">
                            <input type="radio" name="role" value="customer" checked>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <circle cx="12" cy="10" r="3" />
                                <path d="M7 20c1-3 3-4 5-4s4 1 5 4" />
                            </svg>
                            <span>Customer</span>
                        </label>

                        <label class="role-card">
                            <input type="radio" name="role" value="shop_owner">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 9h18l-2-5H5L3 9Z" />
                                <path d="M5 9v11h14V9" />
                                <path d="M9 20v-6h6v6" />
                                <path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />
                            </svg>
                            <span>Print Shop Owner</span>
                        </label>
                    </div>
                    <span class="field-error" id="role-error">Please choose an account type.</span>

                    <div class="field-group">
                        <label for="full_name">Full Name</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21a8 8 0 0 0-16 0" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            <input id="full_name" type="text" name="full_name" placeholder="Juan Dela Cruz" autocomplete="name">
                        </div>
                        <span class="field-error" id="full_name-error">Please enter your full name.</span>
                    </div>

                    <div class="field-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m4 4 16 0 0 16-16 0z" />
                                <path d="m22 6-10 7L2 6" />
                            </svg>
                            <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email">
                        </div>
                        <span class="field-error" id="email-error">Please enter a valid email address.</span>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input id="password" type="password" name="password" placeholder="Create a password" autocomplete="new-password">
                            <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle="password">
                                <svg class="password-icon-visible" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="password-icon-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m3 3 18 18" />
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                    <path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c6.5 0 10 8 10 8a18.5 18.5 0 0 1-2.1 3.2" />
                                    <path d="M6.6 6.6C3.6 8.4 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 5.4-1.6" />
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="password-error">Password must be at least 8 characters long.</span>
                    </div>

                    <div class="field-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                <path d="m9 16 2 2 4-4" />
                            </svg>
                            <input id="confirm_password" type="password" name="confirm_password" placeholder="Retype your password" autocomplete="new-password">
                            <button class="password-toggle" type="button" aria-label="Show password confirmation" aria-pressed="false" data-password-toggle="confirm_password">
                                <svg class="password-icon-visible" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="password-icon-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m3 3 18 18" />
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                    <path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c6.5 0 10 8 10 8a18.5 18.5 0 0 1-2.1 3.2" />
                                    <path d="M6.6 6.6C3.6 8.4 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 5.4-1.6" />
                                </svg>
                            </button>
                        </div>
                        <span class="field-error" id="confirm_password-error">Please retype your password.</span>
                    </div>

                    <button class="btn" type="submit" name="register">Create Account</button>
                </form>

                <div class="registration-divider"><span>or</span></div>

                <div class="social-stack" aria-label="Social registration options">
                    <a class="btn btn-social" href="../../backend/oauth/oauth_start.php?provider=google">
                        <svg class="google-mark" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z" />
                            <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.36l-3.24-2.54c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z" />
                            <path fill="#FBBC05" d="M6.39 13.93A6.02 6.02 0 0 1 6.08 12c0-.67.11-1.32.31-1.93V7.45H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.55l3.35-2.62Z" />
                            <path fill="#EA4335" d="M12 5.94c1.47 0 2.79.5 3.83 1.5l2.87-2.87A9.62 9.62 0 0 0 12 2a10 10 0 0 0-8.96 5.45l3.35 2.62C7.18 7.7 9.39 5.94 12 5.94Z" />
                        </svg>
                        <span>Continue with Google</span>
                    </a>
                </div>

                <div class="form-footer">
                    <span>Already have an account? <a href="login.php">Sign in</a></span>
                    <a class="home-link" href="../../index.php">&larr; Back to Home</a>
                </div>
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;

                var showPassword = input.type === 'password';
                input.type = showPassword ? 'text' : 'password';
                button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            });
        });

        (function () {
            var form = document.querySelector('form[action="../../backend/actions/register_process.php"]');
            var fullName = document.getElementById('full_name');
            var email = document.getElementById('email');
            var password = document.getElementById('password');
            var confirmation = document.getElementById('confirm_password');

            if (!form) return;

            function showError(input, errorId, message) {
                var errorEl = document.getElementById(errorId);
                if (errorEl) {
                    if (message) errorEl.textContent = message;
                    errorEl.classList.add('visible');
                }
                var wrap = input ? input.closest('.input-wrap') : null;
                if (wrap) wrap.classList.add('has-error');
            }

            function clearError(input, errorId) {
                var errorEl = document.getElementById(errorId);
                if (errorEl) errorEl.classList.remove('visible');
                var wrap = input ? input.closest('.input-wrap') : null;
                if (wrap) wrap.classList.remove('has-error');
            }

            function clearRoleError() {
                var errorEl = document.getElementById('role-error');
                if (errorEl) errorEl.classList.remove('visible');
            }

            function isValidEmail(value) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            }

            fullName.addEventListener('input', function () { clearError(fullName, 'full_name-error'); });
            email.addEventListener('input', function () { clearError(email, 'email-error'); });
            password.addEventListener('input', function () {
                clearError(password, 'password-error');
                if (confirmation.value !== '' && confirmation.value === password.value) {
                    clearError(confirmation, 'confirm_password-error');
                }
            });
            confirmation.addEventListener('input', function () {
                clearError(confirmation, 'confirm_password-error');
            });

            document.querySelectorAll('input[name="role"]').forEach(function (radio) {
                radio.addEventListener('change', clearRoleError);
            });

            form.addEventListener('submit', function (e) {
                var valid = true;

                var selectedRole = form.querySelector('input[name="role"]:checked');
                if (!selectedRole) {
                    document.getElementById('role-error').classList.add('visible');
                    valid = false;
                } else {
                    clearRoleError();
                }

                if (fullName.value.trim() === '') {
                    showError(fullName, 'full_name-error');
                    valid = false;
                } else {
                    clearError(fullName, 'full_name-error');
                }

                if (email.value.trim() === '') {
                    showError(email, 'email-error', 'Please enter your email address.');
                    valid = false;
                } else if (!isValidEmail(email.value.trim())) {
                    showError(email, 'email-error', 'Please enter a valid email address.');
                    valid = false;
                } else {
                    clearError(email, 'email-error');
                }

                if (password.value === '') {
                    showError(password, 'password-error', 'Please create a password.');
                    valid = false;
                } else if (password.value.length < 8) {
                    showError(password, 'password-error', 'Password must be at least 8 characters long.');
                    valid = false;
                } else {
                    clearError(password, 'password-error');
                }

                if (confirmation.value === '') {
                    showError(confirmation, 'confirm_password-error', 'Please retype your password.');
                    valid = false;
                } else if (password.value !== confirmation.value) {
                    showError(confirmation, 'confirm_password-error', 'Passwords do not match. Please retype your password.');
                    valid = false;
                } else {
                    clearError(confirmation, 'confirm_password-error');
                }

                if (!valid) {
                    e.preventDefault();
                }
            });
        })();
    </script>
</body>

</html>
