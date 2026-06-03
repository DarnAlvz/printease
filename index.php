<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrintEase E-Printing System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #070566;
            --navy-deep: #05035f;
            --cyan: #08b7d0;
            --cyan-dark: #008fc1;
            --sky-soft: #f1f7ff;
            --ink-soft: #006fb6;
            --card-blue: #f2f8ff;
            --card-mint: #effefe;
            --card-cream: #fffaf4;
            --shadow: 0 10px 22px rgba(7, 5, 102, .12);
            --border: #e4eaf3;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            color: var(--navy);
            font-family: 'Inter', Arial, sans-serif;
            background: #fff;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            min-height: 72px;
            border-bottom: 1px solid #dce3ec;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
            backdrop-filter: blur(10px);
        }

        .nav-wrap {
            width: min(1216px, calc(100% - 48px));
            min-height: 72px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 24px;
        }

        .brand-link {
            display: inline-flex;
            align-items: center;
            width: fit-content;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 42px;
            font-size: 15px;
            font-weight: 500;
        }

        .nav-links a {
            transition: color .18s ease;
        }

        .nav-links a:hover {
            color: var(--cyan-dark);
        }

        .nav-actions {
            display: flex;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 144px;
            min-height: 56px;
            padding: 0 28px;
            border-radius: 7px;
            border: 0;
            font-size: 15px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            color: #fff;
            background: var(--navy);
        }

        .btn-primary:hover {
            background: var(--navy-deep);
        }

        .btn-cyan {
            color: #fff;
            background: var(--cyan);
        }

        .btn-cyan:hover {
            background: var(--cyan-dark);
        }

        .login-btn {
            min-width: 88px;
            min-height: 36px;
            padding: 0 24px;
            border-radius: 7px;
            font-size: 15px;
        }

        .hero {
            min-height: 752px;
            padding: 82px 24px 84px;
            background: var(--sky-soft);
            text-align: center;
        }

        .hero-inner {
            max-width: 860px;
            margin: 0 auto;
        }

        .hero h1 {
            margin: 0;
            color: var(--navy);
            font-size: clamp(42px, 5vw, 60px);
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.12;
        }

        .hero p {
            max-width: 680px;
            margin: 20px auto 0;
            color: #0070bf;
            font-size: 20px;
            font-weight: 400;
            line-height: 1.45;
        }

        .hero-actions {
            margin-top: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-logo {
            display: block;
            width: min(280px, 62vw);
            height: auto;
            margin: 82px auto 0;
            object-fit: contain;
        }

        .features {
            padding: 84px 24px 80px;
            background: #fff;
        }

        .section-head {
            text-align: center;
        }

        .section-head h2 {
            margin: 0;
            color: var(--navy);
            font-size: clamp(32px, 3.2vw, 40px);
            font-weight: 800;
            line-height: 1.15;
        }

        .section-head p {
            margin: 14px 0 0;
            color: #006fb6;
            font-size: 15px;
            line-height: 1.6;
        }

        .feature-grid {
            width: min(1218px, 100%);
            margin: 66px auto 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 30px;
        }

        .feature-card {
            min-height: 268px;
            padding: 32px;
            border-radius: 10px;
            box-shadow: 0 3px 9px rgba(15, 23, 42, .15);
        }

        .feature-card:nth-child(1) {
            background: var(--card-blue);
        }

        .feature-card:nth-child(2) {
            background: var(--card-mint);
        }

        .feature-card:nth-child(3) {
            background: var(--card-cream);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 7px;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--navy);
        }

        .feature-card:nth-child(2) .feature-icon {
            background: #0789c2;
        }

        .feature-card:nth-child(3) .feature-icon {
            background: var(--cyan);
        }

        .feature-icon svg {
            width: 31px;
            height: 31px;
            stroke-width: 2.2;
        }

        .feature-card h3 {
            margin: 28px 0 0;
            color: var(--navy);
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
        }

        .feature-card p {
            margin: 14px 0 0;
            color: #006fb6;
            font-size: 16px;
            line-height: 1.5;
        }

        .site-footer {
            padding: 50px 24px 48px;
            color: #fff;
            background: var(--navy);
        }

        .footer-inner {
            width: min(1218px, 100%);
            margin: 0 auto;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 48px;
        }

        .footer-grid h3 {
            margin: 0 0 22px;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.25;
        }

        .footer-grid p {
            margin: 0;
            font-size: 15px;
            line-height: 1.55;
        }

        .footer-bottom {
            margin-top: 36px;
            padding-top: 34px;
            border-top: 1px solid rgba(255, 255, 255, .23);
            text-align: center;
            font-size: 15px;
        }

        @media (max-width: 900px) {
            .nav-wrap {
                width: min(100% - 32px, 720px);
                grid-template-columns: auto 1fr auto;
                gap: 14px;
            }

            .nav-links {
                gap: 18px;
                font-size: 14px;
            }

            .hero {
                min-height: auto;
                padding-top: 66px;
            }

            .feature-grid,
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .feature-grid {
                max-width: 520px;
                gap: 22px;
            }
        }

        @media (max-width: 640px) {
            .site-header {
                position: static;
            }

            .nav-wrap {
                min-height: auto;
                padding: 14px 0;
                grid-template-columns: 1fr;
                justify-items: center;
            }

            .nav-actions {
                justify-content: center;
            }

            .brand-logo {
                width: 46px;
                height: 46px;
            }

            .hero {
                padding: 54px 18px 64px;
            }

            .hero h1 {
                font-size: 38px;
            }

            .hero p {
                font-size: 17px;
            }

            .hero-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                max-width: 280px;
                margin: 0 auto;
            }

            .hero-logo {
                margin-top: 56px;
            }

            .features {
                padding: 64px 18px;
            }

            .feature-grid {
                margin-top: 42px;
            }

            .feature-card {
                min-height: auto;
                padding: 28px;
            }

            .footer-grid {
                gap: 32px;
            }
        }
    </style>
</head>

<body>
    <header class="site-header">
        <div class="nav-wrap">
            <a class="brand-link" href="#home" aria-label="PrintEase home">
                <img class="brand-logo" src="assets/images/printing-logo.png" alt="PrintEase E-Printing System logo">
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
                    Connect customers with verified print shops in Calbayog City. Streamline your printing business with our comprehensive e-printing management system.
                </p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="frontend/pages/register.php">Get Started</a>
                    <a class="btn btn-cyan" href="frontend/pages/register.php">Register Now</a>
                </div>

                <img class="hero-logo" src="assets/images/printing-logo.png" alt="E-Printing System logo">
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
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <path d="M14 2v6h6" />
                            <path d="M9 13h6" />
                            <path d="M9 17h6" />
                            <path d="M9 9h1" />
                        </svg>
                    </div>
                    <h3>Easy Order Management</h3>
                    <p>Track and manage all your printing orders in one centralized dashboard. Real-time updates and notifications keep you informed.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>
                    <h3>Secure Transactions</h3>
                    <p>Built with security in mind. All transactions are encrypted and protected, ensuring your business data stays safe.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <path d="m9 11 3 3L22 4" />
                        </svg>
                    </div>
                    <h3>Verified Print Shops</h3>
                    <p>Only verified and trusted print shops. Build customer confidence with our verification system and quality assurance.</p>
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
                    <p>Email: info@eprinting.com<br>Phone: +63 123 456 7890</p>
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
