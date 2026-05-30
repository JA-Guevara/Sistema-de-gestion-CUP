(function () {
    const form = document.querySelector('[data-bitacora-filters]');

    if (!form) {
        return;
    }

    let timeoutId = null;

    form.classList.add('bitacora-filters--enhanced');

    const closeCustomSelects = function (current) {
        form.querySelectorAll('.bitacora-select--open').forEach(function (select) {
            if (select !== current) {
                select.classList.remove('bitacora-select--open');
                const button = select.querySelector('.bitacora-select__button');
                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
            }
        });
    };

    const submitFilters = function (delay) {
        window.clearTimeout(timeoutId);

        timeoutId = window.setTimeout(function () {
            const url = new URL(form.action, window.location.origin);
            const formData = new FormData(form);

            for (const [key, value] of formData.entries()) {
                const normalized = String(value).trim();

                if (normalized !== '') {
                    url.searchParams.set(key, normalized);
                }
            }

            url.searchParams.delete('page');
            window.location.href = url.toString();
        }, delay);
    };

    form.querySelectorAll('select.bitacora-filters__input').forEach(function (select) {
        const customSelect = document.createElement('div');
        const button = document.createElement('button');
        const list = document.createElement('div');

        customSelect.className = 'bitacora-select';
        button.type = 'button';
        button.className = 'bitacora-select__button';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        list.className = 'bitacora-select__list';
        list.setAttribute('role', 'listbox');

        const selectedOption = select.options[select.selectedIndex] || select.options[0];
        button.textContent = selectedOption ? selectedOption.textContent : '';

        Array.from(select.options).forEach(function (option) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'bitacora-select__option';
            item.textContent = option.textContent;
            item.dataset.value = option.value;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');

            if (option.selected) {
                item.classList.add('bitacora-select__option--selected');
            }

            item.addEventListener('click', function () {
                select.value = option.value;
                button.textContent = option.textContent;
                list.querySelectorAll('.bitacora-select__option').forEach(function (otherItem) {
                    otherItem.classList.remove('bitacora-select__option--selected');
                    otherItem.setAttribute('aria-selected', 'false');
                });
                item.classList.add('bitacora-select__option--selected');
                item.setAttribute('aria-selected', 'true');
                customSelect.classList.remove('bitacora-select--open');
                button.setAttribute('aria-expanded', 'false');
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            list.appendChild(item);
        });

        button.addEventListener('click', function () {
            const isOpen = customSelect.classList.contains('bitacora-select--open');

            closeCustomSelects(customSelect);
            customSelect.classList.toggle('bitacora-select--open', !isOpen);
            button.setAttribute('aria-expanded', String(!isOpen));
        });

        customSelect.appendChild(button);
        customSelect.appendChild(list);
        select.classList.add('bitacora-filters__input--native-hidden');
        select.insertAdjacentElement('afterend', customSelect);
    });

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            closeCustomSelects(null);
            form.querySelectorAll('.bitacora-select__button[aria-expanded="true"]').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
            });
        }
    });

    form.querySelectorAll('select, input').forEach(function (control) {
        const delay = control.matches('input[type="text"], input[type="search"]') ? 350 : 0;

        control.addEventListener('change', function () {
            submitFilters(delay);
        });

        if (delay > 0) {
            control.addEventListener('input', function () {
                submitFilters(delay);
            });
        }
    });
}());
