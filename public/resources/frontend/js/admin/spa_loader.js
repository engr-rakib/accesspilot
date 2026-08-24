// assets/js/coreAdmin/spa_loader.js

const appConfig = window.APP_CONFIG || {};
const appBaseUrl = appConfig.baseUrl || (typeof baseURL === 'string' ? baseURL : window.location.origin);

function toAbsoluteSpaUrl(url) {
    return new URL(url, appBaseUrl + '/').toString();
}

function normalizePathname(pathname) {
    return String(pathname || '').replace(/\/+$/, '') || '/';
}

function isSpaNavigableUrl(url) {
    if (!url || url.startsWith('#') || url.startsWith('mailto:') || url.startsWith('tel:') || url.startsWith('javascript:')) {
        return false;
    }

    let resolvedUrl;
    try {
        resolvedUrl = new URL(url, appBaseUrl + '/');
    } catch (error) {
        return false;
    }

    const currentOrigin = window.location.origin;
    if (resolvedUrl.origin !== currentOrigin) {
        return false;
    }

    const pathname = normalizePathname(resolvedUrl.pathname);
    const basePathname = normalizePathname(new URL(appConfig.adminPageUrl || `${appBaseUrl}/index.php`, appBaseUrl + '/').pathname);
    const legacyPathname = normalizePathname(new URL(`${appBaseUrl}/coreAdmin/indexpro.php`, appBaseUrl + '/').pathname);
    const defaultPathname = normalizePathname(new URL(appBaseUrl + '/', appBaseUrl + '/').pathname);

    if (pathname === defaultPathname || pathname === basePathname || pathname === legacyPathname) {
        return true;
    }

    return false;
}

function cleanupSpaPageState() {
    // Dispose tooltip/popover instances FIRST (clears their hide timers)
    document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-toggle="popover"], [title]').forEach((element) => {
        try {
            const tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) tooltip.dispose();
            const popover = bootstrap.Popover.getInstance(element);
            if (popover) popover.dispose();
        } catch (e) {
            // Ignore tooltip cleanup errors (race condition with pending hide timers)
        }
    });
    // Then remove leftover tooltip/popover DOM elements
    document.querySelectorAll('.tooltip, .popover').forEach((element) => {
        if (element && element.parentNode) element.parentNode.removeChild(element);
    });

    if (typeof window.destroyDashboardCharts === 'function') {
        window.destroyDashboardCharts();
    }
    if (typeof window.destroyLegacyDashboardCharts === 'function') {
        window.destroyLegacyDashboardCharts();
    }



    document.querySelectorAll('script[data-spa-page-script="true"]').forEach((script) => script.remove());
    document.querySelectorAll('link[data-spa-page-style="true"]').forEach((style) => style.remove());

    if (window._activeUserRefresher) clearInterval(window._activeUserRefresher);
    if (window._dashboardDataRefresher) clearInterval(window._dashboardDataRefresher);
    if (window._sessionTimerIncrementer) clearInterval(window._sessionTimerIncrementer);
    if (window._dashboardPollTimer) clearInterval(window._dashboardPollTimer);
    if (window._dashboardSessionTimer) clearInterval(window._dashboardSessionTimer);
    if (window._userMgmtInterval) clearInterval(window._userMgmtInterval);

    // Restore info card visibility after leaving dashboard
    var _si = document.getElementById('serverUserInfoDisplay');
    var _ei = document.getElementById('employeeInfoDisplay');
    if (_si) _si.style.display = '';
    if (_ei) _ei.style.display = '';

    window._dashboardInitialized = false;
    window._dashboardInitializedRoot = null;
    window.__quickActionChartLoaded = false;
}

document.addEventListener('DOMContentLoaded', () => {
    const initialUrl = window.location.href;
    history.replaceState({ url: initialUrl }, '', initialUrl);
    updateHeaderTitle(document.title);

    // Intercept all menu clicks
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        const url = link.getAttribute('href');
        const forceSpaLink = link.dataset.spaLink === 'true';
        if (!forceSpaLink && !isSpaNavigableUrl(url)) return;

        const absoluteUrl = url ? toAbsoluteSpaUrl(url) : null;

        // Check if it's the same page to avoid redundant loads
        if (absoluteUrl === window.location.href) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        loadSPAPage(absoluteUrl);
        
        // Close offcanvas menu if it's open (Bootstrap 5)
        const offcanvasEl = document.getElementById('offcanvasMenu');
        if (offcanvasEl && offcanvasEl.classList.contains('show')) {
            const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
            if (offcanvas) offcanvas.hide();
        }
    }, true);

    // Handle back/forward browser buttons
    window.onpopstate = (e) => {
        if (e.state && e.state.url) {
            loadSPAPage(e.state.url, false);
        } else {
            // If no state, we might be at the initial page
            window.location.reload();
        }
    };

    // Initial navigation highlight
    updateNavigationLinks(window.location.href);

    // Suppress Bootstrap tooltip transition errors (race: dispose + pending animation timeout)
    window.addEventListener('error', (e) => {
        if (e.filename && e.filename.includes('tooltip.js')) {
            e.preventDefault();
        }
    });
});

async function loadSPAPage(url, pushToHistory = true) {
    const targetUrl = toAbsoluteSpaUrl(url);
    
    const pageContent = document.getElementById('spa-page-content');
    const loader = document.querySelector('.loader-container');

    // 1. Start transition - Fade Out current page content only
    if (pageContent) {
        pageContent.classList.add('spa-page-exit');
    }
    
    // SPA: loading spinner show | Purpose: visual feedback during page load
    if (loader) loader.style.display = 'flex';

    try {
        const response = await fetch(targetUrl, {
            headers: { 'X-Requested-With': 'SPA-Request' }
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success) {
            cleanupSpaPageState();

            // 2. Load Styles dynamically
            if (data.styles && data.styles.length > 0) {
                data.styles.forEach(styleUrl => {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = styleUrl;
                    link.dataset.spaPageStyle = 'true';
                    document.head.appendChild(link);
                });
            }

            // --- Update Body Classes based on page ---
            if (url.includes('page=') && !url.includes('page=default')) {
                document.body.classList.remove('index-pro-page');
            } else {
                document.body.classList.add('index-pro-page');
            }

            // 3. Update Content & Title
            document.title = data.title;
            updateHeaderTitle(data.title);
            if (pageContent) {
                pageContent.innerHTML = data.content;

                // Execute inline <script> tags from loaded content
                pageContent.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    }
                    newScript.textContent = oldScript.textContent;
                    oldScript.replaceWith(newScript);
                });
            }

            if (pushToHistory) {
                history.pushState({ url: targetUrl }, '', targetUrl);
            } else {
                history.replaceState({ url: targetUrl }, '', targetUrl);
            }

            // 5. Update Navigation UI
            updateNavigationLinks(targetUrl);

            // 6. Load Scripts dynamically in order (sequential, not parallel)
            if (data.scripts && data.scripts.length > 0) {
                for (const scriptUrl of data.scripts) {
                    await new Promise((resolve) => {
                        const newScript = document.createElement('script');
                        newScript.src = scriptUrl;
                        newScript.dataset.spaPageScript = 'true';
                        newScript.onload = () => resolve();
                        newScript.onerror = () => resolve();
                        document.body.appendChild(newScript);
                    });
                }
            }

            // 7. Finalize transition - Fade In new page content
            if (pageContent) {
                pageContent.classList.remove('spa-page-exit');
                void pageContent.offsetWidth;
                pageContent.classList.add('spa-page-enter');
            }
            if (loader) loader.style.display = 'none';

            // Re-initialize specific page components
            reinitializeAllScripts();
            
            // Dispatch event for specialized modules (like monitoring)
            document.dispatchEvent(new CustomEvent('spaContentUpdated'));
            
            // Scroll to top
            // UX: smooth scroll to top | Purpose: reset scroll position on page nav
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            console.error('SPA: Server returned error, falling back to full load.');
            window.location.href = targetUrl;
        }

    } catch (error) {
        console.error('SPA Load Error:', error);
        // Fallback to traditional navigation if SPA fails
        window.location.href = targetUrl;
    } finally {
        if (loader && loader.style.display !== 'none') loader.style.display = 'none';
    }
}

window.loadSPAPage = loadSPAPage;

/**
 * Highlights the active link in the navigation menus
 */
function updateNavigationLinks(currentUrl) {
    // Standardize URL for comparison
    const urlObj = new URL(currentUrl, window.location.origin);
    const pageParam = urlObj.searchParams.get('page') || 'home';

    // Update Offcanvas Menu links
    const menuLinks = document.querySelectorAll('#offcanvasMenu .list-group-item a');
    menuLinks.forEach(link => {
        const linkUrl = new URL(link.getAttribute('href'), window.location.origin);
        const linkPage = linkUrl.searchParams.get('page') || 'default';
        
        if (linkPage === pageParam) {
            // UX: navigation active state | Purpose: highlight current page in nav
            link.parentElement.classList.add('active');
            link.classList.add('text-white');
        } else {
            link.parentElement.classList.remove('active');
            link.classList.remove('text-white');
        }
    });

    // Update Vertical Rail links (Main and Flyout)
    const railLinks = document.querySelectorAll('.main-rail .rail-item, .child-rail-flyout .rail-item');
    railLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        if (link.classList.contains('logout-rail-btn')) return;

        try {
            // Support both absolute and relative URLs for comparison
            const linkUrl = new URL(href, window.location.origin);
            const linkPage = linkUrl.searchParams.get('page') || 'home';
            
            if (linkPage === pageParam) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        } catch(e) {}
    });
}

function reinitializeAllScripts() {
    
    // 1. Re-initialize Dashboard logic
    if (typeof window.initializeDashboard === 'function') {
        window.initializeDashboard();
    }

    // 2. Re-attach action processors for buttons
    // Most buttons are globally handled in action_processor.js, 
    // but if new buttons were injected into the DOM, we ensure they are ready.
    if (window.initializeActionProcessors) {
        window.initializeActionProcessors();
    }

    if (typeof window.initializeRecentActivityLogs === 'function') {
        window.initializeRecentActivityLogs();
    }

    if (typeof window.fetchTodayLogChartData === 'function' && document.getElementById('todayLogChart')) {
        window.fetchTodayLogChartData(true);
    }

    // 3. Page-specific initializations
    if (typeof window.initDashboardLogic === 'function') window.initDashboardLogic();
    if (typeof window.initPasswordManager === 'function') window.initPasswordManager();
    if (typeof window.initProfileHub === 'function') window.initProfileHub();
    if (typeof window.initManualCreateUser === 'function') window.initManualCreateUser();
    if (typeof window.initSecurityEvents === 'function') window.initSecurityEvents();
    if (typeof window.initExportUserActions === 'function') window.initExportUserActions();
    if (typeof window.initUserManagement === 'function') window.initUserManagement();
    if (typeof window.initCreateUser === 'function') window.initCreateUser();
    if (typeof window.initEditUser === 'function') window.initEditUser();
    if (typeof window.initChangePassword === 'function') window.initChangePassword();
    if (typeof window.initEmployeeDb === 'function') window.initEmployeeDb();
    if (typeof window.initRoleManagement === 'function') window.initRoleManagement();
    if (typeof window.initRoleForm === 'function') window.initRoleForm();
    if (typeof window.initNotifications === 'function') window.initNotifications();
    
    // 5. Re-initialize tooltips and popovers (Bootstrap 5)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        // Dispose existing instance if it exists
        const existing = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (existing) existing.dispose();
        new bootstrap.Tooltip(tooltipTriggerEl, { container: 'body' });
    });
}

function updateHeaderTitle(title) {
    const headerTitle = document.getElementById('portal-page-title');
    if (!headerTitle) return;
    headerTitle.textContent = title || 'AccessPilot';
}
