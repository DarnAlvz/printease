<?php
$register_alert_message = '';

$register_error_messages = [
    'invalid_role' => 'Please choose a valid account type.',
    'duplicate_email' => 'That email is already registered. Please sign in or use another email.',
    'registration_failed' => 'Registration failed. Please check your details and try again.',
];

if (isset($_GET['error'], $register_error_messages[$_GET['error']])) {
    $register_alert_message = $register_error_messages[$_GET['error']];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PrintEase</title>
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
            padding: 70px 56px;
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
            width: min(320px, 58vw);
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 18px 32px rgba(0, 0, 0, .24));
        }

        .brand-copy {
            margin-top: 70px;
        }

        .brand-copy h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(38px, 4.3vw, 50px);
            font-weight: 800;
            line-height: 1.1;
        }

        .brand-copy p {
            max-width: 560px;
            margin: 22px auto 0;
            color: rgba(255, 255, 255, .9);
            font-size: 20px;
            font-weight: 500;
            line-height: 1.45;
        }

        .benefits-card {
            width: min(384px, 100%);
            margin-top: 34px;
            padding: 28px 34px 26px;
            border-radius: 10px;
            background: var(--panel-soft);
            text-align: left;
        }

        .benefits-card h2 {
            margin: 0 0 18px;
            color: #fff;
            text-align: center;
            font-size: 23px;
            font-weight: 800;
        }

        .benefits-card ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 13px;
        }

        .benefits-card li {
            position: relative;
            padding-left: 20px;
            color: rgba(255, 255, 255, .94);
            font-size: 15px;
            line-height: 1.35;
        }

        .benefits-card li::before {
            content: "";
            position: absolute;
            left: 0;
            top: .48em;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--cyan);
        }

        .form-panel {
            min-height: 100vh;
            padding: 54px 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .register-card {
            width: min(448px, 100%);
        }

        .register-card h2 {
            margin: 0;
            color: var(--navy);
            font-size: 36px;
            font-weight: 800;
            line-height: 1.12;
        }

        .register-card > p {
            margin: 10px 0 36px;
            color: var(--text-blue);
            font-size: 15px;
            line-height: 1.45;
        }

        .auth-alert {
            margin: -18px 0 26px;
            padding: 13px 15px;
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
            margin-bottom: 24px;
        }

        .field-group label,
        .role-label {
            display: block;
            margin-bottom: 10px;
            color: var(--navy);
            font-size: 15px;
            font-weight: 700;
        }

        .role-grid {
            margin-bottom: 26px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .role-card {
            min-height: 96px;
            border: 1px solid var(--input-border);
            border-radius: 7px;
            display: grid;
            place-items: center;
            gap: 8px;
            color: var(--text-blue);
            background: #fff;
            cursor: pointer;
            transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, color .18s ease;
        }

        .role-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .role-card svg {
            width: 32px;
            height: 32px;
            stroke-width: 2;
        }

        .role-card span {
            font-size: 13px;
            font-weight: 700;
        }

        .role-card:has(input:checked) {
            border-color: var(--navy);
            background: #f5f5fb;
            color: var(--navy);
            box-shadow: 0 0 0 1px var(--navy);
        }

        .role-card:hover {
            border-color: var(--cyan);
            box-shadow: 0 8px 18px rgba(9, 184, 207, .12);
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
            background: #eefcff;
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

        .btn {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: var(--navy);
            font-size: 15px;
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

        .form-footer {
            margin-top: 20px;
            text-align: center;
            display: grid;
            gap: 12px;
            color: var(--text-blue);
            font-size: 13px;
            font-weight: 500;
        }

        .form-footer a {
            color: var(--navy);
            font-weight: 600;
        }

        .form-footer .home-link {
            color: var(--navy);
            font-weight: 500;
        }

        @media (max-width: 980px) {
            .register-shell {
                grid-template-columns: 1fr;
            }

            .brand-panel,
            .form-panel {
                min-height: auto;
            }

            .brand-panel {
                padding: 56px 28px;
            }

            .brand-copy {
                margin-top: 38px;
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

            .brand-copy h1 {
                font-size: 34px;
            }

            .brand-copy p {
                font-size: 17px;
            }

            .benefits-card {
                padding: 24px;
            }

            .form-panel {
                padding: 44px 22px 60px;
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
        <section class="brand-panel" aria-label="PrintEase benefits">
            <div class="brand-content">
                <img class="brand-logo" src="../../assets/images/printing-logo.png" alt="PrintEase E-Printing System logo">

                <div class="brand-copy">
                    <h1>Print Made Easy!</h1>
                    <p>Join PrintEase to place orders, manage requests, and connect with verified print shops across Calbayog City.</p>
                </div>

                <div class="benefits-card">
                    <h2>Benefits:</h2>
                    <ul>
                        <li>Browse multiple print shops</li>
                        <li>Compare prices and services</li>
                        <li>Track orders in real-time</li>
                        <li>Secure and verified transactions</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="form-panel" aria-label="Create account form">
            <div class="register-card">
                <h2>Create Account</h2>
                <p>Choose your account type and register</p>

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
                    <span class="role-label">I want to register as:</span>
                    <div class="role-grid">
                        <label class="role-card">
                            <input type="radio" name="role" value="customer" checked required>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <circle cx="12" cy="10" r="3" />
                                <path d="M7 20c1-3 3-4 5-4s4 1 5 4" />
                            </svg>
                            <span>Customer</span>
                        </label>

                        <label class="role-card">
                            <input type="radio" name="role" value="shop_owner" required>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 9h18l-2-5H5L3 9Z" />
                                <path d="M5 9v11h14V9" />
                                <path d="M9 20v-6h6v6" />
                                <path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />
                            </svg>
                            <span>Print Shop Owner</span>
                        </label>
                    </div>

                    <div class="field-group">
                        <label for="full_name">Full Name</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 21a8 8 0 0 0-16 0" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            <input id="full_name" type="text" name="full_name" placeholder="Juan Dela Cruz" autocomplete="name" required>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m4 4 16 0 0 16-16 0z" />
                                <path d="m22 6-10 7L2 6" />
                            </svg>
                            <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input id="password" type="password" name="password" placeholder="Create a password" autocomplete="new-password" required>
                        </div>
                    </div>

                    <button class="btn" type="submit" name="register">Create Account</button>
                </form>

                <div class="form-footer">
                    <span>Already have an account? <a href="login.php">Sign In</a></span>
                    <a class="home-link" href="../../index.php">&larr; Back to Home</a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
