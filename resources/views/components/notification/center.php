<?php if (function_exists('has_permission') && has_permission('card_notification_center')): ?>
<div id="notificationToastStack" class="notification-toast-stack" aria-live="polite" aria-atomic="true"></div>

<div id="notificationCenter" class="notification-center-panel" aria-hidden="true">
    <div class="notification-center-card">
        <div class="notification-center-header">
            <div>
                <h3 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h3>
                <small class="text-muted">Live updates from requests, logs, and admin announcements</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="notification-header-count" id="notificationHeaderCount" style="display:none;">0</span>
                <button type="button" class="notification-close-btn" id="closeNotificationCenter" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
        </div>

        <div class="notification-center-toolbar">
            <button type="button" class="btn btn-sm notification-toolbar-btn" id="markAllNotificationsRead" aria-label="Mark all read" data-noc-tip="Mark all read">
                <i class="fas fa-check-double"></i>
            </button>
            <?php if (has_permission('action_notification_preferences')): ?>
            <button type="button" class="btn btn-sm notification-toolbar-btn" id="toggleNotificationPreferences" aria-label="Preferences" data-noc-tip="Preferences">
                <i class="fas fa-sliders-h"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('action_notification_send')): ?>
            <button type="button" class="btn btn-sm notification-toolbar-btn" id="toggleNotificationComposer" aria-label="Compose" data-noc-tip="Compose">
                <i class="fas fa-pen"></i>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm notification-toolbar-btn notification-icon-btn" id="clearAllNotifications" aria-label="Clear all visible notifications" data-noc-tip="Clear all">
                <i class="fas fa-broom"></i>
            </button>
        </div>

        <?php if (has_permission('action_notification_preferences')): ?>
        <div id="notificationPreferencesPanel" class="notification-inline-panel" style="display:none;">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="notificationShowToasts">
                <label class="form-check-label" for="notificationShowToasts">Show popup toasts for new notifications</label>
            </div>
            <div id="notificationCategoryPreferences" class="notification-category-grid"></div>
            <div class="mt-3">
                <button type="button" class="btn btn-sm btn-primary notification-accent-btn" id="saveNotificationPreferences">Save Preferences</button>
            </div>
            <div id="notificationPreferencesStatus" class="notification-inline-status" style="display:none;"></div>
        </div>
        <?php endif; ?>

        <?php if (has_permission('action_notification_send')): ?>
        <div id="notificationComposerPanel" class="notification-inline-panel notification-compose-panel" style="display:none;">
            <div class="notification-compose-intro">
                <div class="notification-compose-title">Create Broadcast</div>
                <div class="notification-compose-copy">Send a compact message to everyone, selected roles, or individual users.</div>
            </div>
            <div class="row g-1 notification-compose-grid">
                <div class="col-12">
                    <label class="form-label" for="notificationTitle">Title</label>
                    <input type="text" class="form-control" id="notificationTitle" maxlength="120" placeholder="Short title">
                </div>
                <div class="col-6">
                    <label class="form-label" for="notificationSeverity">Severity</label>
                    <select class="form-select" id="notificationSeverity">
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="danger">Danger</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="notificationCategory">Category</label>
                    <select class="form-select" id="notificationCategory">
                        <option value="announcement">Announcement</option>
                        <option value="requests">Requests</option>
                        <option value="security">Security</option>
                        <option value="activity">Activity</option>
                        <option value="ad_actions">AD Actions</option>
                        <option value="reports">Reports</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notificationMessage">Message</label>
                    <textarea class="form-control" id="notificationMessage" rows="3" maxlength="600" placeholder="Write the notification message"></textarea>
                </div>
                <div class="notification-row-3">
                    <div class="notification-col" id="notificationAudienceField">
                        <label class="form-label" for="notificationAudienceType">Audience</label>
                        <select class="form-select" id="notificationAudienceType">
                            <option value="all">All Users</option>
                            <option value="roles">Specific Roles</option>
                            <option value="users">Specific Users</option>
                        </select>
                    </div>
                    <div class="notification-col" id="notificationTargetUrlField">
                        <label class="form-label" for="notificationTargetUrl">Target URL</label>
                        <input type="text" class="form-control" id="notificationTargetUrl" placeholder="/index.php?page=user_management">
                    </div>
                    <div class="notification-col" id="notificationPersistentField">
                        <div class="form-check notification-persistent-check mb-0">
                            <input class="form-check-input" type="checkbox" id="notificationPersistent">
                            <label class="form-check-label" for="notificationPersistent">Persistent</label>
                        </div>
                    </div>
                </div>
                <div class="col-6" id="notificationRolesField">
                    <label class="form-label" for="notificationRoles">Roles</label>
                    <div class="notification-checkbox-list" id="notificationRoles"></div>
                    <div class="notification-field-hint">Click items to toggle multiple roles.</div>
                </div>
                <div class="col-6" id="notificationUsersField">
                    <label class="form-label" for="notificationUsers">Users</label>
                    <div class="notification-checkbox-list" id="notificationUsers"></div>
                    <div class="notification-field-hint">Click items to toggle multiple users.</div>
                </div>
                <div class="col-12 notification-compose-actions">
                    <button type="button" class="btn btn-primary notification-accent-btn notification-send-btn" id="sendNotificationButton">Send Notification</button>
                    <div id="notificationComposeStatus" class="notification-inline-status" style="display:none;"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div id="notificationList" class="notification-list">
            <div class="notification-empty-state">Loading notifications...</div>
        </div>
    </div>
</div>

<div id="notificationCenterBackdrop" class="notification-center-backdrop" style="display:none;"></div>
<?php endif; ?>
