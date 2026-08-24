/**
 * Assistant Panel Filter Logic
 * Handles filtering of identity and report sections in the WhatsApp-style shell.
 */
(function() {
    'use strict';

    function initAssistantFilter() {
        const filterPills = document.querySelectorAll('.filter-pill');
        const sections = document.querySelectorAll('[data-section]');

        if (!filterPills.length || !sections.length) return;

        // Ensure initial state has transitions
        sections.forEach(section => {
            // UX: set CSS transitions on filter sections | Purpose: smooth animation on filter change
            section.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
        });

        const runFilter = (filter, animate = true) => {
            let firstFound = false;
            sections.forEach(section => {
                const sectionType = section.getAttribute('data-section');

                // ANIMATION: clear previous animation classes | Purpose: reset state before re-filter
                section.classList.remove('animate-swipe-up', 'animate-swipe-left', 'animate-swipe-right', 'section-animate', 'first-visible');

                if (filter === 'all' || filter === sectionType) {
                    // UI: section visibility toggle | Purpose: show matching / hide non-matching sections
                    section.style.display = 'block';
                    
                    if (!firstFound) {
                        section.classList.add('first-visible');
                        firstFound = true;
                    }

                    if (animate) {
                        // ANIMATION: add directional swipe classes | Purpose: entrance animation based on filter type
                        section.classList.add('section-animate');
                        if (filter === 'all') {
                            section.classList.add('animate-swipe-up');
                        } else if (filter === 'identity') {
                            section.classList.add('animate-swipe-right');
                        } else if (filter === 'reports') {
                            section.classList.add('animate-swipe-left');
                        }
                    }
                } else {
                    section.style.display = 'none';
                }
            });
        };

        filterPills.forEach(pill => {
            // UI: filter pill click handler | Purpose: toggle active pill + run filter
            pill.addEventListener('click', () => {
                const filter = pill.getAttribute('data-filter');
                const currentActive = document.querySelector('.filter-pill.active');

                if (currentActive === pill) return; // Already active

                // 1. Update pill UI
                filterPills.forEach(p => {
                    p.classList.remove('bg-success', 'active', 'text-white');
                    p.classList.add('bg-light', 'text-dark', 'border');
                });
                pill.classList.remove('bg-light', 'text-dark', 'border');
                pill.classList.add('bg-success', 'active', 'text-white');

                // 2. Run filter with animation
                runFilter(filter, true);
            });
        });

        // Apply initial state (No animation on first load)
        const activePill = document.querySelector('.filter-pill.active');
        if (activePill) {
            runFilter(activePill.getAttribute('data-filter'), false);
        }

        // Trigger 'Reports' filter if requested by external action (e.g. clicking a report button)
        window.activateAssistantFilter = (filterName) => {
            const targetPill = document.querySelector(`.filter-pill[data-filter="${filterName}"]`);
            if (targetPill) targetPill.click();
        };

        // Hook into existing report buttons to switch context automatically
        const reportButtonIds = ['getHrmsAdReportButton', 'exportAdUsersButton', 'adHealthCheckButton', 'userReportButton', 'userSecurityEventsButton'];
        reportButtonIds.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.addEventListener('click', () => {
                });
            }
        });
    }

    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', initAssistantFilter);
    
    // Support for SPA content updates
    window.initAssistantFilter = initAssistantFilter;

})();
