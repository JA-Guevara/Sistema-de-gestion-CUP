(function () {
    function rowTemplate(index) {
        return '' +
            '<div class="gestion-period-row" data-period-row>' +
            '<label>Actividad <input name="periodos[' + index + '][actividad]" placeholder="Examen parcial 1" required></label>' +
            '<label>Inicio <input type="date" name="periodos[' + index + '][inicio]" required></label>' +
            '<label>Fin <input type="date" name="periodos[' + index + '][fin]" required></label>' +
            '<button class="gestion-icon-button" type="button" data-period-remove aria-label="Quitar actividad">' +
            '<svg viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
            '</button>' +
            '</div>';
    }

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-periods-builder]').forEach(function (builder) {
            var list = builder.querySelector('[data-periods-list]');
            var addButton = builder.querySelector('[data-period-add]');
            var nextIndex = list ? list.querySelectorAll('[data-period-row]').length : 0;

            if (!list || !addButton) {
                return;
            }

            addButton.addEventListener('click', function () {
                list.insertAdjacentHTML('beforeend', rowTemplate(nextIndex));
                nextIndex += 1;
            });

            builder.addEventListener('click', function (event) {
                var button = event.target.closest('[data-period-remove]');
                if (!button) {
                    return;
                }

                var rows = list.querySelectorAll('[data-period-row]');
                if (rows.length <= 1) {
                    return;
                }

                button.closest('[data-period-row]').remove();
            });
        });
    });
}());
