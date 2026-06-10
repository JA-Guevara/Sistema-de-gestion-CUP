(function () {
    function normalize(value) {
        return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function rowMatches(row, controls) {
        return controls.every(function (control) {
            var value = normalize(control.value);
            if (value === '') {
                return true;
            }

            var field = control.getAttribute('data-filter-field');
            var rowValue = normalize(row.getAttribute('data-filter-' + field));

            return rowValue.indexOf(value) !== -1;
        });
    }

    function applyFilters(form) {
        var target = form.getAttribute('data-filter-target');
        var table = document.querySelector('[data-gestion-filter-table="' + target + '"]');
        if (!table) {
            return;
        }

        var controls = Array.prototype.slice.call(form.querySelectorAll('[data-filter-field]'));
        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-filter-row]'));
        var emptyRow = table.querySelector('[data-filter-empty]');
        var visibleRows = 0;

        rows.forEach(function (row) {
            var isVisible = rowMatches(row, controls);
            row.hidden = !isVisible;
            visibleRows += isVisible ? 1 : 0;
        });

        if (emptyRow) {
            emptyRow.hidden = visibleRows !== 0;
        }
    }

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-gestion-filters]').forEach(function (form) {
            form.addEventListener('input', function () {
                applyFilters(form);
            });

            form.addEventListener('change', function () {
                applyFilters(form);
            });

            form.addEventListener('keyup', function () {
                applyFilters(form);
            });

            form.addEventListener('search', function () {
                applyFilters(form);
            });

            form.querySelectorAll('[data-filter-clear]').forEach(function (button) {
                button.addEventListener('click', function () {
                    form.reset();
                    applyFilters(form);
                });
            });

            applyFilters(form);
        });
    });
}());
