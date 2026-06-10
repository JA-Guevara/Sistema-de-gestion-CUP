(function () {
    function updateBulkState(root) {
        var items = Array.prototype.slice.call(root.querySelectorAll('[data-bulk-item]'));
        items.forEach(function (item) {
            if (item.closest('tr')?.hidden) {
                item.checked = false;
            }
        });

        var checked = items.filter(function (item) {
            return item.checked && !item.closest('tr')?.hidden;
        });
        var submits = Array.prototype.slice.call(root.querySelectorAll('[data-bulk-submit]'));
        var counter = root.querySelector('[data-bulk-count]');
        var checkAll = root.querySelector('[data-bulk-check-all]');
        var toolbar = root.querySelector('[data-bulk-actions]');

        submits.forEach(function (submit) {
            submit.disabled = checked.length === 0;
            submit.classList.toggle('gestion-button--disabled', checked.length === 0);
        });

        if (counter) {
            counter.textContent = String(checked.length);
        }

        if (toolbar) {
            toolbar.hidden = checked.length === 0;
            toolbar.classList.toggle('is-visible', checked.length > 0);
        }

        if (checkAll) {
            var visibleItems = items.filter(function (item) {
                return !item.closest('tr')?.hidden;
            });
            checkAll.checked = visibleItems.length > 0 && checked.length === visibleItems.length;
            checkAll.indeterminate = checked.length > 0 && checked.length < visibleItems.length;
        }
    }

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bulk-root]').forEach(function (root) {
            var checkAll = root.querySelector('[data-bulk-check-all]');

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    root.querySelectorAll('[data-bulk-item]').forEach(function (item) {
                        if (!item.closest('tr')?.hidden) {
                            item.checked = checkAll.checked;
                        }
                    });
                    updateBulkState(root);
                });
            }

            var clearBtn = root.querySelector('[data-bulk-clear]');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    root.querySelectorAll('[data-bulk-item]').forEach(function (item) {
                        item.checked = false;
                    });
                    updateBulkState(root);
                });
            }

            root.querySelectorAll('[data-bulk-item]').forEach(function (item) {
                item.addEventListener('change', function () {
                    updateBulkState(root);
                });
            });

            root.addEventListener('input', function () {
                window.setTimeout(function () {
                    updateBulkState(root);
                }, 0);
            });

            root.addEventListener('change', function () {
                window.setTimeout(function () {
                    updateBulkState(root);
                }, 0);
            });

            updateBulkState(root);
        });
    });
}());
