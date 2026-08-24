const menuIconButton = document.getElementById('menu-toggle-btn');
const offcanvasMenu = document.getElementById('offcanvasMenu');

if (menuIconButton && offcanvasMenu) {
    const menuIcon = menuIconButton.querySelector('.menu-icon');
    if (menuIcon) {
        // UI: offcanvas menu toggle | Purpose: open/close menu + rotate hamburger icon
        menuIconButton.addEventListener('click', (event) => {
            event.stopPropagation();
            offcanvasMenu.classList.toggle('show-menu');
            menuIcon.classList.toggle('rotated');
        });

        // UI: close menu on outside click | Purpose: dismiss menu when clicking elsewhere
        document.addEventListener('click', (event) => {
            if (!offcanvasMenu.contains(event.target) && !menuIconButton.contains(event.target)) {
                offcanvasMenu.classList.remove('show-menu');
                menuIcon.classList.remove('rotated');
            }
        });
    }
}