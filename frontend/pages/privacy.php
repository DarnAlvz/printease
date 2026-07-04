<?php
require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../components/head.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - PrintEase</title>
    <?php renderPrintEaseIcons(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>

<body class="policy-page">
    <main class="policy-shell">
        <article class="policy-card">
            <a class="back-home policy-back" href="login.php">&larr; Back to Sign In</a>
            <p class="policy-eyebrow">PrintEase</p>
            <h1>Privacy Policy</h1>
            <p class="policy-updated">Last updated: July 3, 2026</p>

            <section>
                <h2>Information We Collect</h2>
                <p>PrintEase may collect account details, contact information, print order details, uploaded files, payment references, profile information, and activity needed to operate the platform.</p>
            </section>

            <section>
                <h2>How We Use Information</h2>
                <p>We use information to create and secure accounts, process orders, connect customers with print shops, send notifications, verify payment details, improve services, and support platform administration.</p>
            </section>

            <section>
                <h2>Sharing Information</h2>
                <p>Order and contact details may be shared between customers, print shop owners, and administrators when needed to complete a transaction or resolve an issue. PrintEase does not sell personal information.</p>
            </section>

            <section>
                <h2>Data Security</h2>
                <p>PrintEase uses reasonable safeguards to protect user information. No system is perfectly secure, so users should keep their passwords private and report suspicious activity promptly.</p>
            </section>

            <section>
                <h2>Your Choices</h2>
                <p>You may update your profile information through your account pages. For account concerns, corrections, or privacy questions, contact the PrintEase administrator.</p>
            </section>
        </article>
    </main>
</body>

</html>
