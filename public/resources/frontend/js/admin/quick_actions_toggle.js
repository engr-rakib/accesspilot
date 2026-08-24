document.addEventListener('DOMContentLoaded', () => {
    const quickActionsCard = document.getElementById('quick-actions-card');
    const toggleButton = document.getElementById('toggleQuickActions');
    const toggleIcon = toggleButton ? toggleButton.querySelector('i') : null;
    const leftSidebar = document.getElementById('left-sidebar');
    const originalButtonParent = toggleButton ? toggleButton.parentNode : null;
    const body = document.body;

    if (!quickActionsCard || !toggleButton || !toggleIcon || !leftSidebar) {
        console.warn('Quick Actions toggle elements not found or button parent missing.');
        return;
    }

    // Create a global container for the button when sidebar is collapsed
    let globalToggleButtonContainer = document.getElementById('global-toggle-button-container');
    if (!globalToggleButtonContainer) {
        globalToggleButtonContainer = document.createElement('div');
        globalToggleButtonContainer.id = 'global-toggle-button-container';
        body.appendChild(globalToggleButtonContainer);
    }

    // Function to set the state of the quick actions card
    const setQuickActionsState = (isHidden) => {
        if (isHidden) {
            // UI: collapse sidebar | Purpose: hide sidebar + show floating toggle button + flip chevron
            quickActionsCard.classList.add('hidden');
            leftSidebar.classList.add('collapsed');
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
            localStorage.setItem('quickActionsHidden', 'true');

            // Move button to global container
            globalToggleButtonContainer.appendChild(toggleButton);
            toggleButton.classList.add('ribbon-button');

            // Dynamically set top position to align with the card title
            const cardTitleRect = quickActionsCard.querySelector('.card-title').getBoundingClientRect();
            toggleButton.style.top = `${cardTitleRect.top}px`;

        } else {
            // UI: expand sidebar | Purpose: restore sidebar + flip chevron back
            quickActionsCard.classList.remove('hidden');
            leftSidebar.classList.remove('collapsed');
            toggleIcon.classList.remove('fa-chevron-right');
            toggleIcon.classList.add('fa-chevron-left');
            localStorage.removeItem('quickActionsHidden');

            // Move button back to original parent
            originalButtonParent.appendChild(toggleButton);
            toggleButton.classList.remove('ribbon-button');
            toggleButton.style.top = ''; // Clear dynamic top style
        }
    };

    // Initialize state from local storage
    const isHidden = localStorage.getItem('quickActionsHidden') === 'true';
    setQuickActionsState(isHidden);

    // Toggle functionality for Quick Actions sidebar
    toggleButton.addEventListener('click', () => {
        const currentState = quickActionsCard.classList.contains('hidden');
        setQuickActionsState(!currentState);
    });

    // Recalculate position on window resize to maintain alignment
    // UX: reposition toggle on resize | Purpose: keep floating button aligned with card title
    window.addEventListener('resize', () => {
        if (quickActionsCard.classList.contains('hidden')) {
            const cardTitleRect = quickActionsCard.querySelector('.card-title').getBoundingClientRect();
            toggleButton.style.top = `${cardTitleRect.top}px`;
        }
    });

    // Offcanvas close button functionality
    const offcanvasCloseButton = document.querySelector('.custom-offcanvas-close-btn');
    const offcanvasElement = document.getElementById('offcanvasMenu');

    if (offcanvasCloseButton && offcanvasElement) {
        offcanvasCloseButton.addEventListener('click', () => {

            offcanvasElement.classList.remove('show');
            offcanvasElement.classList.remove('show-menu');

            document.body.classList.remove('offcanvas-open');

            document.body.classList.remove('overflow-hidden');

            const backdrops = document.querySelectorAll('.offcanvas-backdrop');
            backdrops.forEach(backdrop => {
                if (backdrop) {
                    backdrop.remove();
                }
            });

            const headerSubmenuIcon = document.querySelector('.menu-icon');
            if (headerSubmenuIcon) {
                headerSubmenuIcon.classList.remove('rotated');
            }
        });
    }
});
