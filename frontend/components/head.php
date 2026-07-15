<?php

require_once __DIR__ . '/branding.php';

function renderPrintEaseIcons()
{
    $icon16 = htmlspecialchars(printEaseAssetUrl('assets/images/favicon-16x16.png'), ENT_QUOTES, 'UTF-8');
    $icon32 = htmlspecialchars(printEaseAssetUrl('assets/images/favicon-32x32.png'), ENT_QUOTES, 'UTF-8');
    $appleIcon = htmlspecialchars(printEaseAssetUrl('assets/images/apple-touch-icon.png'), ENT_QUOTES, 'UTF-8');

    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $icon16 . '">' . PHP_EOL;
    echo '    <link rel="icon" type="image/png" sizes="32x32" href="' . $icon32 . '">' . PHP_EOL;
    echo '    <link rel="apple-touch-icon" sizes="180x180" href="' . $appleIcon . '">' . PHP_EOL;
    echo '    <meta name="theme-color" content="#03045e">' . PHP_EOL;
    echo '    <link rel="manifest" href="' . htmlspecialchars(printEaseAssetUrl('manifest.json'), ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;

    if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo '    <meta name="csrf-token" content="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
}

function renderPrintEaseSWRegistration()
{
    ?>
    <script>
        (function () {
            if (!('serviceWorker' in navigator)) return;

            var isLocal = ['localhost', '127.0.0.1'].indexOf(location.hostname) !== -1;

            if (isLocal) {
                navigator.serviceWorker.getRegistrations().then(function (regs) {
                    regs.forEach(function (reg) { reg.unregister(); });
                });
                if ('caches' in window) {
                    caches.keys().then(function (names) {
                        names.forEach(function (n) { if (n.indexOf('printease-') === 0) caches.delete(n); });
                    });
                }
                return;
            }

            navigator.serviceWorker.register('<?php echo htmlspecialchars(printEaseAssetUrl("service-worker.js"), ENT_QUOTES, "UTF-8"); ?>')
                .then(function () { })
                .catch(function () { });
        })();
    </script>
    <?php
}

