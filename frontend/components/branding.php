<?php

function printEaseAssetUrl($path)
{
    $base_url = defined('BASE_URL') ? BASE_URL : '/printease/';
    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}

function renderPrintEaseLogo(array $options = [])
{
    $class = (string) ($options['class'] ?? 'brand-logo');
    $decorative = !empty($options['decorative']);
    $alt = $decorative ? '' : (string) ($options['alt'] ?? 'PrintEase E-Printing System logo');
    $attributes = $decorative ? ' aria-hidden="true"' : '';

    echo '<img src="' . htmlspecialchars(printEaseAssetUrl('assets/images/printing-logo.png'), ENT_QUOTES, 'UTF-8') . '"';
    echo ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
    echo ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $attributes . '>';
}

