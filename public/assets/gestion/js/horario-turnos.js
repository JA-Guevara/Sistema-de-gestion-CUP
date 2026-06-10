(function () {
    var turnos = {
        MANANA: ['08:00', '12:00'],
        TARDE: ['14:00', '18:00'],
        NOCHE: ['18:30', '21:30']
    };

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-horario-turno]').forEach(function (select) {
            var form = select.closest('form');
            if (!form) {
                return;
            }

            var horaInicio = form.querySelector('[name="horaInicio"]');
            var horaFin = form.querySelector('[name="horaFin"]');

            function applyTurno() {
                var value = select.value;
                if (!turnos[value] || !horaInicio || !horaFin) {
                    return;
                }

                horaInicio.value = turnos[value][0];
                horaFin.value = turnos[value][1];
            }

            select.addEventListener('change', applyTurno);
            applyTurno();
        });
    });
}());
