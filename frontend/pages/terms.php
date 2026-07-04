<?php
require_once __DIR__ . '/../../backend/config/app.php';
require_once __DIR__ . '/../components/head.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - PrintEase</title>
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
            <h1>Terms of Service</h1>
            <p class="policy-updated">Last updated: July 3, 2026</p>

            <section>
                <h2>Use of PrintEase</h2>
                <p>PrintEase helps customers connect with print shops, place orders, track requests, and manage related transactions. By using the platform, you agree to provide accurate information and use the service only for lawful printing and business purposes.</p>
            </section>

            <section>
                <h2>Accounts and Security</h2>
                <p>You are responsible for keeping your login details secure and for activities made through your account. If you believe your account was accessed without permission, contact the PrintEase administrator as soon as possible.</p>
            </section>

            <section>
                <h2>Orders and Payments</h2>
                <p>Customers are responsible for reviewing order details before submitting them. Print shops are responsible for keeping service, price, status, and payment information accurate. Payment confirmations may be reviewed before an order is processed.</p>
            </section>

            <section>
                <h2>Acceptable Content</h2>
                <p>Do not upload or request printing of files that are illegal, harmful, abusive, fraudulent, or that violate another person's rights. PrintEase may restrict access or reject orders that appear to violate these terms.</p>
            </section>

            <section>
                <h2>Changes to These Terms</h2>
                <p>PrintEase may update these terms when platform features or policies change. Continued use of the service after updates means you accept the revised terms.</p>
            </section>
        </article>
    </main>
</body>

</html>
