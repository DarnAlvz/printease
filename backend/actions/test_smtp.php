<?php
session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helper/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$output = '';

if ($isPost) {
    ob_start();
    echo "<pre style='font-family:monospace;font-size:13px;padding:16px;background:#1e1e2e;color:#cdd6f4;border-radius:8px;white-space:pre-wrap;word-break:break-all;'>\n";

    echo "=== 1. ENV VARS ===\n";
    $smtpHost = getenv('SMTP_HOST');
    $smtpUser = getenv('SMTP_USER');
    $smtpPass = getenv('SMTP_PASS');
    $mailUser = getenv('MAIL_USER');
    $putenvExists = function_exists('putenv');
    echo "putenv() exists: " . var_export($putenvExists, true) . "\n";
    echo "SMTP_HOST: " . var_export($smtpHost, true) . "\n";
    echo "SMTP_USER: " . var_export($smtpUser, true) . "\n";
    echo "SMTP_PASS: " . ($smtpPass !== false ? "(set, " . strlen($smtpPass) . " chars)" : "(NOT SET)") . "\n";
    echo "MAIL_USER: " . var_export($mailUser, true) . "\n";
    echo "APP_ENV: " . var_export(getenv('APP_ENV'), true) . "\n";
    echo "BASE_URL: " . var_export(getenv('BASE_URL'), true) . "\n";

    if ($smtpHost === false || $smtpHost === '' || $smtpUser === false || $smtpUser === '' || $smtpPass === false || $smtpPass === '') {
        echo "\n*** SMTP ENV VARS NOT LOADED — add SMTP_HOST, SMTP_USER, SMTP_PASS to .env ***\n";
    }

    echo "\n=== 2. TCP CONNECTION TEST ===\n";
    $host = $smtpHost ?: 'cpanel11wh.jpt1.cloud.z.com';
    $fp = @fsockopen($host, 587, $errno, $errstr, 10);
    if (!$fp) {
        echo "FAILED: $errno - $errstr\n";
    } else {
        echo "TCP connection to $host:587 OK\n";
        fclose($fp);
    }

    echo "\n=== 3. PHPMAILER SMTP TEST (z.com server) ===\n";
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->SMTPAutoTLS = false;
        $mail->Timeout = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) {
            echo htmlspecialchars($str) . "\n";
        };

        $fromDomain = parse_url(BASE_URL, PHP_URL_HOST);
        $fromAddr = 'noreply@' . $fromDomain;
        $mail->setFrom($fromAddr, 'PrintEase');
        $mail->addAddress($smtpUser . '@' . $fromDomain);
        $mail->isHTML(true);
        $mail->Subject = "PrintEase SMTP Test (z.com)";
        $mail->Body = "<h3>SMTP Test</h3><p>If you see this, z.com SMTP is working.</p>";
        $mail->AltBody = "SMTP Test - If you see this, z.com SMTP is working.";

        $mail->send();
        echo "\n*** SMTP SEND SUCCESS ***\n";
    } catch (Exception $e) {
        echo "\n*** SMTP SEND FAILED ***\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "ErrorInfo: " . $mail->ErrorInfo . "\n";
    }

    echo "\n=== 4. PHP mail() FALLBACK TEST ===\n";
    $fromAddr = 'noreply@' . parse_url(BASE_URL, PHP_URL_HOST);
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: PrintEase <$fromAddr>\r\n";
    $sent = @mail($smtpUser . '@' . parse_url(BASE_URL, PHP_URL_HOST), "PrintEase mail() Test", "<p>mail() fallback test</p>", $headers);
    echo "mail() result: " . ($sent ? "SUCCESS (check inbox/spam)" : "FAILED") . "\n";

    echo "\n=== 5. LOG FILE TEST ===\n";
    $logFile = __DIR__ . '/../runtime/mail_errors.log';
    logMailError('test_smtp', 'Diagnostic test executed');
    echo "Log file: $logFile\n";
    echo "Log writable: " . (is_writable(dirname($logFile)) ? 'YES' : 'NO') . "\n";
    if (file_exists($logFile)) {
        echo "Recent log entries:\n";
        $lines = file($logFile);
        $recent = array_slice($lines, -10);
        foreach ($recent as $line) {
            echo "  " . htmlspecialchars(trim($line)) . "\n";
        }
    }

    echo "\n=== 6. SESSION ERROR ===\n";
    if (isset($_SESSION['last_mail_error'])) {
        echo "Last mail error: " . $_SESSION['last_mail_error'] . "\n";
    } else {
        echo "No mail error in session.\n";
    }

    echo "\n</pre>";
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrintEase - SMTP Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f0f23; color: #e2e8f0; min-height: 100vh; display: flex; justify-content: center; padding: 40px 16px; }
        .container { max-width: 700px; width: 100%; }
        h1 { font-size: 24px; margin-bottom: 8px; color: #7dd3fc; }
        p.subtitle { color: #94a3b8; margin-bottom: 24px; font-size: 14px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        .warning { background: #78350f; border-color: #b45309; }
        .warning h2 { color: #fbbf24; }
        .warning p { color: #fde68a; }
        form { display: flex; gap: 12px; }
        button { padding: 12px 28px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #1d4ed8; }
        button:disabled { background: #475569; cursor: not-allowed; }
        .result { margin-top: 20px; }
        footer { text-align: center; color: #64748b; font-size: 12px; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PrintEase SMTP Diagnostic</h1>
        <p class="subtitle">This page tests email sending from the production server. Delete after confirming the fix works.</p>

        <div class="card warning">
            <h2>Remove After Testing</h2>
            <p>Delete this file (<code>backend/actions/test_smtp.php</code>) from the server once SMTP is confirmed working. This page exposes environment details.</p>
        </div>

        <div class="card">
            <form method="POST">
                <button type="submit" <?= $isPost ? 'disabled' : '' ?>>
                    <?= $isPost ? 'Test Completed' : 'Run SMTP Diagnostic Test' ?>
                </button>
            </form>
        </div>

        <?php if ($output): ?>
            <div class="card result">
                <?= $output ?>
            </div>
        <?php endif; ?>

        <footer>PrintEase SMTP Diagnostic Tool &mdash; Temporary</footer>
    </div>
</body>
</html>
