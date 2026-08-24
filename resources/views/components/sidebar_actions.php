<?php
/**
 * resources/views/components/sidebar_actions.php
 * 
 * Cleaned up Quick Actions component for the WhatsApp shell.
 */
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}
?>

<?php if (has_permission('card_assistant') || has_permission('card_quick_actions')): ?>
    <div id="quick-actions-panel" class="pt-0 pb-3">
        <form id="actionForm">
            <div data-section="identity">
                <div class="action-section-title mb-2 small fw-bold text-muted uppercase" style="letter-spacing: 1px;">Core Operations</div>
                <div class="button-group mb-2">
                    <?php if (has_permission('action_get_info')): ?>
                        <button type="button" name="action" value="info" class="btn action-button btn-info-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Get account status and HRMS profile.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-search me-1"></i> Info
                        </button>
                    <?php endif; ?>
                    <?php if (has_permission('action_unlock')): ?>
                        <button type="button" name="action" value="unlockUser" class="btn action-button btn-unlock-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Unlock AD account.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-lock-open me-1"></i> Unlock
                        </button>
                    <?php endif; ?>
                </div>

                <div class="button-group mb-2">
                    <?php if (has_permission('action_new_user_form')): ?>
                        <button type="button" name="action" value="createUser" class="btn action-button btn-create-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Provision new AD account.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-user-plus me-1"></i> New User
                        </button>
                    <?php endif; ?>
                    <?php if (has_permission('action_u_and_reset')): ?>
                        <button type="button" name="action" value="resetUnlock" class="btn action-button btn-reset-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Unlock and Reset password.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-sync-alt me-1"></i> U & Reset
                        </button>
                    <?php endif; ?>
                </div>

                <div class="button-group mb-3">
                    <?php if (has_permission('action_disable')): ?>
                        <button type="button" name="action" value="disableUser" class="btn action-button btn-disable-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Disable AD account.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-user-slash me-1"></i> Disable
                        </button>
                    <?php endif; ?>
                    <?php if (has_permission('action_enable')): ?>
                        <button type="button" name="action" value="enableUser" class="btn action-button btn-enable-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Enable AD account.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-user-check me-1"></i> Enable
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div data-section="identity">
                <div class="action-section-title mb-2 small fw-bold text-muted uppercase" style="letter-spacing: 1px;">Advanced Provisioning</div>
                <div class="button-group mb-3">
                    <?php if (has_permission('action_manual_create_form')): ?>
                        <button type="button" name="action" value="ADmanualUserCreate" id="ADmanualUserCreateButton" class="btn action-button btn-manual-create-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Custom AD user creation form.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-user-plus me-1"></i> Manual
                        </button>
                    <?php endif; ?>
                    <?php if (has_permission('action_modify_user_form')): ?>
                        <button type="button" name="action" value="modifyuser" id="ADmodifyUserButton" class="btn action-button btn-modify-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Edit AD user attributes.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-user-edit me-1"></i> Modify
                        </button>
                    <?php endif; ?>
                </div>
                <div class="button-group mb-3">
                    <?php if (has_permission('action_directory_builder') || has_permission('action_manual_create_form')): ?>
                        <button type="button" id="ADdirectoryBuilderButton" class="btn action-button btn-directory-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Manage OUs, Security Groups, and Group Memberships.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-sitemap me-1"></i> Directory
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (has_permission('card_get_report')) : ?>
                <div data-section="reports">
                    <div class="action-section-title mb-2 small fw-bold text-muted uppercase" style="letter-spacing: 1px;">Intelligence Hub</div>
                    <div class="button-group mb-2">
                        <?php if (has_permission('action_get_ad_hrms_status') || has_permission('action_export_hrms_ad_user_id')): ?>
                        <button type="button" id="getHrmsAdReportButton" class="btn action-button btn-sync-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'HRMS & AD combined report.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-database me-1"></i> HRMS AD
                        </button>
                        <?php endif; ?>
                        <?php if (has_permission('action_export_ad_users')): ?>
                        <button type="button" id="exportAdUsersButton" class="btn action-button btn-users-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Export users by OU or Group.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-download me-1"></i> Users
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="button-group mb-3">
                        <?php if (has_permission('action_user_report')): ?>
                        <button type="button" id="userReportButton" class="btn action-button btn-reports-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Advanced user status reports.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-file-invoice me-1"></i> Reports
                        </button>
                        <?php endif; ?>
                        <?php if (has_permission('action_security_events')): ?>
                        <button type="button" id="userSecurityEventsButton" class="btn action-button btn-security-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'View user security event logs from Domain Controller.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-shield-alt me-1"></i> Events
                        </button>
                        <?php endif; ?>
                        <?php if (has_permission('action_ad_health_check')): ?>
                        <button type="button" id="adHealthCheckButton" class="btn action-button btn-health-action flex-fill" data-noc-tip="<?= $isLicenseRestricted ? 'License required' : 'Domain health check.' ?>" <?= $isLicenseRestricted ? 'disabled' : '' ?>>
                            <i class="fas fa-heartbeat me-1"></i> Health
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <button type="button" class="btn btn-outline-success w-100 fw-bold py-2" onclick="window.open('<?= admin_page_url('dashboard') ?>', '_blank')" style="border-radius: 10px; border-width: 2px; font-size: 0.78rem;" data-noc-tip="Launch the full system analytics dashboard in a new tab.">
                    <i class="fas fa-external-link-alt me-2"></i> OPEN DASHBOARD
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>
