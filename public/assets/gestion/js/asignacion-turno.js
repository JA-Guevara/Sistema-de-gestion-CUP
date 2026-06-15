(function () {
    // Filtro de turno robusto, en tiempo real, sin recargar y SIN perder grupos.
    // Un grupo sin turno (data-turno vacio) cuenta como "sin-turno" y solo se
    // oculta cuando se elige explicitamente esa opcion; con "Todos" siempre se ve.

    function turnoDe(el) {
        var t = el.getAttribute('data-turno');
        return (t === null || t === '') ? 'sin-turno' : t;
    }

    function filtrarTarjetas(container, value) {
        if (!container) { return; }
        Array.prototype.forEach.call(container.querySelectorAll('[data-turno-item]'), function (item) {
            item.hidden = (value !== '' && turnoDe(item) !== value);
        });
    }

    function filtrarSelect(select, value) {
        if (!select) { return; }
        Array.prototype.forEach.call(select.options, function (o) {
            if (!o.value) { return; }
            var match = (value === '' || turnoDe(o) === value);
            o.hidden = !match;
            o.disabled = !match;
        });
        var cur = select.options[select.selectedIndex];
        if (cur && cur.value && cur.hidden) { select.value = ''; }
    }

    function wire(filter) {
        var key = filter.getAttribute('data-turno-filter');
        var containers = document.querySelectorAll('[data-turno-target="' + key + '"]');
        var selects = document.querySelectorAll('[data-grupo-select="' + key + '"]');

        function run() {
            Array.prototype.forEach.call(containers, function (c) { filtrarTarjetas(c, filter.value); });
            Array.prototype.forEach.call(selects, function (s) { filtrarSelect(s, filter.value); });
        }

        filter.addEventListener('change', run);
        run();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-turno-filter]'), wire);
    });
}());
