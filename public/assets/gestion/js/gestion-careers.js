(function () {
    function template(builder, index) {
        var tpl = builder.querySelector('[data-career-template]');
        return tpl.innerHTML.replaceAll('__index__', String(index));
    }

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-careers-builder]').forEach(function (builder) {
            var list = builder.querySelector('[data-careers-list]');
            var addButton = builder.querySelector('[data-career-add]');
            var nextIndex = list ? list.querySelectorAll('[data-career-row]').length : 0;

            if (!list || !addButton) {
                return;
            }

            addButton.addEventListener('click', function () {
                list.insertAdjacentHTML('beforeend', template(builder, nextIndex));
                nextIndex += 1;
            });

            builder.addEventListener('click', function (event) {
                var button = event.target.closest('[data-career-remove]');
                if (!button) {
                    return;
                }

                var rows = list.querySelectorAll('[data-career-row]');
                if (rows.length <= 1) {
                    return;
                }

                button.closest('[data-career-row]').remove();
            });
        });
    });
}());
