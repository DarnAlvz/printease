<?php
require_once __DIR__ . '/../../backend/includes/session.php';
secureSession();

require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../../backend/includes/functions.php';
require_once __DIR__ . '/../components/head.php';
require_once __DIR__ . '/../components/auth_brand_panel.php';

function maskRegistrationEmail($email)
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

$pending = $_SESSION['registration_pending'] ?? null;
$email = is_array($pending) ? (string) ($pending['email'] ?? '') : '';
$masked_email = maskRegistrationEmail($email);
$can_verify = is_array($pending) && $email !== '' && !empty($pending['otp']) && !empty($pending['otp_expires']);
$alert_type = '';
$alert_message = '';

$success_messages = [
    '1' => 'Verification code sent. Please check your email inbox.',
];

$error_messages = [
    'session_expired' => 'Your registration session has expired. Please register again.',
    'expired' => 'That verification code has expired. Please register again to request a new code.',
    'incomplete' => 'Please enter the complete 6-digit code.',
    'invalid_otp' => 'The verification code you entered is incorrect. Please try again.',
    'too_many_otp_attempts' => 'Too many incorrect attempts. Please register again to request a new code.',
    'server' => 'An unexpected error occurred. Please try again.',
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
    <title>Verify Registration - PrintEase</title>
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
            'aria_label' => 'PrintEase registration verification',
            'copy_class' => 'welcome-copy',
            'heading' => 'Verify Your Email',
            'description' => 'Enter the verification code we sent to finish creating your PrintEase account.',
        ]);
        ?>

        <section class="form-panel" aria-label="Registration verification form">
            <div class="login-card">
                <h2>Verify Email</h2>
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
                    <form action="../../backend/actions/verify_registration.php" method="POST" data-otp-form>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="otp" id="otp" required>

                        <div class="field-group">
                            <label for="otp-digit-1">Verification Code</label>
                            <div class="otp-grid" aria-label="6-digit registration code">
                                <?php for ($index = 1; $index <= 6; $index++): ?>
                                    <input id="otp-digit-<?php echo $index; ?>" class="otp-input" type="text"
                                        inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code"
                                        aria-label="Verification digit <?php echo $index; ?>" data-otp-digit required>
                                <?php endfor; ?>
                            </div>
                            <p class="field-hint">The code expires 5 minutes after it is sent.</p>
                        </div>

                        <button class="btn btn-primary" type="submit">Verify Email</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-primary" href="register.php">Register Again</a>
                <?php endif; ?>

                <div class="auth-link-row">
                    <a href="register.php">Use another email</a>
                    <a href="login.php">Back to Sign In</a>
                </div>
            </div>
        </section>
    </main>

    <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
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

    <?php if ($alert_type === 'error' && in_array($_GET['error'] ?? '', ['expired', 'session_expired', 'too_many_otp_attempts'], true)): ?>
    <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
        (function () {
            var countdown = 3;
            var alertEl = document.querySelector('.auth-alert');
            if (!alertEl) return;

            var timer = setInterval(function () {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(timer);
                    window.location.href = 'register.php';
                }
            }, 1000);
        })();
    </script>
    <?php endif; ?>
</body>

</html>
