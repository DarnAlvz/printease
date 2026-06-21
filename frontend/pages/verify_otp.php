<?php
session_start();

require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/auth_brand_panel.php';

function maskResetEmail($email)
{
    $parts = explode('@', (string) $email, 2);
    if (count($parts) !== 2) {
        return '';
    }

    $name = $parts[0];
    $domain = $parts[1];
    $visible = substr($name, 0, 1);

    return $visible . str_repeat('*', max(3, strlen($name) - 1)) . '@' . $domain;
}

$alert_type = '';
$alert_message = '';
$email = $_SESSION['otp_email'] ?? '';
$masked_email = maskResetEmail($email);
$can_verify = $email !== '' && !empty($_SESSION['otp']) && !empty($_SESSION['otp_expires']);

$success_messages = [
    '1' => 'OTP sent successfully. Please check your Gmail inbox.',
];

$error_messages = [
    'session_expired' => 'Your reset session has expired. Please request a new OTP.',
    'expired' => 'That OTP has expired. Please request a new code.',
    'incomplete' => 'Please enter the complete 6-digit OTP.',
    'invalid_otp' => 'The OTP you entered is incorrect. Please try again.',
    'too_many_otp_attempts' => 'Too many incorrect OTP attempts. Please request a new code.',
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
    <title>Verify OTP - PrintEase</title>
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
            'aria_label' => 'PrintEase OTP verification',
            'copy_class' => 'welcome-copy',
            'heading' => 'Check Your Email',
            'description' => 'Use the verification code we sent to continue resetting your PrintEase password.',
        ]);
        ?>

        <section class="form-panel" aria-label="OTP verification form">
            <div class="login-card">
                <h2>Verify OTP</h2>
                <p>
                    Enter the 6-digit code sent to
                    <strong><?php echo htmlspecialchars($masked_email ?: 'your email', ENT_QUOTES, 'UTF-8'); ?></strong>.
                </p>

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

                <?php if ($can_verify): ?>
                    <form action="../../backend/actions/verify_otp.php" method="POST" data-otp-form>
                        <input type="hidden" name="otp" id="otp" required>

                        <div class="field-group">
                            <label for="otp-digit-1">One-Time Password</label>
                            <div class="otp-grid" aria-label="6-digit OTP">
                                <?php for ($index = 1; $index <= 6; $index++): ?>
                                    <input id="otp-digit-<?php echo $index; ?>" class="otp-input" type="text"
                                        inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code"
                                        aria-label="OTP digit <?php echo $index; ?>" data-otp-digit required>
                                <?php endfor; ?>
                            </div>
                            <p class="field-hint">The code expires 5 minutes after it is sent.</p>
                        </div>

                        <button class="btn btn-primary" type="submit">Verify OTP</button>
                    </form>
                <?php elseif ($email !== ''): ?>
                    <form action="../../backend/actions/send_otp.php" method="POST">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-primary" type="submit">Resend OTP</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-primary" href="forgot_password.php">Request OTP</a>
                <?php endif; ?>

                <div class="auth-link-row">
                    <?php if ($email !== ''): ?>
                        <form action="../../backend/actions/send_otp.php" method="POST">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="text-button" type="submit">Resend OTP</button>
                        </form>
                    <?php endif; ?>
                    <a href="forgot_password.php">Use another email</a>
                </div>

                <a class="back-home" href="login.php">&larr; Back to Sign In</a>
            </div>
        </section>
    </main>

    <script>
        (function () {
            var form = document.querySelector('[data-otp-form]');
            if (!form) return;

            var hiddenOtp = document.getElementById('otp');
            var inputs = Array.prototype.slice.call(document.querySelectorAll('[data-otp-digit]'));

            function syncOtpValue() {
                hiddenOtp.value = inputs.map(function (input) {
                    return input.value;
                }).join('');
            }

            inputs.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    input.value = input.value.replace(/\D/g, '').slice(0, 1);
                    if (input.value && inputs[index + 1]) {
                        inputs[index + 1].focus();
                    }
                    syncOtpValue();
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !input.value && inputs[index - 1]) {
                        inputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function (event) {
                    var pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    if (!pasted) return;

                    event.preventDefault();
                    inputs.forEach(function (field, pastedIndex) {
                        field.value = pasted[pastedIndex] || '';
                    });
                    syncOtpValue();

                    var nextIndex = Math.min(pasted.length, inputs.length) - 1;
                    if (inputs[nextIndex]) {
                        inputs[nextIndex].focus();
                    }
                });
            });

            form.addEventListener('submit', syncOtpValue);
        })();
    </script>
</body>

</html>
