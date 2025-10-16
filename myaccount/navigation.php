<?php
/**
 * Navegación lateral personalizada para el área "Mi cuenta".
 *
 * Copiar en yourtheme/woocommerce/myaccount/navigation.php para sobrescribir el
 * menú predeterminado de WooCommerce. Se omite el endpoint de descargas porque
 * no se utiliza en este proyecto.
 */

defined('ABSPATH') || exit;

$menu_items = wc_get_account_menu_items();
unset($menu_items['downloads']);
?>
<nav class="mva-nav" aria-label="<?php esc_attr_e('Navegación del área personal', 'manaslu'); ?>">
    <button class="mva-nav__toggle" type="button" data-mva-toggle>
        <span class="mva-nav__toggle-label"><?php esc_html_e('Menú de mi cuenta', 'manaslu'); ?></span>
        <svg class="mva-nav__toggle-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 15.75a1 1 0 0 1-.707-.293l-5-5a1 1 0 1 1 1.414-1.414L12 13.336l4.293-4.293a1 1 0 0 1 1.414 1.414l-5 5A1 1 0 0 1 12 15.75z" fill="currentColor" />
        </svg>
    </button>
    <ul class="mva-nav__list" data-mva-menu-list>
        <?php foreach ($menu_items as $endpoint => $label) : ?>
            <?php
            $classes = implode(' ', array_map('sanitize_html_class', explode(' ', wc_get_account_menu_item_classes($endpoint))));
            $url     = wc_get_account_endpoint_url($endpoint);
            $is_active = strpos($classes, 'is-active') !== false;
            ?>
            <li class="mva-nav__item <?php echo esc_attr($classes); ?>">
                <a
                    class="mva-nav__link"
                    href="<?php echo esc_url($url); ?>"
                    <?php echo $is_active ? 'aria-current="page"' : ''; ?>
                >
                    <span class="mva-nav__label"><?php echo esc_html($label); ?></span>
                    <svg class="mva-nav__chevron" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8.47 5.47a.75.75 0 0 1 1.06 0L16 11.94l-6.47 6.47a.75.75 0 0 1-1.06-1.06L13.94 12 8.47 6.53a.75.75 0 0 1 0-1.06z" fill="currentColor" />
                    </svg>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
<script>
    (function () {
        var scriptEl = document.currentScript || (function () {
            var scripts = document.getElementsByTagName('script');
            return scripts[scripts.length - 1] || null;
        })();
        if (!scriptEl) {
            return;
        }

        var nav = scriptEl.previousElementSibling;
        if (!nav) {
            return;
        }

        var toggle = nav.querySelector('[data-mva-toggle]');
        var list = nav.querySelector('[data-mva-menu-list]');
        if (!toggle || !list) {
            return;
        }

        var mq = window.matchMedia('(max-width: 767px)');

        function setState(open) {
            list.setAttribute('data-open', open ? 'true' : 'false');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () {
            var isOpen = list.getAttribute('data-open') === 'true';
            setState(!isOpen);
        });

        function handleMatch(event) {
            if (!event.matches) {
                setState(true);
            } else {
                setState(false);
            }
        }

        setState(!mq.matches);
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', handleMatch);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(handleMatch);
        }
    })();
</script>
<style>
    .mva-nav {
        padding: clamp(1.5rem, 3vw, 2rem);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .mva-nav__toggle {
        display: none;
        align-items: center;
        justify-content: space-between;
        background: var(--mva-surface-alt);
        border: 1px solid var(--mva-border);
        color: var(--mva-text);
        font-weight: 600;
        padding: 0.75rem 1rem;
        border-radius: var(--mva-radius-sm);
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .mva-nav__toggle:focus-visible {
        outline: 3px solid rgba(31, 60, 136, 0.45);
        outline-offset: 2px;
    }

    .mva-nav__toggle-icon {
        transform: rotate(-90deg);
    }

    .mva-nav__list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.75rem;
    }

    .mva-nav__item {
        border-radius: var(--mva-radius-sm);
        border: 1px solid transparent;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .mva-nav__link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 1rem;
        color: var(--mva-text-muted);
        text-decoration: none;
        font-weight: 500;
        background: transparent;
    }

    .mva-nav__chevron {
        opacity: 0;
        transform: translateX(-4px);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .mva-nav__item:hover, .mva-nav__item:focus-within {
        border-color: rgba(31, 60, 136, 0.18);
        box-shadow: 0 12px 25px -20px rgba(31, 60, 136, 0.6);
        transform: translateY(-1px);
    }

    .mva-nav__item:hover .mva-nav__link,
    .mva-nav__item:focus-within .mva-nav__link {
        color: var(--mva-primary);
    }

    .mva-nav__item:hover .mva-nav__chevron,
    .mva-nav__item:focus-within .mva-nav__chevron {
        opacity: 1;
        transform: translateX(0);
    }

    .mva-nav__item.is-active {
        border-color: rgba(31, 60, 136, 0.4);
        background: linear-gradient(135deg, rgba(31, 60, 136, 0.1), rgba(31, 60, 136, 0.05));
    }

    .mva-nav__item.is-active .mva-nav__link {
        color: var(--mva-primary);
        font-weight: 600;
    }

    @media (max-width: 767px) {
        .mva-nav {
            padding: 1rem;
        }

        .mva-nav__toggle {
            display: flex;
        }

        .mva-nav__list {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            border-radius: var(--mva-radius-sm);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .mva-nav__list[data-open="true"] {
            max-height: 600px;
        }

        .mva-nav__item {
            border-radius: 0;
            border-left: none;
            border-right: none;
        }

        .mva-nav__item:first-child {
            border-top-left-radius: var(--mva-radius-sm);
            border-top-right-radius: var(--mva-radius-sm);
        }

        .mva-nav__item:last-child {
            border-bottom-left-radius: var(--mva-radius-sm);
            border-bottom-right-radius: var(--mva-radius-sm);
        }
    }
</style>
