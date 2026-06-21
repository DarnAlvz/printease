<?php
session_start();

require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/auth_brand_panel.php';

$can_reset = !empty($_SESSION['otp_verified']) && !empty($_SESSION['otp_email']);
$alert_type = '';
$alert_message = '';

$success_messages = [
    '1' => 'OTP verified. Create your new password below.',
];

$error_messages = [
    'session_expired' => 'Your reset session has expired. Please request a new OTP.',
    'weak_password' => 'Use at least 8 characters for your new password.',
    'server' => 'We could not update your password right now. Please try again.',
];

if (!$can_reset) {
    $alert_type = 'error';
    $alert_message = $error_messages['session_expired'];
} elseif (isset($_GET['verified'], $success_messages[$_GET['verified']])) {
    $alert_type = 'success';
    $alert_message = $success_messages[$_GET['verified']];
} elseif (isset($_GET['error'], $error_messages[$_GET['error']])) {
    $alert_type = 'error';
    $alert_message = $error_messages[$_GET['error']];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PrintEase</title>
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
            'aria_label' => 'PrintEase password reset',
            'copy_class' => 'welcome-copy',
            'heading' => 'Create New Password',
            'description' => 'Choose a secure password so you can get back to managing your PrintEase account.',
        ]);
        ?>

        <section class="form-panel" aria-label="Reset password form">
            <div class="login-card">
                <h2>Reset Password</h2>
                <p>Use at least 8 characters for your new account password.</p>

                <?php if ($alert_message !== ''): ?>
                    <div class="auth-alert auth-alert-<?php echo $alert_type; ?>" role="alert">
                        <?php if ($alert_type === 'success'): ?>
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
                        <span><?php echo htmlspecialchars($alert_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($can_reset): ?>
                    <form action="../../backend/actions/reset_password_process.php" method="POST">
                        <div class="field-group">
                            <label for="password">New Password</label>
                            <div class="input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="11" width="18" height="11" rx="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                                <input id="password" type="password" name="password" placeholder="Enter new password"
                                    autocomplete="new-password" minlength="8" required>
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
                            <p class="field-hint">Your old password will stop working after this update.</p>
                        </div>

                        <button class="btn btn-primary" type="submit">Update Password</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-primary" href="forgot_password.php">Request New OTP</a>
                <?php endif; ?>

                <a class="back-home" href="login.php">&larr; Back to Sign In</a>
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
