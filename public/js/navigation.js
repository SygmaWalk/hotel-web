'use strict';

// Mejora de navegación local: no envía peticiones HTTP ni datos al servidor.
const menuButton = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navegacion');

if (menuButton && navigation) {
    document.documentElement.classList.add('js');
    menuButton.hidden = false;

    function closeMenu() {
        navigation.dataset.open = 'false';
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.textContent = 'Menú';
    }

    menuButton.addEventListener('click', () => {
        const open = menuButton.getAttribute('aria-expanded') !== 'true';
        navigation.dataset.open = String(open);
        menuButton.setAttribute('aria-expanded', String(open));
        menuButton.textContent = open ? 'Cerrar' : 'Menú';
    });

    navigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
            closeMenu();
            menuButton.focus();
        }
    });
}
