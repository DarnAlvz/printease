<?php
$login_alert_type = '';
$login_alert_message = '';

$login_success_messages = [
    'success' => 'Account created successfully. You can now sign in.',
];

$login_error_messages = [
    'email_not_found' => 'Email not found. Please check your email address.',
    'incorrect_password' => 'Incorrect password. Please try again.',
    'rejected' => 'Your account has been rejected. Please contact the administrator.',
    'invalid_role' => 'Invalid role detected. Please contact the administrator.',
];

if (isset($_GET['registered'], $login_success_messages[$_GET['registered']])) {
    $login_alert_type = 'success';
    $login_alert_message = $login_success_messages[$_GET['registered']];
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #070566;
            --blue-deep: #0a3d86;
            --blue: #006fa6;
            --cyan: #09b8cf;
            --cyan-dark: #008fc1;
            --input-bg: #eaf4ff;
            --input-border: #77d5ff;
            --text-blue: #0070bf;
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

        .login-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(480px, 1fr);
        }

        .brand-panel {
            min-height: 100vh;
            padding: 72px 56px;
            color: #fff;
            background:
                radial-gradient(circle at 28% 22%, rgba(19, 102, 180, .48), transparent 32%),
                linear-gradient(145deg, #070566 0%, #0a448c 48%, #00709d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-content {
            width: min(560px, 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: left;
        }

        .brand-logo {
            width: min(310px, 56vw);
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 18px 32px rgba(0, 0, 0, .24));
        }

        .welcome-copy {
            width: 100%;
            margin-top: 68px;
        }

        .welcome-copy h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(38px, 4.2vw, 50px);
            font-weight: 800;
            line-height: 1.1;
        }

        .welcome-copy p {
            max-width: 560px;
            margin: 22px 0 0;
            color: rgba(255, 255, 255, .9);
            font-size: 20px;
            font-weight: 500;
            line-height: 1.45;
        }

        .trust-row {
            margin-top: 52px;
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .trust-icon {
            width: 50px;
            height: 50px;
            color: var(--cyan);
            flex: 0 0 auto;
        }

        .trust-divider {
            width: 1px;
            height: 48px;
            background: rgba(255, 255, 255, .28);
        }

        .trust-row span {
            display: block;
            color: var(--cyan);
            font-size: 15px;
            font-weight: 500;
        }

        .trust-row strong {
            display: block;
            margin-top: 8px;
            color: #fff;
            font-size: 24px;
            font-weight: 700;
        }

        .form-panel {
            min-height: 100vh;
            padding: 72px 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .login-card {
            width: min(448px, 100%);
        }

        .login-card h2 {
            margin: 0;
            color: var(--navy);
            font-size: 36px;
            font-weight: 800;
            line-height: 1.12;
        }

        .login-card > p {
            margin: 10px 0 36px;
            color: var(--text-blue);
            font-size: 15px;
            line-height: 1.45;
        }

        .auth-alert {
            margin: -18px 0 26px;
            padding: 13px 15px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
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

        .auth-alert-success {
            border: 1px solid #8ee6c1;
            color: #047857;
            background: #ecfdf5;
        }

        .auth-alert-error {
            border: 1px solid #fecaca;
            color: #b91c1c;
            background: #fef2f2;
        }

        .field-group {
            margin-bottom: 26px;
        }

        .field-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 700;
        }

        .input-wrap {
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 0 16px;
            border: 1px solid var(--input-border);
            border-radius: 7px;
            background: var(--input-bg);
            color: var(--text-blue);
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .input-wrap:focus-within {
            border-color: var(--cyan);
            box-shadow: 0 0 0 4px rgba(9, 184, 207, .13);
            background: #f3f9ff;
        }

        .input-wrap svg {
            width: 21px;
            height: 21px;
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
            font-size: 15px;
            font-weight: 500;
        }

        .input-wrap input::placeholder {
            color: #5a87ab;
        }

        .form-options {
            margin: 6px 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 500;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .remember input {
            width: 15px;
            height: 15px;
            margin: 0;
            accent-color: var(--cyan);
        }

        .forgot-link {
            border: 0;
            padding: 0;
            color: var(--navy);
            background: transparent;
            font: inherit;
            cursor: default;
        }

        .btn {
            width: 100%;
            min-height: 48px;
            border: 0;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: var(--navy);
        }

        .btn-primary:hover {
            background: #05035f;
        }

        .btn-cyan {
            background: var(--cyan);
        }

        .btn-cyan:hover {
            background: var(--cyan-dark);
        }

        .divider {
            margin: 58px 0 32px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 18px;
            color: var(--text-blue);
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: "";
            height: 1px;
            background: #9bddf4;
        }

        .back-home {
            margin: 18px 0 26px;
            display: block;
            text-align: center;
            color: var(--navy);
            font-size: 13px;
            font-weight: 500;
        }

        .demo-row {
            min-height: 44px;
            padding-top: 24px;
            border-top: 1px solid #9bddf4;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: var(--text-blue);
            font-size: 13px;
            font-weight: 700;
        }

        .demo-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .demo-label svg,
        .demo-caret {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        @media (max-width: 980px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .brand-panel,
            .form-panel {
                min-height: auto;
            }

            .brand-panel {
                padding: 56px 28px;
            }

            .brand-content {
                text-align: center;
            }

            .welcome-copy {
                margin-top: 36px;
            }

            .welcome-copy p {
                margin-right: auto;
                margin-left: auto;
            }

            .trust-row {
                justify-content: center;
            }

            .form-panel {
                padding: 56px 28px 72px;
            }
        }

        @media (max-width: 560px) {
            .brand-panel {
                padding: 42px 22px;
            }

            .brand-logo {
                width: min(240px, 72vw);
            }

            .welcome-copy h1 {
                font-size: 34px;
            }

            .welcome-copy p {
                font-size: 17px;
            }

            .trust-row {
                margin-top: 34px;
                gap: 16px;
            }

            .trust-row strong {
                font-size: 20px;
            }

            .form-panel {
                padding: 44px 22px 60px;
            }

            .login-card h2 {
                font-size: 32px;
            }

            .form-options {
                align-items: flex-start;
                flex-direction: column;
            }

            .divider {
                margin-top: 42px;
            }
        }
    </style>
</head>

<body>
    <main class="login-shell">
        <section class="brand-panel" aria-label="PrintEase welcome">
            <div class="brand-content">
                <img class="brand-logo" src="../../assets/images/printing-logo.png" alt="PrintEase E-Printing System logo">

                <div class="welcome-copy">
                    <h1>Welcome Back!</h1>
                    <p>Access your dashboard to manage orders, track performance, and grow your printing business.</p>

                    <div class="trust-row">
                        <svg class="trust-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9V2h12v7" />
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                            <path d="M6 14h12v8H6z" />
                        </svg>
                        <span class="trust-divider" aria-hidden="true"></span>
                        <div>
                            <span>Trusted by</span>
                            <strong>50+ Print Shops</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="form-panel" aria-label="Sign in form">
            <div class="login-card">
                <h2>Sign In</h2>
                <p>Enter your credentials to access your account</p>

                <?php if ($login_alert_message !== ''): ?>
                    <div class="auth-alert auth-alert-<?php echo $login_alert_type; ?>" role="alert">
                        <?php if ($login_alert_type === 'success'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
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
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m4 4 16 0 0 16-16 0z" />
                                <path d="m22 6-10 7L2 6" />
                            </svg>
                            <input id="email" type="email" name="email" placeholder="Enter your email" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember">
                            <input type="checkbox" aria-label="Remember me visual option">
                            <span>Remember me</span>
                        </label>

                        <button class="forgot-link" type="button" aria-disabled="true">Forgot password?</button>
                    </div>

                    <button class="btn btn-primary" type="submit" name="login">Sign In</button>
                </form>

                <div class="divider">or</div>

                <a class="btn btn-cyan" href="register.php">Create New Account</a>
                <a class="back-home" href="../../index.php">&larr; Back to Home</a>
            </div>
        </section>
    </main>
</body>

</html>
