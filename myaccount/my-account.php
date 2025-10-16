<?php
/**
 * Plantilla principal para el área "Mi cuenta" de WooCommerce.
 *
 * Esta versión aplica un diseño moderno y responsive con mejoras de usabilidad
 * sobre la plantilla por defecto. Debe copiarse en
 * yourtheme/woocommerce/myaccount/my-account.php para sobrescribir el layout.
 */

defined('ABSPATH') || exit;

if (!function_exists('manaslu_myaccount_reservas_title')) {
    function manaslu_myaccount_reservas_title($title = '')
    {
        return __('Reservas', 'manaslu');
    }
}

add_filter('woocommerce_endpoint_orders_title', 'manaslu_myaccount_reservas_title');
add_filter('woocommerce_my_account_my_orders_title', 'manaslu_myaccount_reservas_title');

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

        .mva-account__content .woocommerce-MyAccount-content h2,
        .mva-account__content .woocommerce-MyAccount-content h3 {
            font-weight: 700;
            color: var(--mva-text);
            margin-bottom: 1.25rem;
        }

        .mva-account__content .woocommerce-orders-table,
        .mva-account__content .woocommerce-table--order-downloads,
        .mva-account__content .woocommerce-MyAccount-paymentMethods table {
            width: 100%;
            border-collapse: collapse;
            background: var(--mva-surface-alt);
            border-radius: var(--mva-radius-sm);
            overflow: hidden;
            box-shadow: 0 12px 28px -22px rgba(15, 23, 42, 0.45);
        }

        .mva-account__content .woocommerce-orders-table thead th,
        .mva-account__content .woocommerce-MyAccount-paymentMethods thead th {
            background: rgba(31, 60, 136, 0.12);
            color: var(--mva-text);
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            padding: 1rem 1.25rem;
        }

        .mva-account__content .woocommerce-orders-table td,
        .mva-account__content .woocommerce-MyAccount-paymentMethods td {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            color: var(--mva-text-muted);
            vertical-align: middle;
        }

        .mva-account__content .woocommerce-orders-table tr:hover,
        .mva-account__content .woocommerce-MyAccount-paymentMethods tbody tr:hover {
            background: rgba(31, 60, 136, 0.08);
        }

        .mva-account__content .woocommerce-orders-table a.button,
        .mva-account__content .woocommerce-MyAccount-paymentMethods a.button {
            background: var(--mva-primary);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .mva-account__content .woocommerce-orders-table a.button:hover,
        .mva-account__content .woocommerce-MyAccount-paymentMethods a.button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px -20px rgba(31, 60, 136, 0.85);
        }

        .mva-account__content mark.order-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-size: 0.8rem;
            text-transform: capitalize;
            background: rgba(31, 60, 136, 0.12);
            color: var(--mva-primary);
        }

        .mva-account__content .woocommerce-Addresses {
            display: grid;
            gap: clamp(1.25rem, 2vw, 1.75rem);
        }

        @media (min-width: 768px) {
            .mva-account__content .woocommerce-Addresses {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .mva-account__content .woocommerce-Address {
            background: var(--mva-surface-alt);
            border-radius: var(--mva-radius-lg);
            border: 1px solid rgba(15, 23, 42, 0.08);
            padding: clamp(1.25rem, 2vw, 1.75rem);
            box-shadow: 0 12px 30px -26px rgba(15, 23, 42, 0.55);
            display: grid;
            gap: 0.75rem;
        }

        .mva-account__content .woocommerce-Address-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .mva-account__content .woocommerce-Address-title h3 {
            margin: 0;
            font-size: 1.05rem;
        }

        .mva-account__content .woocommerce-Address .edit {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--mva-primary);
            text-decoration: none;
        }

        .mva-account__content .woocommerce-PaymentMethods {
            display: grid;
            gap: 1.5rem;
        }

        .mva-account__content .woocommerce-PaymentMethods p {
            margin: 0;
            color: var(--mva-text-muted);
        }

        .mva-account__content .woocommerce-EditAccountForm {
            display: grid;
            gap: 1.25rem;
        }

        .mva-account__content .woocommerce-EditAccountForm fieldset {
            margin: 0;
            padding: 0;
            border: none;
        }

        .mva-account__content .woocommerce-EditAccountForm .form-row {
            display: grid;
            gap: 0.4rem;
        }

        @media (min-width: 640px) {
            .mva-account__content .woocommerce-EditAccountForm .form-row.form-row-first,
            .mva-account__content .woocommerce-EditAccountForm .form-row.form-row-last {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: center;
            }

            .mva-account__content .woocommerce-EditAccountForm .form-row.form-row-first label,
            .mva-account__content .woocommerce-EditAccountForm .form-row.form-row-last label {
                grid-column: 1 / span 2;
            }
        }

        .mva-account__content .woocommerce-EditAccountForm label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--mva-text);
        }

        .mva-account__content .woocommerce-EditAccountForm input,
        .mva-account__content .woocommerce-EditAccountForm textarea,
        .mva-account__content .woocommerce-EditAccountForm select {
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: var(--mva-radius-sm);
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .mva-account__content .woocommerce-EditAccountForm input:focus,
        .mva-account__content .woocommerce-EditAccountForm textarea:focus,
        .mva-account__content .woocommerce-EditAccountForm select:focus {
            outline: none;
            border-color: rgba(31, 60, 136, 0.45);
            box-shadow: 0 0 0 4px rgba(31, 60, 136, 0.15);
        }

        .mva-account__content .woocommerce-EditAccountForm .woocommerce-Button {
            justify-self: start;
            background: var(--mva-primary);
            color: #fff;
            padding: 0.75rem 1.75rem;
            border-radius: 999px;
            font-weight: 600;
            letter-spacing: 0.01em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .mva-account__content .woocommerce-EditAccountForm .woocommerce-Button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 30px -24px rgba(31, 60, 136, 0.85);
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
