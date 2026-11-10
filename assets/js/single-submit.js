(function () {
    'use strict';
    const forms = document.querySelectorAll('[data-single-submit]');
    forms.forEach(form => {
        let submitting = false;
        let button = null;
        let originalText = '';
        form.addEventListener('submit', event => {
            if (submitting) { event.preventDefault(); return; }
            if (event.defaultPrevented) return;
            submitting = true;
            button = event.submitter || form.querySelector('button[type="submit"]');
            if (button) {
                originalText = button.textContent;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = 'Отправляем…';
            }
        });
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            submitting = false;
            if (button) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.textContent = originalText;
            }
        });
    });
})();
