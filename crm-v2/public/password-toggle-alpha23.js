(() => {
    'use strict';
    const input = document.getElementById('password');
    const button = document.querySelector('.password-toggle');
    if (!input || !button) return;
    const slash = button.querySelector('.eye-slash');
    const hide = () => {
        input.type = 'password';
        button.setAttribute('aria-label', 'Şifreyi göster');
        button.title = 'Şifreyi göster';
        slash.setAttribute('hidden', '');
    };
    hide();
    button.hidden = false;
    button.addEventListener('click', () => {
        if (input.type === 'text') return hide();
        input.type = 'text';
        button.setAttribute('aria-label', 'Şifreyi gizle');
        button.title = 'Şifreyi gizle';
        slash.removeAttribute('hidden');
    });
    input.form.addEventListener('submit', hide);
    window.addEventListener('pagehide', hide);
    window.addEventListener('pageshow', hide);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) hide();
    });
})();
