(function () {
    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-panel-toggle]').forEach(function (button) {
            var panel = document.getElementById(button.getAttribute('data-panel-toggle'));
            var openLabel = button.getAttribute('data-open-label') || button.textContent;
            var closeLabel = button.getAttribute('data-close-label') || 'Ocultar formulario';

            if (!panel) {
                return;
            }

            button.addEventListener('click', function () {
                var willOpen = panel.hidden;
                panel.hidden = !willOpen;
                button.textContent = willOpen ? closeLabel : openLabel;

                if (willOpen) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    });
}());
