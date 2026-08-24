document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('.btn-menu');
    const menuDropdown = document.querySelector('.menu-dropdown');

    if (menuButton && menuDropdown) {
        // UI: header dropdown toggle | Purpose: open/close dropdown menu
        menuButton.addEventListener('click', () => {
            menuButton.classList.toggle('active');
            menuDropdown.classList.toggle('active');
        });

        // UI: close dropdown on outside click | Purpose: dismiss menu when clicking elsewhere
        document.addEventListener('click', (event) => {
            if (!menuButton.contains(event.target) && !menuDropdown.contains(event.target)) {
                menuButton.classList.remove('active');
                menuDropdown.classList.remove('active');
            }
        });

        // Close menu on Escape key press
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                menuDropdown.classList.remove('active');
                menuButton.classList.remove('active');
            }
        });
    }

    // Ensure correct navigation for dashboard and employee DB links
    const dashboardLink = document.getElementById('dashboardLink');
    const employeeDbLink = document.getElementById('employeeDbLink');

    if (dashboardLink) {
        dashboardLink.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = this.href;
        });
    }

    if (employeeDbLink) {
        employeeDbLink.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = this.href;
        });
    }

    // Fullscreen toggle button: enter/exit browser fullscreen for the workspace
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const fullscreenIcon = fullscreenBtn ? fullscreenBtn.querySelector('i') : null;
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        });

        document.addEventListener('fullscreenchange', function() {
            if (fullscreenIcon) {
                const isFullscreen = !!document.fullscreenElement;
                fullscreenIcon.classList.remove(isFullscreen ? 'fa-expand' : 'fa-compress');
                fullscreenIcon.classList.add(isFullscreen ? 'fa-compress' : 'fa-expand');
            }
        });
    }
});