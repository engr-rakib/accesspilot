(function () {
    const appConfig = window.APP_CONFIG || {};
    const resolvedBaseUrl = appConfig.baseUrl || (typeof baseURL === 'string' ? baseURL : window.location.origin);

    let notificationState = {
        notifications: [],
        unreadCount: 0,
        categories: {},
        preferences: null,
        capabilities: {},
        users: [],
        roles: [],
    };

    let pollingTimer = null;
    const shownToastIds = new Set();
    let currentBoundRoot = null;
    let selectedRoleValues = [];
    let selectedUserValues = [];
    const expandedNotificationGroups = new Set();

    function getEls() {
        return {
            bellButton: document.getElementById('notificationBellButton'),
            badge: document.getElementById('notificationBadge'),
            center: document.getElementById('notificationCenter'),
            backdrop: document.getElementById('notificationCenterBackdrop'),
            closeCenter: document.getElementById('closeNotificationCenter'),
            list: document.getElementById('notificationList'),
            toastStack: document.getElementById('notificationToastStack'),
            markAllRead: document.getElementById('markAllNotificationsRead'),
            clearAll: document.getElementById('clearAllNotifications'),
            togglePreferences: document.getElementById('toggleNotificationPreferences'),
            preferencesPanel: document.getElementById('notificationPreferencesPanel'),
            categoryPreferences: document.getElementById('notificationCategoryPreferences'),
            showToasts: document.getElementById('notificationShowToasts'),
            savePreferences: document.getElementById('saveNotificationPreferences'),
            preferencesStatus: document.getElementById('notificationPreferencesStatus'),
            toggleComposer: document.getElementById('toggleNotificationComposer'),
            composerPanel: document.getElementById('notificationComposerPanel'),
            composeStatus: document.getElementById('notificationComposeStatus'),
            title: document.getElementById('notificationTitle'),
            message: document.getElementById('notificationMessage'),
            severity: document.getElementById('notificationSeverity'),
            category: document.getElementById('notificationCategory'),
            audienceType: document.getElementById('notificationAudienceType'),
            audienceField: document.getElementById('notificationAudienceField'),
            targetUrlField: document.getElementById('notificationTargetUrlField'),
            persistentField: document.getElementById('notificationPersistentField'),
            rolesField: document.getElementById('notificationRolesField'),
            usersField: document.getElementById('notificationUsersField'),
            roles: document.getElementById('notificationRoles'),
            users: document.getElementById('notificationUsers'),
            targetUrl: document.getElementById('notificationTargetUrl'),
            persistent: document.getElementById('notificationPersistent'),
            sendButton: document.getElementById('sendNotificationButton'),
        };
    }

    function apiUrl(action) {
        return `${resolvedBaseUrl}/notification.php?action=${encodeURIComponent(action)}`;
    }

    function navigateToNotificationTarget(targetUrl) {
        if (!targetUrl) return;

        let normalizedTarget = String(targetUrl);
        const adminPageUrl = appConfig.adminPageUrl || `${resolvedBaseUrl}/index.php`;
        const dashboardPageUrl = appConfig.dashboardPageUrl || `${adminPageUrl}?page=dashboard`;
        const userActivityPageUrl = appConfig.userActivityPageUrl || `${adminPageUrl}?page=user_activity`;
        const userManagementPageUrl = appConfig.userManagementPageUrl || `${adminPageUrl}?page=user_management`;

        normalizedTarget = normalizedTarget
            .replace('/coreAdmin/indexpro.php?page=user_management', userManagementPageUrl)
            .replace('/coreAdmin/indexpro.php?page=user_activity', userActivityPageUrl)
            .replace('/coreAdmin/indexpro.php', adminPageUrl)
            .replace('/assets/dashboard/index.php', dashboardPageUrl);

        if (normalizedTarget.startsWith('http://') || normalizedTarget.startsWith('https://')) {
            const currentOrigin = window.location.origin;
            if (!normalizedTarget.startsWith(currentOrigin)) {
                window.location.href = normalizedTarget;
                return;
            }
            normalizedTarget = normalizedTarget.substring(currentOrigin.length);
        }

        if (normalizedTarget.includes('page=dashboard')) {
            window.location.href = normalizedTarget;
            return;
        }

        if (typeof loadSPAPage === 'function') {
            loadSPAPage(normalizedTarget);
            return;
        }

        window.location.href = normalizedTarget;
    }

    async function apiCall(action, options = {}) {
        const response = await fetch(apiUrl(action), {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
        });

        const text = await response.text();
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error(`Notification API returned invalid JSON: ${text.slice(0, 300)}`);
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.message || `Notification API error (${response.status})`);
        }

        return data;
    }

    function setBadge(count) {
        const { badge } = getEls();
        if (!badge) return;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
        const headerCount = document.getElementById('notificationHeaderCount');
        if (headerCount) {
            headerCount.textContent = count > 99 ? '99+' : String(count);
            headerCount.style.display = count > 0 ? 'inline-flex' : 'none';
        }
    }

    function severityLabel(severity) {
        const value = String(severity || 'info').toLowerCase();
        if (['success', 'warning', 'danger', 'info'].includes(value)) {
            return value;
        }
        return 'info';
    }

    function formatCategoryLabel(value) {
        const key = String(value || '').trim();
        return key ? key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()) : 'Notification';
    }

    function formatDateTime(value) {
        if (!value) return '';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleString();
    }

    function setPreferencesStatus(message, type = 'success') {
        const { preferencesStatus } = getEls();
        setInlineStatus(preferencesStatus, message, type);
    }

    function setComposeStatus(message, type = 'success') {
        const { composeStatus } = getEls();
        setInlineStatus(composeStatus, message, type);
    }

    function setInlineStatus(element, message, type = 'success') {
        if (!element) return;
        if (!message) {
            element.textContent = '';
            element.className = 'notification-inline-status';
            element.style.display = 'none';
            return;
        }

        element.textContent = message;
        element.className = `notification-inline-status is-${type}`;
        element.style.display = 'block';

        if (type === 'success') {
            window.setTimeout(() => {
                if (element && element.textContent === message) {
                    element.textContent = '';
                    element.className = 'notification-inline-status';
                    element.style.display = 'none';
                }
            }, 2400);
        }
    }

    function renderNotifications() {
        const { list } = getEls();
        if (!list) return;

        if (!notificationState.notifications.length) {
            list.innerHTML = '<div class="notification-empty-state">No notifications right now.</div>';
            return;
        }

        const grouped = [];
        const groupMap = new Map();
        notificationState.notifications.forEach((item) => {
            const key = String(item.category || 'notification');
            if (!groupMap.has(key)) {
                const group = { key, items: [] };
                groupMap.set(key, group);
                grouped.push(group);
            }
            groupMap.get(key).items.push(item);
        });

        list.innerHTML = grouped.map((group) => {
            const isExpanded = expandedNotificationGroups.has(group.key);
            const leadItem = group.items[0];
            const extraItems = isExpanded ? group.items.slice(1) : [];
            const moreCount = Math.max(group.items.length - 1, 0);

            return `
                <div class="notification-group" data-group-key="${escapeHTML(group.key)}">
                    <div class="notification-group-header">
                        <div class="notification-group-title">${escapeHTML(formatCategoryLabel(group.key))}</div>
                        ${moreCount > 0 ? `<button type="button" class="notification-group-toggle">${isExpanded ? 'Fewer' : `+${moreCount} more`}</button>` : ''}
                    </div>
                    ${renderNotificationCard(leadItem, true)}
                    ${extraItems.map((item) => renderNotificationCard(item, false)).join('')}
                </div>
            `;
        }).join('');
    }

    function renderNotificationCard(item, isLeadItem) {
        return `
            <div class="notification-item ${item.is_read ? '' : 'is-unread'} ${isLeadItem ? 'is-group-lead' : 'is-group-child'}" data-notification-id="${escapeHTML(String(item.id))}">
                <div class="notification-item-top">
                    <div>
                        <div class="notification-title">${escapeHTML(String(item.title || 'Notification'))}</div>
                        <div class="notification-meta">${escapeHTML(formatDateTime(item.created_at))}</div>
                    </div>
                    <span class="notification-pill ${escapeHTML(severityLabel(item.severity))}">${escapeHTML(formatCategoryLabel(item.category || 'info'))}</span>
                </div>
                <div class="notification-message">${escapeHTML(String(item.message || ''))}</div>
                ${(String(item.id).startsWith('manual_') && Array.isArray(item.read_by) && item.read_by.length)
                    ? `
                        <div class="notification-card-footer">
                            <div class="notification-read-stack">
                                ${item.read_by.map((reader, index) => `<div class="notification-read-user">${index + 1}. ${escapeHTML(reader)}</div>`).join('')}
                            </div>
                            <div class="notification-actions notification-actions-vertical">
                                <button type="button" class="btn btn-sm btn-outline-light notification-open-btn">Open</button>
                                ${item.is_read ? '' : '<button type="button" class="btn btn-sm btn-light notification-read-btn">Mark Read</button>'}
                                ${(notificationState.capabilities.can_manage && String(item.id).startsWith('manual_')) ? '<button type="button" class="btn btn-sm btn-outline-danger notification-delete-btn">Delete</button>' : ''}
                            </div>
                        </div>
                    `
                    : `
                        <div class="notification-actions">
                            <button type="button" class="btn btn-sm btn-outline-light notification-open-btn">Open</button>
                            ${item.is_read ? '' : '<button type="button" class="btn btn-sm btn-light notification-read-btn">Mark Read</button>'}
                            ${(notificationState.capabilities.can_manage && String(item.id).startsWith('manual_')) ? '<button type="button" class="btn btn-sm btn-outline-danger notification-delete-btn">Delete</button>' : ''}
                        </div>
                    `}
            </div>
        `;
    }

    function renderPreferences() {
        const { categoryPreferences, showToasts } = getEls();
        if (!notificationState.preferences || !categoryPreferences) return;

        showToasts.checked = !!notificationState.preferences.show_toasts;
        categoryPreferences.innerHTML = Object.entries(notificationState.categories).map(([key, label]) => `
            <label class="form-check">
                <input class="form-check-input notification-category-checkbox" type="checkbox" value="${escapeHTML(key)}" ${notificationState.preferences.categories?.[key] !== false ? 'checked' : ''}>
                <span class="form-check-label">${escapeHTML(label)}</span>
            </label>
        `).join('');

        renderRoleCheckboxes();
        updateAudienceFields();
    }

    function renderRoleCheckboxes() {
        const { roles } = getEls();
        if (!roles) return;

        roles.innerHTML = notificationState.roles.map((role) => {
            const value = String(role);
            const checked = selectedRoleValues.includes(value) ? 'checked' : '';
            return `
                <label class="notification-check-item">
                    <input type="checkbox" class="notification-role-checkbox" value="${escapeHTML(value)}" ${checked}>
                    <span>${escapeHTML(formatCategoryLabel(value))}</span>
                </label>
            `;
        }).join('');
    }

    function renderUsersOptions(usersToRender, selectedValues = []) {
        const { users } = getEls();
        if (!users) return;

        const selectedSet = new Set(selectedValues.map(String));
        users.innerHTML = usersToRender.map(user => {
            const username = String(user.username || '');
            const label = String(user.full_name || user.username || '');
            const role = String(user.role || '');
            const checked = selectedSet.has(username) ? 'checked' : '';
            return `
                <label class="notification-check-item">
                    <input type="checkbox" class="notification-user-checkbox" value="${escapeHTML(username)}" ${checked}>
                    <span>${escapeHTML(label)}${role ? ` (${escapeHTML(formatCategoryLabel(role))})` : ''}</span>
                </label>
            `;
        }).join('');
    }

    function updateAudienceFields() {
        const els = getEls();
        const audienceType = els.audienceType?.value || 'all';
        const selectedRoles = [...selectedRoleValues];
        const selectedUsers = [...selectedUserValues];

        if (els.rolesField) {
            els.rolesField.style.display = audienceType === 'roles' ? '' : 'none';
            els.rolesField.className = 'col-md-6';
        }
        if (els.usersField) {
            els.usersField.style.display = audienceType === 'all' ? 'none' : '';
            els.usersField.className = audienceType === 'users' ? 'col-12' : 'col-md-6';
        }

        let usersToRender = notificationState.users;
        if (audienceType === 'roles' && selectedRoles.length) {
            usersToRender = notificationState.users.filter(user => selectedRoles.includes(String(user.role || '')));
            selectedUserValues = selectedUserValues.filter((username) =>
                usersToRender.some(user => String(user.username || '') === username)
            );
        }

        renderUsersOptions(audienceType === 'all' ? [] : usersToRender, selectedUsers);

        if (audienceType === 'all') {
            selectedRoleValues = [];
            selectedUserValues = [];
            renderRoleCheckboxes();
            renderUsersOptions([], []);
        } else if (audienceType === 'users') {
            selectedRoleValues = [];
            renderRoleCheckboxes();
        }
    }

    function closeCenter() {
        const { center, backdrop } = getEls();
        if (center) center.classList.remove('is-open');
        if (backdrop) backdrop.style.display = 'none';
    }

    function openCenter() {
        const { center, backdrop } = getEls();
        if (center) center.classList.add('is-open');
        if (backdrop) backdrop.style.display = 'none';
    }

    function syncToolbarStates() {
        const els = getEls();
        if (els.togglePreferences) {
            els.togglePreferences.classList.toggle('is-active', els.preferencesPanel?.style.display === 'block');
        }
        if (els.toggleComposer) {
            els.toggleComposer.classList.toggle('is-active', els.composerPanel?.style.display === 'block');
        }
    }

    async function refreshNotifications(showToasts = true) {
        const data = await apiCall('fetch');
        notificationState = {
            notifications: data.notifications || [],
            unreadCount: data.unread_count || 0,
            categories: data.categories || {},
            preferences: data.preferences || null,
            capabilities: data.capabilities || {},
            users: data.users || [],
            roles: data.roles || [],
        };

        setBadge(notificationState.unreadCount);
        renderNotifications();
        renderPreferences();

        if (showToasts && notificationState.preferences?.show_toasts !== false) {
            showIncomingToasts(data.toast_notifications || []);
        }
    }

    async function markRead(ids) {
        if (!ids.length) return;
        await apiCall('mark_read', { method: 'POST', body: { ids } });
        await refreshNotifications(false);
    }

    async function dismissToasts(ids) {
        if (!ids.length) return;
        await apiCall('dismiss_toasts', { method: 'POST', body: { ids } });
    }

    function removeToastElement(element) {
        if (!element) return;
        element.remove();
    }

    function showIncomingToasts(notifications) {
        const { toastStack } = getEls();
        if (!toastStack) return;

        const idsToDismiss = [];

        notifications.forEach((item) => {
            const notificationId = String(item.id);
            if (shownToastIds.has(notificationId)) {
                return;
            }

            shownToastIds.add(notificationId);
            idsToDismiss.push(notificationId);

            const toast = document.createElement('div');
            toast.className = 'notification-toast';
            toast.innerHTML = `
                <div class="notification-toast-body">
                    <div class="notification-item-top">
                        <div>
                            <div class="notification-title">${escapeHTML(String(item.title || 'Notification'))}</div>
                            <div class="notification-meta">${escapeHTML(formatDateTime(item.created_at))}</div>
                        </div>
                        <button type="button" class="notification-toast-close-btn" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="notification-message mb-0">${escapeHTML(String(item.message || ''))}</div>
                </div>
                <div class="notification-toast-progress"><span></span></div>
            `;

            const closeToast = () => removeToastElement(toast);

            toast.querySelector('.notification-toast-close')?.addEventListener('click', closeToast);
            toast.addEventListener('click', async () => {
                closeToast();
                openCenter();
                await markRead([notificationId]);
                if (item.target_url) {
                    navigateToNotificationTarget(item.target_url);
                }
            });

            toastStack.appendChild(toast);
            window.setTimeout(closeToast, 6000);
        });

        if (idsToDismiss.length) {
            dismissToasts(idsToDismiss).catch(console.error);
        }

        if (notifications.length > 0 && typeof window._soundAlertsEnabled === 'function' && window._soundAlertsEnabled() && typeof window.playNotificationSound === 'function') {
            window.playNotificationSound();
        }
    }

    async function handleListClick(event) {
        const groupToggle = event.target.closest('.notification-group-toggle');
        if (groupToggle) {
            const group = event.target.closest('.notification-group');
            const key = group?.dataset.groupKey;
            if (!key) return;

            if (expandedNotificationGroups.has(key)) {
                expandedNotificationGroups.delete(key);
            } else {
                expandedNotificationGroups.add(key);
            }
            renderNotifications();
            return;
        }

        const notificationItem = event.target.closest('.notification-item');
        if (!notificationItem) return;

        const notificationId = notificationItem.dataset.notificationId;
        const notification = notificationState.notifications.find(item => String(item.id) === notificationId);
        if (!notification) return;

        if (event.target.closest('.notification-read-btn')) {
            await markRead([notificationId]);
            return;
        }

        if (event.target.closest('.notification-delete-btn')) {
            await apiCall('delete', { method: 'POST', body: { id: notificationId } });
            await refreshNotifications(false);
            return;
        }

        if (event.target.closest('.notification-open-btn') || notification.target_url) {
            await markRead([notificationId]);
            if (notification.target_url) {
                closeCenter();
                navigateToNotificationTarget(notification.target_url);
            }
        }
    }

    async function savePreferences() {
        const { categoryPreferences, showToasts } = getEls();
        const categories = {};
        categoryPreferences.querySelectorAll('.notification-category-checkbox').forEach(input => {
            categories[input.value] = input.checked;
        });

        await apiCall('save_preferences', {
            method: 'POST',
            body: {
                show_toasts: showToasts.checked,
                categories,
            },
        });

        await refreshNotifications(false);
        setPreferencesStatus('Preferences saved.', 'success');
    }

    async function sendNotification() {
        const els = getEls();
        const payload = {
            title: els.title?.value?.trim() || '',
            message: els.message?.value?.trim() || '',
            severity: els.severity?.value || 'info',
            category: els.category?.value || 'announcement',
            audience_type: els.audienceType?.value || 'all',
            roles: [...selectedRoleValues],
            users: [...selectedUserValues],
            target_url: els.targetUrl?.value?.trim() || '',
            is_persistent: !!els.persistent?.checked,
        };

        const result = await apiCall('create', { method: 'POST', body: payload });

        if (els.title) els.title.value = '';
        if (els.message) els.message.value = '';
        if (els.targetUrl) els.targetUrl.value = '';
        if (els.persistent) els.persistent.checked = false;
        if (els.audienceType) els.audienceType.value = 'all';
        selectedRoleValues = [];
        selectedUserValues = [];
        updateAudienceFields();

        await refreshNotifications(false);
        setComposeStatus(result.message || 'Notification sent successfully.', 'success');
    }

    function bindEvents() {
        const els = getEls();
        if (!els.bellButton) return;
        if (currentBoundRoot === els.center) {
            return;
        }
        currentBoundRoot = els.center;

        els.bellButton.onclick = () => {
            const isOpen = els.center?.classList.contains('is-open');
            if (isOpen) {
                closeCenter();
            } else {
                openCenter();
            }
        };

        if (els.closeCenter) {
            els.closeCenter.onclick = closeCenter;
        }
        if (els.backdrop) {
            els.backdrop.onclick = closeCenter;
        }
        if (els.markAllRead) {
            els.markAllRead.onclick = async () => {
                await apiCall('mark_all_read', { method: 'POST', body: {} });
                await refreshNotifications(false);
            };
        }
        if (els.clearAll) {
            els.clearAll.onclick = async () => {
                try {
                    await apiCall('clear_all', { method: 'POST', body: {} });
                    expandedNotificationGroups.clear();
                    await refreshNotifications(false);
                } catch (error) {
                    console.error(error);
                }
            };
        }
        if (els.list) {
            els.list.onclick = (event) => { handleListClick(event).catch(console.error); };
        }
        if (els.togglePreferences) {
            els.togglePreferences.onclick = () => {
                if (!els.preferencesPanel) return;

                const isOpen = els.preferencesPanel.style.display === 'block';
                els.preferencesPanel.style.display = isOpen ? 'none' : 'block';

                if (els.composerPanel && !isOpen) {
                    els.composerPanel.style.display = 'none';
                }
                syncToolbarStates();
            };
        }
        if (els.savePreferences) {
            els.savePreferences.onclick = async () => {
                try {
                    setPreferencesStatus('Saving preferences...', 'info');
                    await savePreferences();
                } catch (error) {
                    console.error(error);
                    setPreferencesStatus(error?.message || 'Failed to save preferences.', 'error');
                }
            };
        }
        if (els.toggleComposer) {
            els.toggleComposer.onclick = () => {
                if (!els.composerPanel) return;

                const isOpen = els.composerPanel.style.display === 'block';
                els.composerPanel.style.display = isOpen ? 'none' : 'block';

                if (els.preferencesPanel && !isOpen) {
                    els.preferencesPanel.style.display = 'none';
                }
                if (!isOpen) {
                    updateAudienceFields();
                }
                syncToolbarStates();
            };
        }
        if (els.audienceType) {
            els.audienceType.onchange = () => {
                updateAudienceFields();
            };
        }
        if (els.roles) {
            els.roles.onchange = (event) => {
                if (!event.target.classList.contains('notification-role-checkbox')) return;
                const value = String(event.target.value || '');
                if (event.target.checked) {
                    if (!selectedRoleValues.includes(value)) {
                        selectedRoleValues.push(value);
                    }
                } else {
                    selectedRoleValues = selectedRoleValues.filter(item => item !== value);
                }
                updateAudienceFields();
            };
        }
        if (els.users) {
            els.users.onchange = (event) => {
                if (!event.target.classList.contains('notification-user-checkbox')) return;
                const value = String(event.target.value || '');
                if (event.target.checked) {
                    if (!selectedUserValues.includes(value)) {
                        selectedUserValues.push(value);
                    }
                } else {
                    selectedUserValues = selectedUserValues.filter(item => item !== value);
                }
            };
        }
        if (els.sendButton) {
            els.sendButton.onclick = async () => {
                try {
                    setComposeStatus('Sending notification...', 'info');
                    await sendNotification();
                } catch (error) {
                    console.error(error);
                    setComposeStatus(error?.message || 'Failed to send notification.', 'error');
                }
            };
        }
    }

    function startPolling() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
        }

        pollingTimer = setInterval(() => {
            refreshNotifications(true).catch(console.error);
        }, 3000);
    }

    window.initNotifications = function () {
        const { bellButton } = getEls();
        if (!bellButton) return;

        bindEvents();
        refreshNotifications(false).catch(console.error);
        startPolling();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof window.initNotifications === 'function') {
            window.initNotifications();
        }
    });
})();
