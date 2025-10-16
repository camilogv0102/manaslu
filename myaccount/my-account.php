<?php
/**
 * Plantilla principal para el área "Mi cuenta" de WooCommerce.
 *
 * Esta versión aplica un diseño moderno y responsive con mejoras de usabilidad
 * sobre la plantilla por defecto. Debe copiarse en
 * yourtheme/woocommerce/myaccount/my-account.php para sobrescribir el layout.
 */

defined('ABSPATH') || exit;

// Estilos básicos específicos para la plantilla. Solo se imprimen una vez.
if (!did_action('manaslu_myaccount_styles_printed')) {
    do_action('manaslu_myaccount_styles_printed');
    ?>
    <style>
        :root {
            --mva-surface: #ffffff;
            --mva-surface-alt: #f5f7fb;
            --mva-border: rgba(15, 23, 42, 0.08);
            --mva-primary: #1f3c88;
            --mva-text: #111827;
            --mva-text-muted: #4b5563;
            --mva-radius-lg: 18px;
            --mva-radius-sm: 10px;
            --mva-shadow: 0 20px 45px -25px rgba(15, 23, 42, 0.35);
        }

        .mva-account {
            background: linear-gradient(135deg, #eef2ff, #fff);
            padding: clamp(2rem, 4vw, 4rem) clamp(1.5rem, 4vw, 4rem);
            color: var(--mva-text);
        }

        .mva-account__inner {
            display: grid;
            gap: clamp(1.5rem, 2vw, 3rem);
            max-width: 1100px;
            margin: 0 auto;
        }

        @media (min-width: 960px) {
            .mva-account__inner {
                grid-template-columns: minmax(240px, 280px) 1fr;
                align-items: start;
            }
        }

        .mva-card {
            background: var(--mva-surface);
            border-radius: var(--mva-radius-lg);
            box-shadow: var(--mva-shadow);
            border: 1px solid rgba(255, 255, 255, 0.25);
            overflow: hidden;
        }

        .mva-account__sidebar {
            position: sticky;
            top: 1.5rem;
        }

        @media (max-width: 959px) {
            .mva-account__sidebar {
                position: static;
            }
        }

        .mva-account__content {
            min-height: 360px;
        }

        .mva-account__content .woocommerce {
            margin: 0;
        }

        .mva-account__content .woocommerce-MyAccount-content {
            padding: clamp(1.5rem, 3vw, 2.5rem);
        }

        .mva-account__content .woocommerce-MyAccount-content > :first-child {
            margin-top: 0;
        }

        .mva-account__content .woocommerce-MyAccount-content > :last-child {
            margin-bottom: 0;
        }
    </style>
    <?php
}
?>
<section class="mva-account" aria-labelledby="mva-account-title">
    <div class="mva-account__inner">
        <aside class="mva-account__sidebar mva-card" data-mva-menu>
            <h2 id="mva-account-title" class="screen-reader-text"><?php esc_html_e('Área personal', 'manaslu'); ?></h2>
            <?php do_action('woocommerce_account_navigation'); ?>
        </aside>
        <div class="mva-account__content mva-card" data-mva-content>
            <?php do_action('woocommerce_account_content'); ?>
        </div>
    </div>
</section>
