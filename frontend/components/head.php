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
}

