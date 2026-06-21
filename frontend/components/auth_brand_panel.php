<?php

require_once __DIR__ . '/branding.php';

function renderAuthBrandPanel(array $options)
{
    $aria_label = (string) ($options['aria_label'] ?? 'PrintEase');
    $copy_class = (string) ($options['copy_class'] ?? 'brand-copy');
    $heading = (string) ($options['heading'] ?? 'PrintEase');
    $description = (string) ($options['description'] ?? '');
    $supporting_html = (string) ($options['supporting_html'] ?? '');
    $supporting_position = (string) ($options['supporting_position'] ?? 'after_copy');
    ?>
    <section class="brand-panel" aria-label="<?php echo htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="brand-content">
            <?php renderPrintEaseLogo(['class' => 'brand-logo']); ?>

            <div class="<?php echo htmlspecialchars($copy_class, ENT_QUOTES, 'UTF-8'); ?>">
                <h1><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if ($supporting_position === 'inside_copy') echo $supporting_html; ?>
            </div>

            <?php if ($supporting_position !== 'inside_copy') echo $supporting_html; ?>
        </div>
    </section>
    <?php
}
