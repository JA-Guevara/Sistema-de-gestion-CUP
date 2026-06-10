(function () {
    function closeToast(toast) {
        toast.classList.add('gestion-toast--leaving');
        window.setTimeout(function () {
            toast.remove();
        }, 220);
    }

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-gestion-toast]').forEach(function (toast) {
            var closeButton = toast.querySelector('[data-gestion-toast-close]');
            var timeout = window.setTimeout(function () {
                closeToast(toast);
            }, 4600);

            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    window.clearTimeout(timeout);
                    closeToast(toast);
                });
            }
        });
    });
}());
