document.addEventListener('DOMContentLoaded', function() {
    const actionCard = document.getElementById('actionTakenCardContainer');
    const mainContentArea = document.querySelector('.main-content-area');

    // Check if we are on a mobile screen and the elements exist
    if (window.innerWidth <= 767.98 && actionCard && mainContentArea) {
        // Move the action card to be the first child of the main content area
        mainContentArea.prepend(actionCard);
    }
});
