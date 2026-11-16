'use strict';
// Los controles nativos siguen funcionando cuando JavaScript no está disponible.
document.querySelectorAll('input[type="password"]').forEach(input => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.textContent = 'Mostrar contraseña';
    button.setAttribute('aria-controls', input.id);
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));
        button.textContent = reveal ? 'Ocultar contraseña' : 'Mostrar contraseña';
    });
    input.after(button);
});
document.querySelectorAll('.table-wrap').forEach(region => {
    if (region.hasAttribute('tabindex')) return;
    region.tabIndex = 0;
    region.setAttribute('role', 'region');
    region.setAttribute('aria-label', region.querySelector('caption')?.textContent || 'Tabla de datos, desplazable horizontalmente');
});
document.querySelectorAll('[data-error-target]').forEach(link => {
    const field = document.getElementById(link.dataset.errorTarget);
    if (!field) {
        // Los errores de operación no siempre corresponden a un campo editable.
        link.replaceWith(document.createTextNode(link.textContent));
        return;
    }
    if (field.matches('input,select,textarea')) {
        field.setAttribute('aria-invalid', 'true');
        const described = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
        described.add(link.id);
        field.setAttribute('aria-describedby', [...described].join(' '));
    }
    link.addEventListener('click', () => {
        if (!field.matches('input,select,textarea,button,a')) field.tabIndex = -1;
        field.focus();
    });
});
document.querySelector('[data-error-summary]')?.focus();
