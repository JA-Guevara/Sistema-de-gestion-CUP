/* =========================================================================
   Planilla de notas: calculo EN VIVO de promedio y estado mientras el docente
   registra manualmente, validacion 0-100 y resumen. Replica la regla del
   servidor (VerPlanilla): promedio = round(promedio de examenes cargados);
   aprobado solo cuando estan TODOS los examenes y promedio >= nota minima.
   ========================================================================= */
(function () {
    function init() {
        var table = document.querySelector('[data-nota-table]');
        if (!table) {
            return;
        }

        var min = parseInt(table.getAttribute('data-nota-min'), 10);
        var total = parseInt(table.getAttribute('data-nota-examenes'), 10);
        if (isNaN(min)) { min = 51; }
        if (isNaN(total) || total < 1) { total = 1; }

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-filter-row]'));
        var summary = document.querySelector('[data-nota-summary]');
        var dirtyHint = document.querySelector('[data-nota-dirty]');
        var dirty = false;

        function clamp(n) {
            if (n < 0) { return 0; }
            if (n > 100) { return 100; }
            return n;
        }

        function recalcRow(row) {
            var inputs = Array.prototype.slice.call(row.querySelectorAll('.gestion-nota-input'));
            var suma = 0;
            var cargadas = 0;
            inputs.forEach(function (inp) {
                var raw = (inp.value || '').trim();
                if (raw === '') {
                    return;
                }
                var n = Math.round(Number(raw));
                if (isNaN(n)) {
                    return;
                }
                n = clamp(n);
                suma += n;
                cargadas += 1;
            });

            var promedio = cargadas > 0 ? Math.round(suma / cargadas) : null;
            var estado;
            if (cargadas === total) {
                estado = promedio >= min ? 'aprobado' : 'reprobado';
            } else {
                estado = 'incompleto';
            }

            var promCell = row.querySelector('[data-nota-promedio]');
            if (promCell) {
                promCell.textContent = promedio === null ? '-' : String(promedio);
            }

            var estadoCell = row.querySelector('[data-nota-estado]');
            if (estadoCell) {
                var label = estado === 'aprobado' ? 'Aprobado' : (estado === 'reprobado' ? 'Reprobado' : 'Incompleto');
                var cls = estado === 'aprobado' ? 'activa' : (estado === 'reprobado' ? 'inactiva' : 'borrador');
                estadoCell.innerHTML = '<span class="gestion-badge gestion-badge--' + cls + '">' + label + '</span>';
            }

            // Mantiene el filtro por estado coherente tras editar.
            row.setAttribute('data-filter-estado', estado);

            return estado;
        }

        function recalcAll() {
            var counts = { aprobado: 0, reprobado: 0, incompleto: 0 };
            rows.forEach(function (row) {
                counts[recalcRow(row)] += 1;
            });
            if (summary) {
                setCount(summary, '[data-nota-aprobados]', counts.aprobado);
                setCount(summary, '[data-nota-reprobados]', counts.reprobado);
                setCount(summary, '[data-nota-incompletos]', counts.incompleto);
            }
        }

        function setCount(scope, selector, value) {
            var el = scope.querySelector(selector);
            if (el) {
                el.textContent = String(value);
            }
        }

        function markDirty() {
            if (dirty) {
                return;
            }
            dirty = true;
            if (dirtyHint) {
                dirtyHint.hidden = false;
            }
        }

        table.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('gestion-nota-input')) {
                recalcAll();
                markDirty();
            }
        });

        // Al perder foco, normaliza el valor al rango 0-100.
        table.addEventListener('change', function (e) {
            var inp = e.target;
            if (!inp.classList || !inp.classList.contains('gestion-nota-input')) {
                return;
            }
            var raw = (inp.value || '').trim();
            if (raw !== '') {
                var n = Math.round(Number(raw));
                inp.value = isNaN(n) ? '' : String(clamp(n));
            }
            recalcAll();
        });

        // Al guardar, deja de marcar como "sin guardar".
        var form = table.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                dirty = false;
            });
        }

        recalcAll();
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
}());
