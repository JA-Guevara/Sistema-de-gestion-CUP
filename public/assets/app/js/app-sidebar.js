/* =========================================================================
   Navegación lateral:
   - Sectores colapsables (acordeón) por área, con estado recordado.
   - El sector que contiene la página actual se abre automáticamente.
   - En móvil, el sidebar es un panel deslizable (drawer) con backdrop.
   ========================================================================= */
(function () {
    function setOpen(group, header, open) {
        group.classList.toggle('app-sidebar__group--collapsed', !open);
        if (header) {
            header.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function initGroups() {
        var groups = Array.prototype.slice.call(document.querySelectorAll('[data-nav-group]'));
        groups.forEach(function (group) {
            var header = group.querySelector('[data-nav-toggle]');
            if (!header) {
                return;
            }

            var key = 'cup-nav:' + (group.getAttribute('data-nav-key') || header.textContent.trim());
            var hasActive = group.querySelector('.app-sidebar__item--active') !== null;
            var stored = null;
            try {
                stored = window.localStorage.getItem(key);
            } catch (e) {
                stored = null;
            }

            // Abierto si: contiene la página activa, o el usuario lo dejó abierto.
            // Por defecto (sin preferencia y sin activo) se muestra colapsado.
            var open = hasActive || stored === '1';
            setOpen(group, header, open);

            header.addEventListener('click', function () {
                var willOpen = group.classList.contains('app-sidebar__group--collapsed');
                setOpen(group, header, willOpen);
                try {
                    window.localStorage.setItem(key, willOpen ? '1' : '0');
                } catch (e) {
                    /* sin persistencia si el storage no está disponible */
                }
            });
        });
    }

    function initDrawer() {
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var sidebar = document.querySelector('[data-sidebar]');
        var backdrop = document.querySelector('[data-sidebar-backdrop]');
        if (!toggle || !sidebar) {
            return;
        }

        function openDrawer(open) {
            document.body.classList.toggle('app--drawer-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (backdrop) {
                backdrop.hidden = !open;
            }
        }

        toggle.addEventListener('click', function () {
            openDrawer(!document.body.classList.contains('app--drawer-open'));
        });

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                openDrawer(false);
            });
        }

        // Al elegir una opción en móvil, cerrar el drawer.
        sidebar.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('.app-sidebar__item')) {
                openDrawer(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                openDrawer(false);
            }
        });
    }

    function init() {
        initGroups();
        initDrawer();
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
}());
