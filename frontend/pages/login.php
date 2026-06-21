<?php
require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/auth_brand_panel.php';

define('DB_CONNECTION_OPTIONAL', true);
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/includes/auth.php';

redirectIfAuthenticated($conn);

$trusted_shop_html = '';

if ($conn instanceof mysqli) {
    try {
        $trusted_shop_result = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS total FROM print_shops WHERE permit_status = 'verified'"
        );

        if ($trusted_shop_result) {
            $trusted_shop_row = mysqli_fetch_assoc($trusted_shop_result);
            $trusted_shop_count = max(0, (int) ($trusted_shop_row['total'] ?? 0));
            $trusted_shop_label = $trusted_shop_count === 1 ? 'Print Shop' : 'Print Shops';

            $trusted_shop_html = '<div class="trust-row">
                    <svg class="trust-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 9V2h12v7" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <path d="M6 14h12v8H6z" />
                    </svg>
                    <span class="trust-divider" aria-hidden="true"></span>
                    <div><span>Trusted by</span><strong>' . $trusted_shop_count . ' ' . $trusted_shop_label . '</strong></div>
                </div>';
        }
    } catch (mysqli_sql_exception $exception) {
        $trusted_shop_html = '';
    }
}

$login_alert_type = '';
$login_alert_message = '';

$login_success_messages = [
    'success' => 'Account created successfully. You can now sign in.',
    'password_reset' => 'Password reset successfully. You can now sign in with your new password.',
];

$login_error_messages = [
    'email_not_found' => 'Email not found. Please check your email address.',
    'incorrect_password' => 'Incorrect password. Please try again.',
    'rejected' => 'Your account has been rejected. Please contact the administrator.',
    'inactive' => 'Your account has been deactivated. Please contact the administrator.',
    'invalid_role' => 'Invalid role detected. Please contact the administrator.',
    'oauth_invalid_provider' => 'That sign-in provider is not supported.',
    'oauth_not_configured' => 'Social sign-in is not configured yet. Please contact the administrator.',
    'oauth_denied' => 'Social sign-in was cancelled. Please try again.',
    'oauth_invalid_state' => 'Social sign-in expired or could not be verified. Please try again.',
    'oauth_failed' => 'Social sign-in failed. Please try again.',
    'oauth_missing_email' => 'Your social account did not share a verified email address.',
    'oauth_session_expired' => 'Your social sign-in session expired. Please try again.',
    'duplicate_email' => 'That email is already registered. Please sign in or use another email.',
    'too_many_attempts' => 'Too many sign-in attempts. Please wait 15 minutes before trying again.',
];

if (isset($_GET['registered'], $login_success_messages[$_GET['registered']])) {
    $login_alert_type = 'success';
    $login_alert_message = $login_success_messages[$_GET['registered']];
} elseif (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $login_alert_type = 'success';
    $login_alert_message = $login_success_messages['password_reset'];
} elseif (isset($_GET['error'], $login_error_messages[$_GET['error']])) {
    $login_alert_type = 'error';
    $login_alert_message = $login_error_messages[$_GET['error']];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PrintEase</title>
    <?php renderPrintEaseIcons(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <main class="login-shell">
        <?php
        renderAuthBrandPanel([
            'aria_label' => 'PrintEase welcome',
            'copy_class' => 'welcome-copy',
            'heading' => 'Welcome Back!',
            'description' => 'Access your dashboard to manage orders, track performance, and grow your printing business.',
            'supporting_position' => 'inside_copy',
            'supporting_html' => $trusted_shop_html,
        ]);
        ?>

        <section class="form-panel" aria-label="Sign in form">
            <div class="login-card">
                <h2>Sign In</h2>
                <p>Enter your credentials to access your account</p>

                <?php if ($login_alert_message !== ''): ?>
                    <div class="auth-alert auth-alert-<?php echo $login_alert_type; ?>" role="alert">
                        <?php if ($login_alert_type === 'success'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 8v5" />
                                <path d="M12 16h.01" />
                            </svg>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($login_alert_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form action="../../backend/actions/login_process.php" method="POST">
                    <div class="field-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m4 4 16 0 0 16-16 0z" />
                                <path d="m22 6-10 7L2 6" />
                            </svg>
                            <input id="email" type="email" name="email" placeholder="Enter your email"
                                autocomplete="email" required>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input id="password" type="password" name="password" placeholder="Enter your password"
                                autocomplete="current-password" required>
                            <button class="password-toggle" type="button" aria-label="Show password"
                                aria-pressed="false" data-password-toggle="password">
                                <svg class="password-icon-visible" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="password-icon-hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m3 3 18 18" />
                                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                    <path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c6.5 0 10 8 10 8a18.5 18.5 0 0 1-2.1 3.2" />
                                    <path d="M6.6 6.6C3.6 8.4 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 5.4-1.6" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember">
                            <input type="checkbox" name="remember_me" value="1" aria-label="Remember me">
                            <span>Remember me</span>
                        </label>

                        <a href="forgot_password.php" class="forgot-link">
                            Forgot password?
                        </a>
                    </div>

                    <button class="btn btn-primary" type="submit" name="login">Sign In</button>
                </form>

                <div class="social-stack" aria-label="Social sign in options">
                    <a class="btn btn-social" href="../../backend/oauth/oauth_start.php?provider=google">
                        <svg class="google-mark" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4"
                                d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z" />
                            <path fill="#34A853"
                                d="M12 22c2.7 0 4.97-.9 6.62-2.36l-3.24-2.54c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z" />
                            <path fill="#FBBC05"
                                d="M6.39 13.93A6.02 6.02 0 0 1 6.08 12c0-.67.11-1.32.31-1.93V7.45H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.55l3.35-2.62Z" />
                            <path fill="#EA4335"
                                d="M12 5.94c1.47 0 2.79.5 3.83 1.5l2.87-2.87A9.62 9.62 0 0 0 12 2a10 10 0 0 0-8.96 5.45l3.35 2.62C7.18 7.7 9.39 5.94 12 5.94Z" />
                        </svg>
                        <span>Continue with Google</span>
                    </a>
                </div>

                <p class="register-prompt">Don't have an account? <a href="register.php">Create account</a></p>
                <a class="back-home" href="../../index.php">&larr; Back to Home</a>
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
    </script>
</body>

</html>
