/**
 * UI Alignment Utility
 * Synchronizes the height of the main content logs table with the left sidebar bottom.
 * Ensures the table bottom aligns perfectly with the sidebar bottom.
 */
(function() {
    'use strict';

    const DEFAULT_VISIBLE_ROWS = 20;

    function getVisibleRowsHeight(wrapper, forcedCount) {
        const table = wrapper ? wrapper.querySelector('.log-table') : null;
        const tbody = table ? table.querySelector('tbody') : null;
        const rows = tbody ? tbody.querySelectorAll('tr') : [];
        const actualRowCount = rows.length;

        const headerRow = table ? table.querySelector('thead tr') : null;
        const bodyRow = table ? table.querySelector('tbody tr') : null;

        const headerHeight = headerRow ? headerRow.getBoundingClientRect().height : 38;
        const rowHeight = bodyRow ? bodyRow.getBoundingClientRect().height : 32;

        // Use actual row count (max 20). If forcedCount is provided, use that.
        const displayRows = forcedCount !== undefined ? forcedCount : (actualRowCount > 0 ? Math.min(actualRowCount, DEFAULT_VISIBLE_ROWS) : 2);

        // Add a bit more buffer (12px instead of 8) to prevent sub-pixel scrollbars
        return Math.ceil(headerHeight + (rowHeight * displayRows) + 12);
    }

    function syncLogTableHeight() {
        const sidebarCard = document.getElementById('quick-actions-card');
        const mainContent = document.getElementById('main-content');
        const targetCard = document.getElementById('recent-activity-card') || document.getElementById('dashboard-log-panel');
        if (!sidebarCard || !mainContent || !targetCard) return;

        // Skip on mobile
        if (window.innerWidth < 768) {
            const allWrappers = mainContent.querySelectorAll('.log-table-wrapper');
            allWrappers.forEach(w => {
                w.style.maxHeight = '';
                w.style.minHeight = '';
                w.style.height = '';
            });
            sidebarCard.style.minHeight = '';
            targetCard.style.minHeight = '';
            return;
        }

        const logWrappers = mainContent.querySelectorAll('.log-table-wrapper');
        if (logWrappers.length === 0) return;

        let targetWrapper = null;
        for (let i = logWrappers.length - 1; i >= 0; i--) {
            if (logWrappers[i].offsetParent !== null) {
                targetWrapper = logWrappers[i];
                break;
            }
        }

        if (!targetWrapper) return;

        // Reset all forced heights to allow dynamic expansion
        targetWrapper.style.maxHeight = '';
        targetWrapper.style.minHeight = '';
        targetWrapper.style.height = '';
        sidebarCard.style.minHeight = '';
        targetCard.style.minHeight = '';

        // 1. Calculate height for max 20 rows
        const maxHeight20 = getVisibleRowsHeight(targetWrapper, 20);
        
        // 2. Apply max-height only. Let 'height: auto' handle the dynamic expansion.
        targetWrapper.style.maxHeight = maxHeight20 + 'px';
        targetWrapper.style.height = 'auto'; 
        targetWrapper.style.overflowY = 'auto';
        targetWrapper.style.overflowX = 'hidden';

        // 3. Bottom alignment logic removed per user feedback
        // Cards will now naturally end where their content ends, removing the white space.
    }

    window.addEventListener('load', () => setTimeout(syncLogTableHeight, 500));
    window.addEventListener('resize', syncLogTableHeight);
    window.addEventListener('spaContentUpdated', () => setTimeout(syncLogTableHeight, 200));
    window.syncLogTableHeight = syncLogTableHeight;
})();
