# 02 — Permission Catalog

> **Document ID:** TP-RBAC-001 · **Version:** 1.1 · **Status:** ACTIVE
> Complete inventory of every grantable permission key, grouped by page category, as defined in `config/components_config.php` (610 lines, 274 keys). The role create/edit form renders this exact tree.

---

## Legend

- **Page category** = top-level checkbox group shown in the role form (e.g. `page_monitoring`).
- `permissions` = direct permission keys (usually page-level capabilities).
- `cards` = visible feature cards; may contain nested `cards` and `buttons`.
- `buttons` = specific operations a user can perform.
- `*` = wildcard (only `core_admin`).

## Catalog Summary

| # | Page Category | Key |
|---|---------------|-----|
| 1 | Global Components | `global_components` |
| 2 | AD Administration | `page_ad_administration` |
| 3 | Dashboard | `page_dashboard` |
| 4 | User Management | `page_user_management` |
| 5 | Password Manager | `page_password_manager` |
| 6 | Role Management | `page_role_management` |
| 7 | Application Events | `page_application_events` |
| 8 | Infrastructure Monitor | `page_monitoring` |
| 9 | System Configuration | `page_system_config` |
| 10 | Vendor License Console | `page_vendor_console` |
| 11 | About Us | `page_about_us` |
| 12 | Documentation | `page_documentation` |
| 13 | Documentation Guide | `page_documentation_guide` |
| 14 | License Center | `page_license` |
| 15 | Change Password | `page_change_password` |
| 16 | Profile Hub | `page_profile` |
| 17 | Employee DB | `page_employee_db` |
| 18 | Email Analysis | `page_email_tools` |
| 19 | Exchange Management | `page_exchange` |

---

## 1. Global Components (`global_components`)

Quick-action cards that appear across pages.

### `card_assistant` — Assistant Card

| Button | Key |
|--------|-----|
| Info | `action_get_info` |
| Unlock | `action_unlock` |
| New User | `action_new_user_form` |
| U & Reset | `action_u_and_reset` |
| Disable | `action_disable` |
| Enable | `action_enable` |
| Manual | `action_manual_create_form` |
| Modify | `action_modify_user_form` |
| Directory | `action_directory_builder` |
| Open Dashboard | `action_dashboard` |

### `card_manual_create_form` — Manual User Creation Form

| Button | Key |
|--------|-----|
| Manual Create User API Access | `manual_create_user` |
| Create User Manually Button | `action_submit_manual_create` |
| Cancel Button | `action_cancel_manual_create` |

### `card_directory_builder_form` — OU and Groups Manager Form

| Button | Key |
|--------|-----|
| Create Directory Object | `action_directory_builder_create` |
| Manage Group Membership | `action_directory_builder_manage` |
| Delete Directory Object | `action_directory_builder_delete` |
| Search Delete Target | `action_directory_builder_search_delete_target` |
| Queue Group Member | `action_directory_builder_queue_member` |

### `card_modify_user_form` — Modify User Form

| Button | Key |
|--------|-----|
| Update User Button | `action_update_user` |
| Cancel Button | `action_cancel_modify` |

### `card_get_report` — Intelligence Hub

| Button | Key |
|--------|-----|
| HRMS AD | `action_get_ad_hrms_status` |
| HRMS AD (User ID Export) | `action_export_hrms_ad_user_id` |
| Users | `action_export_ad_users` |
| Reports | `action_user_report` |
| Events | `action_security_events` |
| Health | `action_ad_health_check` |

### `card_notification_center` — Notification Center

| Button | Key |
|--------|-----|
| Receive Announcement Notifications | `notif_category_announcement` |
| Receive Request Notifications | `notif_category_requests` |
| Receive Activity Notifications | `notif_category_activity` |
| Receive AD Action Notifications | `notif_category_ad_actions` |
| Receive Report Notifications | `notif_category_reports` |
| Receive Security Notifications | `notif_category_security` |
| Update Notification Preferences | `action_notification_preferences` |
| Send Notifications | `action_notification_send` |
| Manage Notifications | `action_notification_manage` |

## 2. AD Administration (`page_ad_administration`)

### `permissions`

| Key | Name |
|-----|------|
| `execute_ad_actions` | Execute All AD Actions |
| `view_user_info` | View User Information |
| `modify_ad_user` | Modify AD User |
| `reset_user_password` | Reset AD User Password |
| `view_ad_groups` | View AD Groups |
| `view_ad_ous` | View AD Organizational Units |
| `view_hrms_status` | View HRMS Status |

### `cards`

| Card | Key | Buttons |
|------|-----|---------|
| Server Information Card | `card_server_info` | — |
| Employee Information Card | `card_employee_info` | — |
| Recent Activity Card | `card_recent_activity` | — |
| AD User Request Admin Card | `card_ad_user_request_admin` | `action_ad_request_approve`, `action_ad_request_deny` |

> **Menu fallback:** Infrastructure Monitor, System Configuration, and Home still honor this legacy key via OR-lists (`page_monitoring|page_ad_administration`, `page_system_config|page_ad_administration`).

## 3. Dashboard (`page_dashboard`)

| Card | Key |
|------|-----|
| Today's Log Card | `card_dashboard_today_log` |
| Weekly Logs Card | `card_dashboard_weekly_logs` |
| Monthly Activity Card | `card_dashboard_monthly_activity` |
| Action Status Breakdown Card | `card_dashboard_action_status` |
| Status Breakdown Card | `card_dashboard_status_breakdown` |
| Top Users Card | `card_dashboard_top_users` |
| Filter Bar | `card_dashboard_filter_bar` |
| Detailed Log Table | `card_dashboard_log_table` |
| Exchange Monitor Card | `card_dashboard_exchange_monitor` |

## 4. User Management (`page_user_management`)

### `permissions`

| Key | Name |
|-----|------|
| `user_create` | Access Create User Page |

### `cards`

#### `card_pending_requests` — Pending Registration Requests Card

| Button | Key |
|--------|-----|
| Create New Users | `action_usermgmt_create` |
| Approve/Deny Registration Requests | `action_usermgmt_approve_deny` |
| Approve User Request Permission | `user_approve_request` |

#### `card_existing_users` — Existing Users Card

| Button | Key |
|--------|-----|
| Edit Existing Users | `action_usermgmt_edit` |
| Reset User Passwords | `action_usermgmt_reset` |
| Delete Users | `action_usermgmt_delete` |
| Access Edit User Page | `user_edit` |
| Delete User Permission | `user_delete` |
| Reset User Password Permission | `user_password_reset` |
| Terminate User Session | `terminate_user_session` |

#### `card_user_create_form` — Create User Form

| Button | Key |
|--------|-----|
| Access Create User Form | `user_create` |
| Submit Create User Form | `manual_create_user` |

#### `card_user_edit_form` — Edit User Form

| Button | Key |
|--------|-----|
| Access Edit User Form | `user_edit` |
| Reset Password from Edit Form | `user_password_reset` |

> Page gates: `create_user` → `page_user_management|user_create` · `edit_user` → `page_user_management|user_edit`.

## 5. Password Manager (`page_password_manager`)

### `card_my_passwords` — My Passwords

| Button | Key |
|--------|-----|
| Password Management Table | `card_password_manager` |
| Create New Password Entry | `action_password_create` |
| Edit a Password Entry | `action_password_edit` |
| Delete a Password Entry | `action_password_delete` |
| Share Password to the global table | `action_password_share` |
| View All Users' Passwords (Admin) | `action_password_view_all` |

### `card_global_passwords` — Global Passwords

| Button | Key |
|--------|-----|
| Global Passwords Page Access | `page_global_passwords` |
| Edit Global Password Entry | `action_global_password_edit` |
| Delete Global Password Entry | `action_global_password_delete` |

## 6. Role Management (`page_role_management`)

### `card_roles_list` — Roles List Card

| Button | Key |
|--------|-----|
| Create New Roles | `action_role_create` |
| Edit Existing Roles | `action_role_edit` |
| Delete Roles | `action_role_delete` |
| Add Users to Roles | `action_role_add_member` |
| Remove Users from Roles | `action_role_remove_member` |

### `card_role_form` — Role Create/Edit Form

| Button | Key |
|--------|-----|
| Create Role Form Access | `action_role_create` |
| Edit Role Form Access | `action_role_edit` |
| Add Role Members from Form | `action_role_add_member` |
| Remove Role Members from Form | `action_role_remove_member` |

> `create_role` / `edit_role` pages are gated inside `role_form_view.php` by `page_role_management` + the matching `action_role_*`.

## 7. Application Events (`page_application_events`)

| Card | Key |
|------|-----|
| Header & Date Filters | `card_event_filters` |
| User Activity Tracking Chart | `card_event_overview` |
| Secondary User Activity Tracking Chart | `card_user_activity_tracking` |
| Hourly Activity Chart | `card_event_hourly_activity` |
| Top Actions Chart | `card_event_top_actions` |
| Active Sessions Card | `card_event_active_sessions` |
| Activity Log Table | `card_event_log_table` |

## 8. Infrastructure Monitor (`page_monitoring`)

### `permissions`

| Key | Name |
|-----|------|
| `view_monitoring_system` | View System Monitor Tab |
| `view_monitoring_hub` | View Infrastructure Hub Tab |
| `view_monitoring_network` | View Network Operations Tab |

### `cards`

| Card | Key | Buttons |
|------|-----|---------|
| Infrastructure Hub Overview | `card_monitoring_overview` | `action_monitoring_run_sweep`, `action_monitoring_add_node`, `action_monitoring_add_node_submit` |
| Multi-Node RTT Timeline | `card_monitoring_timeline` | `action_monitoring_rtt_pause`, `action_monitoring_rtt_export` |
| Monitoring Grid | `card_monitoring_grid` | — |
| Infrastructure Event Logs | `card_monitoring_event_logs` | `action_monitoring_load_logs`, `action_monitoring_export_logs` |
| Deep Analysis Focus Area | `card_monitoring_focus_area` | — |
| System Monitor (Container & Infra) | `card_system_monitor` | nested cards ↓ |
| └ Container Card | `card_container_monitor` | `action_container_export` |
| └ System Infrastructure | `card_system_infra_monitor` | `action_system_trend_export` |
| └ Advanced Analytics Charts | `card_advanced_analytics` | `action_advanced_analytics_refresh` |
| Network Profiler | `card_monitoring_network_profiler` | `action_monitoring_calculate_network`, `action_monitoring_scan_block`, `action_monitoring_cancel_scan` |
| Discovery Stream | `card_monitoring_discovery_stream` | `action_monitoring_export_scan` |
| Diagnostic Ping | `card_monitoring_ping` | `action_monitoring_manual_ping`, `action_monitoring_stop_ping` |
| DNS Lookup | `card_monitoring_dns_lookup` | `action_monitoring_dns_lookup` |
| Port Check | `card_monitoring_port_check` | `action_monitoring_port_check` |
| Traceroute | `card_monitoring_traceroute` | `action_monitoring_traceroute` |
| MTR Report | `card_monitoring_mtr` | `action_monitoring_mtr_report` |
| WHOIS Lookup | `card_monitoring_whois` | `action_monitoring_whois` |
| Multi-Ping Test | `card_monitoring_multi_ping` | `action_monitoring_multi_ping` |
| Node Management | `card_monitoring_node_management` | `action_monitoring_node_summary`, `action_monitoring_delete_node` |

> Page gate: `page_monitoring|page_ad_administration`.

## 9. System Configuration (`page_system_config`)

| Card | Key | Buttons |
|------|-----|---------|
| Deployment & System Configuration | `card_system_configuration` | `action_system_update_domain`, `action_system_update_credentials`, `action_system_update_storage`, `action_system_update_passwords`, `action_system_confirm_update`, `action_system_ldap_test`, `action_system_ldap_test_user` |
| Domain Configuration | `card_system_domain_config` | `action_system_domain_add`, `action_system_domain_switch`, `action_system_domain_edit`, `action_system_domain_delete`, `action_system_domain_test` |
| Application Configuration | `card_system_application_config` | `action_system_save_org`, `action_system_test_integration`, `action_system_save_integrations`, `action_system_save_storage`, `action_system_save_passwords`, `action_system_refresh_diagnostics` |

> Page gate: `page_system_config|page_ad_administration`. Controller gate: same OR-list.

## 10. Vendor License Console (`page_vendor_console`)

| Card | Key | Buttons |
|------|-----|---------|
| License Generator | `card_vendor_license_generator` | `action_vendor_save_license`, `action_vendor_reset_form`, `action_vendor_verify_credentials` |
| License Tracking Table | `card_vendor_tracking_table` | `action_vendor_refresh_list` |
| Signing Key Management | `card_vendor_key_management` | `action_vendor_save_key`, `action_vendor_delete_key` |
| Build Client Release | `card_vendor_build_release` | `action_vendor_build_release` |
| Vendor Console Log | `card_vendor_console_log` | `action_vendor_clear_log` |
| Vendor Documentation Tree | `card_vendor_documentation` | — |

> Page gate: `page_vendor_console` (controller also accepts `page_license`).

## 11. About Us (`page_about_us`)

| Card | Key |
|------|-----|
| About Information | `card_about_info` |
| Current Version & Update | `card_about_version` |
| Meet the Team | `card_about_team` |
| Key Features & Enhancements | `card_about_features` |
| Documentation & Upgrades | `card_about_docs` |
| Our Mission | `card_about_mission` |

## 12. Documentation (`page_documentation`)

| Card | Key |
|------|-----|
| Documentation Content | `card_doc_content` |

## 13. Documentation Guide (`page_documentation_guide`)

| Card | Key |
|------|-----|
| Guide Content | `card_doc_guide_content` |

## 14. License Center (`page_license`)

| Card | Key | Buttons |
|------|-----|---------|
| License Status Banner | `card_license_status` | — |
| Certificate of Authenticity | `card_license_certificate` | — |
| Usage Terms | `card_license_policy` | — |
| Sync Material Panel | `card_license_sync` | `action_license_sync_renewal` |
| Deployment Lifecycle & Behavior | `card_license_lifecycle` | — |
| Vendor Support | `card_license_support` | — |
| Verification Info | `card_license_verification` | — |

## 15. Change Password (`page_change_password`)

| Card | Key |
|------|-----|
| Change Password Form | `card_change_password_form` |

## 16. Profile Hub (`page_profile`)

| Card | Key | Buttons |
|------|-----|---------|
| Profile Identity Card | `card_profile_identity` | `action_profile_update_avatar`, `action_profile_update_details` |
| Quick Stats Card | `card_profile_quick_stats` | — |
| Theme Personalization Card | `card_profile_theme` | `action_profile_change_theme` |
| Notification Preferences Card | `card_profile_notifications` | `action_profile_test_sound`, `action_profile_save_notifications` |
| Security & Password Card | `card_profile_security` | `action_profile_change_password` |
| Recent Activity Card | `card_profile_recent_activity` | — |

## 17. Employee DB (`page_employee_db`)

### `card_employee_db_table` — Employee Table

| Button | Key |
|--------|-----|
| Search Employee Button | `action_employee_search` |
| Add Employee Button | `action_add_employee` |
| Edit Employee Button | `action_edit_employee` |
| Delete Employee Button | `action_delete_employee` |
| Save Employee Button | `action_save_employee` |

> Page gate: `page_employee_db`. View guard: `page_employee_db` + per-action `can_*` checks.

## 18. Email Analysis (`page_email_tools`)

| Card | Key | Buttons |
|------|-----|---------|
| DNS Record Lookup | `card_email_dns` | `action_email_dns_lookup` |
| Header Analysis | `card_email_header` | `action_email_header_parse` |
| Blacklist Check | `card_email_blacklist` | `action_email_blacklist_check` |
| Email Validation | `card_email_validate` | `action_email_validate` |
| SMTP Server Test | `card_email_smtp_test` | `action_email_smtp_test` |
| Mail Port Scanner | `card_email_port_scan` | `action_email_port_scan` |
| BIMI Record Check | `card_email_bimi` | `action_email_bimi_check` |
| MTA-STS Check | `card_email_mta_sts` | `action_email_mta_sts_check` |

> Controller requires `page_email_tools` AND the per-action key. See `email_tools.php` permission map.

## 19. Exchange Management (`page_exchange`)

### `permissions`

| Key | Name |
|-----|------|
| `action_exchange_mailbox_view` | View Mailbox Information |
| `action_exchange_mailbox_enable` | Enable Mailbox |
| `action_exchange_mailbox_disable` | Disable Mailbox |
| `action_exchange_mailbox_quota` | Set Mailbox Quota |
| `action_exchange_mailbox_forward` | Configure Forwarding |
| `action_exchange_mailbox_address` | Manage Email Addresses |
| `action_exchange_group_view` | View Distribution Groups |
| `action_exchange_group_create` | Create Distribution Groups |
| `action_exchange_group_modify` | Modify Group Members |
| `action_exchange_group_delete` | Delete Distribution Groups |
| `action_exchange_monitoring` | Access Exchange Monitoring |
| `action_exchange_settings` | Modify Exchange Settings |

### `cards`

| Card | Key |
|------|-----|
| Mailbox Search & Info | `card_exchange_mailbox` |
| Distribution Groups | `card_exchange_groups` |
| Quota & Queue Monitoring | `card_exchange_monitoring` |
| Exchange Connection Settings | `card_exchange_settings` |

> Controller enforces `page_exchange` AND the per-action key from its 42-action permission map (see `exchange.php`).

---

## Reserved / Protected Roles

| Role | Permissions | Notes |
|------|-------------|-------|
| `core_admin` | `["*"]` | Full bypass. Cannot be deleted. |
| `View only` | read-only keys | Cannot be deleted. |
| `user` | `page_dashboard`, `page_password_manager`, `card_my_passwords` | Default fallback role. |

The `admin` user cannot be reassigned away from `core_admin` (enforced in `add_role_member`).

---

*Catalog source: `config/components_config.php` (610 lines, 274 keys). Generated from the live config — if you add keys, re-run the dump pattern in `03-implementation-guide.md` to keep this list in sync.*
