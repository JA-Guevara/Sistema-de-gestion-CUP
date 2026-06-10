(function () {
    function setStep(wizard, index) {
        var panels = Array.prototype.slice.call(wizard.querySelectorAll('[data-wizard-panel]'));
        var steps = Array.prototype.slice.call(wizard.querySelectorAll('[data-wizard-step]'));
        var previous = wizard.querySelector('[data-wizard-prev]');
        var next = wizard.querySelector('[data-wizard-next]');
        var submit = wizard.querySelector('[data-wizard-submit]');

        panels.forEach(function (panel, panelIndex) {
            panel.hidden = panelIndex !== index;
        });

        steps.forEach(function (step, stepIndex) {
            step.classList.toggle('gestion-wizard__step--active', stepIndex === index);
            step.classList.toggle('gestion-wizard__step--done', stepIndex < index);
        });

        previous.hidden = index === 0;
        next.hidden = index === panels.length - 1;
        submit.hidden = false;
        submit.disabled = index !== panels.length - 1;
        submit.classList.toggle('gestion-button--disabled', index !== panels.length - 1);
        wizard.setAttribute('data-current-step', String(index));
    }

    function currentFieldsAreValid(wizard) {
        var index = Number(wizard.getAttribute('data-current-step') || '0');
        var panel = wizard.querySelectorAll('[data-wizard-panel]')[index];
        var fields = Array.prototype.slice.call(panel.querySelectorAll('input, select, textarea'));

        return fields.every(function (field) {
            return field.reportValidity();
        });
    }

    window.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-gestion-wizard]').forEach(function (wizard) {
            var previous = wizard.querySelector('[data-wizard-prev]');
            var next = wizard.querySelector('[data-wizard-next]');
            var steps = Array.prototype.slice.call(wizard.querySelectorAll('[data-wizard-panel]'));

            setStep(wizard, 0);

            previous.addEventListener('click', function () {
                setStep(wizard, Math.max(0, Number(wizard.getAttribute('data-current-step')) - 1));
            });

            next.addEventListener('click', function () {
                if (!currentFieldsAreValid(wizard)) {
                    return;
                }

                setStep(wizard, Math.min(steps.length - 1, Number(wizard.getAttribute('data-current-step')) + 1));
            });
        });
    });
}());
