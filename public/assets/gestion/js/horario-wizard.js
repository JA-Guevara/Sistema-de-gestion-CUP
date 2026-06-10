(function () {
    function parseTime(value) {
        if (!value) {
            return null;
        }

        var parts = value.split(':');
        if (parts.length < 2) {
            return null;
        }

        return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
    }

    function formatMinutes(total) {
        var hours = Math.floor(total / 60) % 24;
        var minutes = total % 60;
        return (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes;
    }

    function recompute(form) {
        var startEl = form.querySelector('[name="horaInicio"]');
        var finEl = form.querySelector('[name="horaFin"]');
        var durEl = form.querySelector('[name="duracionMinutos"]');
        var descEl = form.querySelector('[name="descansoMinutos"]');
        var preview = form.querySelector('[data-horario-preview]');

        var start = parseTime(startEl ? startEl.value : null);
        var duration = durEl ? parseInt(durEl.value, 10) : 0;
        var rest = descEl ? (parseInt(descEl.value, 10) || 0) : 0;
        var rows = Array.prototype.slice.call(form.querySelectorAll('[data-horario-row]'));

        if (start === null || !duration || duration <= 0) {
            rows.forEach(function (row) {
                var slot = row.querySelector('[data-horario-slot]');
                if (slot) {
                    slot.textContent = '-';
                }
            });
            if (preview) {
                preview.textContent = 'Completa el inicio del turno y la duracion de clase.';
            }
            return;
        }

        var cursor = start;
        var count = 0;
        var end = start;

        rows.forEach(function (row) {
            var checkbox = row.querySelector('[name="incluir[]"]');
            var slot = row.querySelector('[data-horario-slot]');
            if (checkbox && checkbox.checked) {
                var slotStart = cursor;
                var slotEnd = cursor + duration;
                if (slot) {
                    slot.textContent = formatMinutes(slotStart) + ' - ' + formatMinutes(slotEnd);
                }
                cursor = slotEnd + rest;
                end = slotEnd;
                count += 1;
            } else if (slot) {
                slot.textContent = '-';
            }
        });

        if (!preview) {
            return;
        }

        if (count === 0) {
            preview.textContent = 'Selecciona al menos una materia en el paso 2.';
            return;
        }

        var message = count + ' materia(s) - el horario termina a las ' + formatMinutes(end) + '.';
        var fin = parseTime(finEl ? finEl.value : null);
        if (fin !== null && end > fin) {
            message += ' Atencion: excede el fin del turno (' + finEl.value + ').';
        }

        preview.textContent = message;
    }

    window.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-horario-generator]');
        if (!form) {
            return;
        }

        form.addEventListener('input', function () {
            recompute(form);
        });
        form.addEventListener('change', function () {
            recompute(form);
        });

        recompute(form);
    });
}());
