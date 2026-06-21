<?php
session_start();
define('DB_CONNECTION_OPTIONAL', true);
require_once __DIR__ . '/backend/config/app.php';
require_once __DIR__ . '/backend/includes/auth.php';
require_once __DIR__ . '/frontend/components/head.php';

redirectIfAuthenticated($conn);

if (!isset($_SESSION['seen_splash'])) {
    $_SESSION['seen_splash'] = true;
    header("Location: frontend/splash.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/index.css">
    <title>PrintEase E-Printing System</title>
    <?php renderPrintEaseIcons(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

</head>

<body>
    <header class="site-header">
        <div class="nav-wrap">
            <a class="brand-link" href="#home" aria-label="PrintEase home">
                <?php renderPrintEaseLogo(['class' => 'brand-logo']); ?>
            </a>

            <nav class="nav-links" aria-label="Main navigation">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#contact">Contact</a>
            </nav>

            <div class="nav-actions">
                <a class="btn btn-primary login-btn" href="frontend/pages/login.php">Login</a>
            </div>
        </div>
    </header>

    <main>
        <section class="hero" id="home">
            <div class="hero-inner">
                <h1>Modern Printing Made Easy</h1>
                <p>
                    Connect customers with verified print shops in Calbayog City. Streamline your printing business with
                    our comprehensive e-printing management system.
                </p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="frontend/pages/login.php">Get Started</a>
                    <a class="btn btn-cyan" href="frontend/pages/register.php">Register Now</a>
                </div>

                <?php renderPrintEaseLogo(['class' => 'hero-logo', 'alt' => 'E-Printing System logo']); ?>
            </div>
        </section>

        <section class="features" id="features">
            <div class="section-head">
                <h2>Powerful Features</h2>
                <p>Everything you need to manage your printing business efficiently</p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <path d="M14 2v6h6" />
                            <path d="M9 13h6" />
                            <path d="M9 17h6" />
                            <path d="M9 9h1" />
                        </svg>
                    </div>
                    <h3>Easy Order Management</h3>
                    <p>Track and manage all your printing orders in one centralized dashboard. Real-time updates and
                        notifications keep you informed.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>
                    <h3>Secure Transactions</h3>
                    <p>Built with security in mind. All transactions are encrypted and protected, ensuring your business
                        data stays safe.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <path d="m9 11 3 3L22 4" />
                        </svg>
                    </div>
                    <h3>Verified Print Shops</h3>
                    <p>Only verified and trusted print shops. Build customer confidence with our verification system and
                        quality assurance.</p>
                </article>
            </div>
        </section>
    </main>

    <footer class="site-footer" id="contact">
        <div class="footer-inner">
            <div class="footer-grid">
                <div>
                    <h3>E-Printing System</h3>
                    <p>Modernizing print shops in Calbayog City</p>
                </div>
                <div>
                    <h3>Contact</h3>
                    <p>
                        Email:
                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=printease122@gmail.com" target="_blank"
                            rel="noopener noreferrer">
                            help@gmail.com
                        </a>
                        <br>
                        Phone: +63 970 061 1209
                    </p>
                </div>

                <div>
                    <h3>Location</h3>
                    <p>Calbayog City<br>Samar, Philippines</p>
                </div>
            </div>

            <div class="footer-bottom">
                &copy; 2026 E-Printing System. All rights reserved.
            </div>
        </div>
    </footer>
</body>

</html>
