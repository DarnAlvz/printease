<?php
require_once __DIR__ . '/../../backend/includes/session.php';
secureSession();

require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../../backend/includes/functions.php';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/auth_brand_panel.php';

$alert_type = '';
$alert_message = '';

$error_messages = [
    'invalid_email' => 'Please enter a valid email address.',
    'email_not_found' => 'No PrintEase account was found with that email address.',
    'mail_failed' => 'We could not send the OTP right now. Please check your mail settings or try again.',
    'server' => 'Something went wrong while preparing your OTP. Please try again.',
    'otp_wait' => 'Please wait at least 60 seconds before requesting another OTP.',
    'otp_hourly_limit' => 'Too many OTP requests for this email. Please try again later.',
    'too_many_requests' => 'Too many OTP requests from your connection. Please try again later.',
];

$success_messages = [
    '1' => 'OTP sent successfully. Please check your inbox.',
];

if (isset($_GET['sent'], $success_messages[$_GET['sent']])) {
    $alert_type = 'success';
    $alert_message = $success_messages[$_GET['sent']];
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
    <title>Forgot Password - PrintEase</title>
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
            'aria_label' => 'PrintEase password recovery',
            'copy_class' => 'welcome-copy',
            'heading' => 'Recover Access',
            'description' => 'Enter your account email and we will send a secure one-time code to help you reset your password.',
        ]);
        ?>

        <section class="form-panel" aria-label="Forgot password form">
            <div class="login-card">
                <h2>Forgot Password</h2>
                <p>We will send a 6-digit OTP to your registered email.</p>

                <?php if ($alert_message !== ''): ?>
                    <div class="auth-alert auth-alert-<?php echo $alert_type; ?>" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                            stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 8v5" />
                            <path d="M12 16h.01" />
                        </svg>
                        <span><?php echo htmlspecialchars($alert_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form action="../../backend/actions/send_otp.php" method="POST">
                    <?php echo csrfField(); ?>
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

                    <button class="btn btn-primary" type="submit">Send OTP</button>
                </form>

                <p class="register-prompt">Remember your password? <a href="login.php">Sign in</a></p>
                <a class="back-home" href="../../index.php">&larr; Back to Home</a>
            </div>
        </section>
    </main>
</body>

</html>
