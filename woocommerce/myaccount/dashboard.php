<?php
/**
 * Panel de bienvenida personalizado para la página "Mi cuenta".
 *
 * Copiar en yourtheme/woocommerce/myaccount/dashboard.php para mostrar el nuevo
 * contenido de bienvenida.
 */

defined('ABSPATH') || exit;

$current_user = wp_get_current_user();
$display_name = $current_user->exists() ? $current_user->display_name : __('viajero', 'manaslu');
$orders_url   = wc_get_account_endpoint_url('orders');
$details_url  = wc_get_account_endpoint_url('edit-account');
$address_url  = wc_get_account_endpoint_url('edit-address');

$last_order = is_user_logged_in() ? wc_get_customer_last_order(get_current_user_id(), 'completed') : false;
$upcoming_message = '';
if ($last_order) {
    $order_date = $last_order->get_date_created();
    if ($order_date instanceof WC_DateTime) {
        $upcoming_message = sprintf(
            /* translators: %s: formatted order date. */
            esc_html__('Tu última reserva confirmada es del %s. ¡Prepárate para la aventura!', 'manaslu'),
            esc_html(wc_format_datetime($order_date, wc_date_format()))
        );
    }
}

if (!$upcoming_message) {
    $upcoming_message = esc_html__('Aún no tienes reservas confirmadas. ¡Explora nuestros viajes y encuentra tu próxima experiencia!', 'manaslu');
}
?>
<div class="mva-dashboard" aria-live="polite">
    <header class="mva-dashboard__header">
        <p class="mva-dashboard__eyebrow"><?php esc_html_e('Mi cuenta', 'manaslu'); ?></p>
        <h1 class="mva-dashboard__title"><?php echo esc_html(sprintf(__('Hola, %s', 'manaslu'), $display_name)); ?></h1>
        <p class="mva-dashboard__lead"><?php echo esc_html($upcoming_message); ?></p>
    </header>

    <div class="mva-dashboard__grid">
        <section class="mva-dashboard__card">
            <h2 class="mva-dashboard__card-title"><?php esc_html_e('Resumen rápido', 'manaslu'); ?></h2>
            <ul class="mva-dashboard__list">
                <li>
                    <span class="mva-dashboard__label"><?php esc_html_e('Pedidos y reservas', 'manaslu'); ?></span>
                    <a class="mva-dashboard__link" href="<?php echo esc_url($orders_url); ?>">
                        <?php esc_html_e('Revisar historial', 'manaslu'); ?>
                    </a>
                </li>
                <li>
                    <span class="mva-dashboard__label"><?php esc_html_e('Detalles de la cuenta', 'manaslu'); ?></span>
                    <a class="mva-dashboard__link" href="<?php echo esc_url($details_url); ?>">
                        <?php esc_html_e('Actualizar perfil', 'manaslu'); ?>
                    </a>
                </li>
                <li>
                    <span class="mva-dashboard__label"><?php esc_html_e('Direcciones guardadas', 'manaslu'); ?></span>
                    <a class="mva-dashboard__link" href="<?php echo esc_url($address_url); ?>">
                        <?php esc_html_e('Gestionar direcciones', 'manaslu'); ?>
                    </a>
                </li>
            </ul>
        </section>

        <section class="mva-dashboard__card mva-dashboard__card--accent">
            <h2 class="mva-dashboard__card-title"><?php esc_html_e('¿Necesitas ayuda?', 'manaslu'); ?></h2>
            <p>
                <?php esc_html_e('Nuestro equipo está listo para acompañarte en cada paso del viaje. Si tienes dudas sobre tu reserva o necesitas hacer cambios, contáctanos.', 'manaslu'); ?>
            </p>
            <a class="mva-dashboard__cta" href="mailto:hola@manasluaventura.com">
                <?php esc_html_e('Escríbenos', 'manaslu'); ?>
            </a>
        </section>
    </div>
</div>
<style>
    .mva-dashboard {
        padding: clamp(1.5rem, 4vw, 3rem);
        display: grid;
        gap: clamp(1.5rem, 3vw, 2.5rem);
    }

    .mva-dashboard__header {
        display: grid;
        gap: 0.5rem;
    }

    .mva-dashboard__eyebrow {
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.75rem;
        font-weight: 700;
        color: rgba(17, 24, 39, 0.55);
        margin: 0;
    }

    .mva-dashboard__title {
        font-size: clamp(1.8rem, 4vw, 2.4rem);
        font-weight: 700;
        margin: 0;
        color: var(--mva-text);
    }

    .mva-dashboard__lead {
        font-size: clamp(1rem, 2.2vw, 1.125rem);
        color: var(--mva-text-muted);
        margin: 0;
        max-width: 46ch;
    }

    .mva-dashboard__grid {
        display: grid;
        gap: clamp(1.25rem, 2.5vw, 2rem);
    }

    @media (min-width: 860px) {
        .mva-dashboard__grid {
            grid-template-columns: minmax(240px, 1fr) minmax(260px, 1fr);
            align-items: stretch;
        }
    }

    .mva-dashboard__card {
        background: var(--mva-surface-alt);
        border-radius: var(--mva-radius-lg);
        padding: clamp(1.5rem, 3vw, 2.25rem);
        border: 1px solid var(--mva-border);
        display: grid;
        gap: 1rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45);
    }

    .mva-dashboard__card--accent {
        background: linear-gradient(135deg, rgba(227, 23, 45, 0.08), rgba(227, 23, 45, 0.25));
        color: var(--mva-text);
    }

    .mva-dashboard__card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .mva-dashboard__list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.75rem;
    }

    .mva-dashboard__label {
        font-weight: 600;
        color: var(--mva-text);
        display: block;
        margin-bottom: 0.25rem;
    }

    .mva-dashboard__link {
        color: var(--mva-primary);
        text-decoration: none;
        font-weight: 600;
    }

    .mva-dashboard__link:hover,
    .mva-dashboard__link:focus {
        text-decoration: underline;
    }

    .mva-dashboard__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border-radius: 999px;
        background: var(--mva-primary);
        color: #fff;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .mva-dashboard__cta:hover,
    .mva-dashboard__cta:focus {
        transform: translateY(-1px);
        box-shadow: 0 10px 25px -15px rgba(227, 23, 45, 0.5);
    }

    .mva-dashboard__cta:focus-visible {
        outline: 3px solid rgba(255, 255, 255, 0.7);
        outline-offset: 3px;
    }
</style>
