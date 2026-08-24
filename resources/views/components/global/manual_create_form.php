<?php if (has_permission('card_manual_create_form')):
    $_canExchange = false;
    if (function_exists('ldap_exchange_active_domain_config')) {
        $_exCfg = ldap_exchange_active_domain_config();
        $_canExchange = !empty($_exCfg['enabled']);
    }
?>
<div class="row gx-0" id="manualCreateFormContainerRow" style="display: none; position: relative; z-index: 9999;">
    <div class="col-12">
        <div class="card h-100 slide-in-top overflow-visible-card">
            <div class="card-body">
                <div id="manualCreateFormContainer">
                    <h3 class="card-title" id="manualCreateFormTitle"><i class="fas fa-user-plus"></i> Manual User Creation</h3>

                    <!-- Service Account Option (visible only in create mode) -->
                    <div id="serviceAccountSection" class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="serviceAccountCheck" name="isServiceAccount" value="true">
                            <label class="form-check-label" for="serviceAccountCheck"><strong>Service Account</strong></label>
                        </div>
                        <div class="form-check mt-2" id="enableMailboxWrap" style="<?= $_canExchange ? '' : 'display: none;' ?>">
                            <input type="checkbox" class="form-check-input" id="enableMailboxCheck" name="enable_mailbox" value="true">
                            <label class="form-check-label" for="enableMailboxCheck"><i class="fas fa-envelope me-1"></i>Enable Mailbox</label>
                        </div>
                    </div>
                    <div id="serviceAccountFields" style="display: none;" class="mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="serverOperation" class="form-label">Server / Operation</label>
                                <input type="text" class="form-control" id="serverOperation" name="serverOperation" placeholder="e.g. Print Server, SQL Backup, Monitoring">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="servicePwdNeverExpires" name="passwordNeverExpires" value="true" checked>
                                    <label class="form-check-label" for="servicePwdNeverExpires">Password never expires</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Standard fields (create mode / modify without Exchange) -->
                    <div id="standardFormFields">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="manualDisplayName" class="form-label">Display Name</label>
                                <input type="text" class="form-control" id="manualDisplayName" name="manualDisplayName" placeholder="e.g. John Doe" required list="manualDisplayName-suggestions" autocomplete="off">
                                <datalist id="manualDisplayName-suggestions"></datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="manualUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="manualUsername" name="manualUsername" placeholder="e.g. jdoe" required list="manualUsername-suggestions" autocomplete="off">
                                <datalist id="manualUsername-suggestions"></datalist>
                                <input type="hidden" id="originalUsername" name="originalUsername">
                            </div>
                        </div>
                        <div class="row" style="position: relative; z-index: 99999;">
                            <div class="col-md-6 mb-3">
                                <label for="manualDescription" class="form-label">Description</label>
                                <input type="text" class="form-control" id="manualDescription" name="manualDescription" placeholder="e.g. Sales Department User" list="manualDescription-suggestions" autocomplete="off">
                                <datalist id="manualDescription-suggestions"></datalist>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="manualOUDisplay" class="form-label">Organizational Unit (OU)</label>
                                <div class="custom-select-container">
                                    <input type="text" id="manualOUDisplay" class="form-control" placeholder="Type to search OU...">
                                    <input type="hidden" id="manualOU" name="manualOU">
                                    <div class="custom-select-dropdown" id="manualOUDropdown">
                                        <ul class="custom-select-list" id="manualOUList">
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exchange / Modify Identity Section -->
                    <div id="exchangeIdentitySection" style="display:none;">
                        <!-- Identity -->
                        <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong style="font-size:var(--font-sm);"><i class="fas fa-id-card me-1"></i>Identity <i class="fas fa-info-circle text-muted" data-noc-tip="User identity and contact information. Changes save to Active Directory." style="font-size:10px;cursor:help"></i></strong>
                                <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('mbSaveProfileBtn').click();" style="height:28px;font-size:var(--font-xs);padding:0 10px;" data-noc-tip="Save all identity, organization, and mailbox changes"><i class="fas fa-save me-1"></i> Save Identity</button>
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <label style="font-size:10px;color:var(--text-muted);">First name:</label>
                                    <input type="text" id="exFirstName" placeholder="First name" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-3">
                                    <label style="font-size:10px;color:var(--text-muted);">Initials:</label>
                                    <input type="text" id="exInitials" placeholder="Initials" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-5">
                                    <label style="font-size:10px;color:var(--text-muted);">Last name:</label>
                                    <input type="text" id="exLastName" placeholder="Last name" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-sm-6">
                                    <label style="font-size:10px;color:var(--text-muted);">*Name <span style="font-weight:400;">(Active Directory)</span>:</label>
                                    <input type="text" id="exFullName" placeholder="Full Name" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-6">
                                    <label style="font-size:10px;color:var(--text-muted);">*Display name <span style="font-weight:400;">(address book)</span>:</label>
                                    <input type="text" id="exDisplayName" placeholder="Display name" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-sm-6">
                                    <label style="font-size:10px;color:var(--text-muted);">*Alias <span style="font-weight:400;">(email prefix)</span>:</label>
                                    <input type="text" id="exAlias" placeholder="Alias" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-6">
                                    <label style="font-size:10px;color:var(--text-muted);">*User logon name:</label>
                                    <div class="d-flex gap-1">
                                        <input type="text" id="exLogonName" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                        <span style="line-height:28px;font-size:var(--font-xs);">@</span>
                                        <input type="text" id="exDomainSuffix" readonly style="width:160px;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;background:var(--border-color);">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-3 mt-2">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="exHideFromGal">
                                    <label class="form-check-label" style="font-size:10px" for="exHideFromGal">Hide from address lists <i class="fas fa-info-circle text-muted" data-noc-tip="Hides this mailbox from the Global Address List (GAL) in Outlook and Exchange. The user can still receive emails but won't appear in address searches." style="font-size:10px;cursor:help"></i></label>
                                </div>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-sm-6">
                                    <label style="font-size:10px;color:var(--text-muted);">Organizational unit:</label>
                                    <div class="custom-select-container" style="position:relative">
                                        <input type="text" id="exOUDisplay" placeholder="Type to search OU..." style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                        <input type="hidden" id="exOU" name="exOU">
                                        <div class="custom-select-dropdown" id="exOUDropdown">
                                            <ul class="custom-select-list" id="exOUList"></ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label style="font-size:10px;color:var(--text-muted);">Mailbox database:</label>
                                    <select id="exMailboxDb" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;">
                                        <option value="">Default</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-2" style="display:none;">
                                <button class="btn btn-sm btn-primary" id="mbSaveProfileBtn" style="font-size:11px;padding:2px 14px"><i class="fas fa-save"></i> Save Identity</button>
                                <span id="mbProfileFeedback" class="small ms-2"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Group Members -->
                    <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">
                        <div class="d-flex align-items-center mb-2">
                            <strong style="font-size:var(--font-sm);"><i class="fas fa-users me-1"></i>Group Members <i class="fas fa-info-circle text-muted" data-noc-tip="Search and add security/distribution groups. Use the search below to find groups." style="font-size:10px;cursor:help"></i></strong>
                        </div>
                        <div class="custom-select-container">
                            <div id="selectedGroupMembersTags" class="selected-tags-container"></div>
                            <input type="text" id="manualGroupMemberDisplay" placeholder="Type to search groups..." style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;" data-noc-tip="Start typing a group name to search. Click a result to add the user to that group.">
                            <input type="hidden" id="manualGroupMembers" name="manualGroupMembers">
                            <div class="custom-select-dropdown" id="manualGroupMemberDropdown">
                                <ul class="custom-select-list" id="manualGroupMemberList"></ul>
                            </div>
                        </div>
                    </div>

                        <!-- Organization -->
                        <div class="mb-3 pb-2" style="border-bottom:1px solid var(--border-color);">
                            <div class="d-flex align-items-center mb-2">
                                <strong style="font-size:var(--font-sm);"><i class="fas fa-building me-1"></i>Organization <i class="fas fa-info-circle text-muted" data-noc-tip="Job title, department, company, and contact details. Saved to Active Directory profile." style="font-size:10px;cursor:help"></i></strong>
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <label style="font-size:10px;color:var(--text-muted);">Title:</label>
                                    <input type="text" id="exTitle" placeholder="Job title" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-4">
                                    <label style="font-size:10px;color:var(--text-muted);">Department:</label>
                                    <input type="text" id="exDepartment" placeholder="Department" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-4">
                                    <label style="font-size:10px;color:var(--text-muted);">Company:</label>
                                    <input type="text" id="exCompany" placeholder="Company" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-sm-4">
                                    <label style="font-size:10px;color:var(--text-muted);">Office:</label>
                                    <input type="text" id="exOffice" placeholder="Office location" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                                <div class="col-sm-4">
                                    <label style="font-size:10px;color:var(--text-muted);">Work phone:</label>
                                    <input type="text" id="exPhone" placeholder="Phone number" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                </div>
                            </div>
                        </div>

                        <!-- Exchange Mailbox -->
                    <div id="modifyMailboxSection" style="display: none;">
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong style="font-size:var(--font-sm);"><i class="fas fa-envelope me-1"></i>Exchange Mailbox <i class="fas fa-info-circle text-muted" data-noc-tip="Manage Exchange mailbox settings: email addresses, quota, archive, CAS (ActiveSync/OWA), and forwarding." style="font-size:10px;cursor:help"></i></strong>
                                <div id="mbStatusWrapper">
                                    <span id="mbStatus" class="status-badge status-success" style="display:none;font-size:10px;">Enabled</span>
                                    <span id="mbRecipientType" class="status-badge status-info" style="display:none;font-size:10px;"></span>
                                </div>
                            </div>

                            <!-- Has Mailbox -->
                            <div id="mailboxHasMailbox" style="display:none">
                                <div id="mbAdvancedBody"></div>
                            </div>

                            <!-- No Mailbox -->
                            <div id="mailboxNoMailbox" style="display:none">
                                <div class="mb-2 text-muted" style="font-size:var(--font-xs);"><i class="fas fa-info-circle me-1"></i>This user does not have an Exchange mailbox.</div>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <label style="font-size:10px;color:var(--text-muted);">Alias</label>
                                        <input type="text" id="modifyMailboxAlias" placeholder="Alias (default: username)" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                    </div>
                                    <div class="col-sm-6">
                                        <label style="font-size:10px;color:var(--text-muted);">Database</label>
                                        <select id="modifyMailboxDatabase" style="width:100%;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;"><option value="">Default</option></select>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-success" id="mbCreateBtn" style="height:28px;font-size:var(--font-xs);"><i class="fas fa-envelope me-1"></i> Create Mailbox</button>
                                    <span id="mbCreateFeedback" class="ms-2" style="font-size:var(--font-xs);"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div id="modifyPasswordSection" style="display: none;">
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong style="font-size:var(--font-sm);"><i class="fas fa-lock me-1"></i>Password <i class="fas fa-info-circle text-muted" data-noc-tip="Reset password and configure password policy settings for this user." style="font-size:10px;cursor:help"></i></strong>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" id="modifyResetPassword" name="resetPassword" value="true">
                                <label class="form-check-label" style="font-size:var(--font-xs);" for="modifyResetPassword">Reset Password <i class="fas fa-info-circle text-muted" data-noc-tip="Enable this to set a new password for the user. You can provide a temporary password or use the system default." style="font-size:9px;cursor:help"></i></label>
                            </div>
                            <div id="modifyPasswordOptions" style="display: none;">
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <label style="font-size:10px;color:var(--text-muted);">Temporary Password</label>
                                        <div class="d-flex gap-1">
                                            <input type="password" id="modifyTemporaryPassword" name="temporaryPassword" placeholder="Leave blank for default password" style="flex:1;height:28px;font-size:var(--font-xs);border:1px solid var(--border-color);border-radius:4px;padding:0 8px;">
                                            <button type="button" class="btn btn-sm btn-outline-secondary password-toggle-btn" data-toggle-target="modifyTemporaryPassword" style="height:28px;width:32px;padding:0;"><i class="fas fa-eye"></i></button>
                                        </div>
                                        <div style="font-size:10px;color:var(--text-muted);">Leave empty to use system default password.</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="modifyForcePasswordChange" name="forcePasswordChange" value="true" checked>
                                            <label class="form-check-label" style="font-size:var(--font-xs);" for="modifyForcePasswordChange">Force password change on next login</label>
                                        </div>
                                        <div class="form-check mt-1">
                                            <input type="checkbox" class="form-check-input" id="modifyUseDefaultPassword" name="useDefaultPassword" value="true">
                                            <label class="form-check-label" style="font-size:var(--font-xs);" for="modifyUseDefaultPassword">Use default password</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="modifyPwdMustChange" name="pwdMustChange" value="true">
                                <label class="form-check-label" style="font-size:var(--font-xs);" for="modifyPwdMustChange">User must change password at next login <i class="fas fa-info-circle text-muted" data-noc-tip="The user will be prompted to change their password the next time they log in." style="font-size:9px;cursor:help"></i></label>
                            </div>
                            <div class="form-check mt-1">
                                <input type="checkbox" class="form-check-input" id="modifyPwdCantChange" name="pwdCantChange" value="true">
                                <label class="form-check-label" style="font-size:var(--font-xs);" for="modifyPwdCantChange">User cannot change password <i class="fas fa-info-circle text-muted" data-noc-tip="Prevents the user from changing their own password. Only administrators can reset it." style="font-size:9px;cursor:help"></i></label>
                            </div>
                            <div class="form-check mt-1">
                                <input type="checkbox" class="form-check-input" id="modifyPwdNeverExpires" name="pwdNeverExpires" value="true">
                                <label class="form-check-label" style="font-size:var(--font-xs);" for="modifyPwdNeverExpires">Password never expires <i class="fas fa-info-circle text-muted" data-noc-tip="The password will not expire. Use with caution as this reduces security." style="font-size:9px;cursor:help"></i></label>
                            </div>
                        </div>
                    </div>

                    <div class="app-form-actions">
                        <?php if (has_permission('action_submit_manual_create')): ?>
                        <button type="button" id="submitManualCreate" class="btn btn-primary">
                            <i class="fas fa-save"></i> Submit
                        </button>
                        <?php endif; ?>
                        <button type="button" id="cancelManualCreateButton" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
