<?php
if (!isset($projectName) || $projectName === '') {
    $projectName = $app_config['app_info']['name'] ?? $app_config['domain_name'] ?? 'AccessPilot';
}
?>

<div class="card">
    <div class="card-body">
        <div class="doc-section">
            <h2>1. Why This Product Exists</h2>
            <p><strong><?= $projectName ?></strong> exists for organizations that are tired of handling identity operations through scattered tools, risky shortcuts, and undocumented human memory. In many teams, HRMS keeps employee truth, Active Directory keeps technical identity, support teams manage resets, request approvals happen in chat, and shared credentials live in places they should never live. That is not a system. That is operational fragility.</p>
            <p>This product turns those fragmented tasks into one connected operating environment. It gives teams a place where identity creation, password action, account recovery, employee-data alignment, approvals, notifications, activity review, and credential handling all move through a single operational workflow.</p>
            <p>The result is not just faster administration. The result is better control, better traceability, lower error, and stronger confidence when teams need to answer who changed what, why it happened, and what happened next.</p>
        </div>

        <div class="doc-section">
            <h2>2. What A Buyer Actually Gets</h2>
            <p>This application is not a simple admin panel with a few buttons. It is an internal identity operations platform built around real service desk and operations behavior.</p>
            <ul>
                <li><strong>Identity Operations:</strong> unlock, enable, disable, reset, reset-and-unlock, fetch account intelligence, modify profiles, and create new users.</li>
                <li><strong>HRMS-Aware Provisioning:</strong> employee code can drive create and update flows through live HRMS retrieval.</li>
                <li><strong>Approval Workbench:</strong> request review, registration approval, and password-related request handling from one operator surface.</li>
                <li><strong>Credential Vault:</strong> encrypted personal and shared password storage with controlled visibility.</li>
                <li><strong>Operational Visibility:</strong> home-page recent activity, today's logs, dashboard charts, top-user views, detailed logs, and status breakdowns.</li>
                <li><strong>Request and Notification Layer:</strong> request submission, follow-up, and notification handling in one loop.</li>
                <li><strong>Reporting and Exports:</strong> AD and HRMS alignment exports, health review, user reports, group reports, and operational support views.</li>
            </ul>
            <p>This means the product supports both the daily operator and the oversight role. One person uses it to take action. Another person uses it to understand behavior and enforce control.</p>
        </div>

        <div class="doc-section">
            <h2>3. How The Experience Is Structured</h2>
            <p>The application is designed around how real teams work during a day, not around arbitrary technical separation.</p>
            <ul>
                <li><strong>Home:</strong> the live operator console. Quick actions remain visible. Recent activity and today's logs provide immediate awareness.</li>
                <li><strong>Dashboard:</strong> deeper operational analysis. This is where teams see trend direction, action volume, top actors, and broader execution patterns.</li>
                <li><strong>App Events:</strong> operational activity review for investigation and audit-minded follow-up.</li>
                <li><strong>Identity &amp; Access:</strong> pending request review, approval, edit user, reset, delete, and session-related control.</li>
                <li><strong>Credential Vault:</strong> personal and shared credential lifecycle with controlled exposure.</li>
                <li><strong>Instructions and Documentation:</strong> operator readiness, institutional understanding, and handover resilience.</li>
            </ul>
            <p>This structure reduces training overhead. Operators do not need to remember which external tool solves which step. The platform groups related action and related context together.</p>
        </div>

        <div class="doc-section">
            <h2>4. Identity Operations In Practical Terms</h2>
            <p>The quick-action layer is one of the strongest parts of the product because it removes the delay between knowing there is a problem and executing a controlled action.</p>
            <ul>
                <li><strong>Get Info:</strong> reads AD user state and exposes profile, security, workstation, and account details.</li>
                <li><strong>Unlock:</strong> releases locked accounts fast.</li>
                <li><strong>U &amp; Reset:</strong> combines password reset and unlock into one recovery action.</li>
                <li><strong>Enable / Disable:</strong> changes account state without leaving the portal.</li>
                <li><strong>Manual Create:</strong> allows operator-driven AD user creation with business and directory context.</li>
                <li><strong>Create from HRMS:</strong> provisions directly from employee code using live HRMS-backed data.</li>
                <li><strong>Modify User:</strong> opens the edit flow for HRMS refresh, password change, and profile alignment.</li>
            </ul>
            <p>For a buyer, this matters because most day-to-day identity pain is repetitive, urgent, and time-sensitive. A platform that reduces those steps into one operator surface directly improves response speed.</p>
        </div>

        <div class="doc-section">
            <h2>5. HRMS Integration Is A Functional Advantage</h2>
            <p>Many systems mention HRMS integration, but they often treat it as a simple lookup convenience. Here it has a stronger role. Employee code acts as an operational bridge between business truth and technical identity action.</p>
            <ul>
                <li><strong>Create User:</strong> employee code can drive live retrieval of employee data and reduce manual field entry.</li>
                <li><strong>Edit User:</strong> operator can refresh identity data from HRMS when business records change.</li>
                <li><strong>Status Comparison:</strong> AD and HRMS can be reviewed together for mismatch visibility.</li>
                <li><strong>Export Workflows:</strong> the platform can support broader reporting around HRMS and AD alignment.</li>
            </ul>
            <p>This reduces duplicate typing, reduces identity drift, and speeds onboarding. Those are direct operational savings, not cosmetic features.</p>
        </div>

        <div class="doc-section">
            <h2>6. Approval Flow Makes The Product Mature</h2>
            <p>A serious identity platform should not assume every account request deserves immediate execution without review. That is why this application separates request submission from administrative decision.</p>
            <p>Requests can enter the system through the request side of the platform. Review happens in the management workbench. Operators get requester context, target information, and approval or denial control inside the same environment.</p>
            <p>This removes dependence on ad-hoc chat approvals, untracked phone requests, and memory-based exception handling. In short, the product turns unmanaged requests into controlled operational work.</p>
        </div>

        <div class="doc-section">
            <h2>7. Password Vault Solves A Real Governance Problem</h2>
            <p>Most teams already manage sensitive credentials. The question is whether they manage them safely. Without a structured vault, those credentials spread into personal notes, messages, spreadsheets, and memory. That creates operational and audit risk.</p>
            <p>This platform includes a built-in credential vault with two natural scopes:</p>
            <ul>
                <li><strong>My Passwords:</strong> personal operational entries for the current user.</li>
                <li><strong>Global Passwords:</strong> broader operational entries when permissions allow shared visibility.</li>
            </ul>
            <p>Entries can be created, updated, removed, expanded, and viewed with controlled exposure. For teams that need a practical operational vault without teaching users a completely separate daily workflow, this is a meaningful product capability.</p>
        </div>

        <div class="doc-section">
            <h2>8. Home And Dashboard Work Together</h2>
            <p>The Home page and Dashboard are intentionally different. Home is an execution cockpit. Dashboard is an observability surface.</p>
            <ul>
                <li><strong>Home:</strong> current-day awareness, quick action continuity, and immediate activity context.</li>
                <li><strong>Dashboard:</strong> daily, weekly, monthly, filtered, and detailed operational visibility for pattern reading.</li>
            </ul>
            <p>This gives the product a two-layer monitoring model. Front-line operators stay informed in real time. Team leads or senior administrators can read behavior, intensity, and operational health at a broader level.</p>
        </div>

        <div class="doc-section">
            <h2>9. Notifications And Requests Create A Closed Loop</h2>
            <p>A platform becomes operationally valuable when actions do not end at the button click. Request flow, execution flow, notification flow, and logging flow need to reinforce one another.</p>
            <p>That is what this product does. It supports request intake, review, action, notification, and visibility in a connected sequence. This creates a closed operational loop: someone asks, someone reviews, someone acts, the system records, and the organization can later understand the decision and the outcome.</p>
        </div>

        <div class="doc-section">
            <h2>10. Security And Governance Value</h2>
            <p>One of the strongest commercial arguments for the platform is that it improves control without forcing operators into a slow or hostile process.</p>
            <ul>
                <li>RBAC protects sensitive surfaces and actions.</li>
                <li>Vault entries are encrypted.</li>
                <li>Default password behavior is configuration-driven.</li>
                <li>Audit and activity logging reduce blind execution.</li>
                <li>HRMS-backed data reduces identity inconsistency.</li>
                <li>Approval flow reduces uncontrolled privileged action.</li>
            </ul>
            <p>For a buyer, these are not optional technical extras. These are governance controls that make the platform safer to trust.</p>
        </div>

        <div class="doc-section">
            <h2>11. How The Modules Interact End To End</h2>
            <p>The application becomes more valuable when you look at how modules support one another instead of viewing each screen in isolation.</p>
            <ul>
                <li><strong>New employee scenario:</strong> HRMS already contains business data. Operator uses employee code, retrieves live information, creates the account, and the action becomes visible through the activity model.</li>
                <li><strong>Locked account scenario:</strong> operator checks user state, unlocks or resets in one place, and the action is reflected in the platform's operational visibility.</li>
                <li><strong>Request scenario:</strong> a request enters the system, review happens in management, action is taken, and the result becomes part of audit and activity history.</li>
                <li><strong>Shared access scenario:</strong> the password vault prevents critical credentials from being scattered into insecure unofficial channels.</li>
            </ul>
            <p>This interaction model is what gives the product depth. It is not just feature volume. It is workflow continuity.</p>
        </div>

        <div class="doc-section">
            <h2>12. What Makes The Product Easier To Operate</h2>
            <ul>
                <li>one persistent quick-action layer across main working pages</li>
                <li>same-day visibility through recent activity and today's log views</li>
                <li>deeper analytics when the team needs more than one card</li>
                <li>HRMS-backed create and update flows that reduce duplicate work</li>
                <li>approval handling without leaving the system</li>
                <li>built-in password governance instead of side-channel storage</li>
            </ul>
            <p>Operationally, this lowers training effort. Teams can onboard new operators faster because the platform presents action, feedback, and reference in one controlled environment.</p>
        </div>

        <div class="doc-section">
            <h2>13. Technical Confidence For Delivery Teams</h2>
            <p>Buyer-facing polish only matters when the underlying platform can actually be maintained. The application now runs from a cleaner runtime structure that separates public entry, application logic, configuration, views, and operational scripts.</p>
            <ul>
                <li><strong>public root:</strong> <code>public/</code></li>
                <li><strong>canonical app entry:</strong> <code>public/index.php</code></li>
                <li><strong>canonical API gateway:</strong> <code>public/api/index.php</code></li>
                <li><strong>application shell:</strong> <code>app/Application/Http/admin_portal.php</code></li>
                <li><strong>domain logic:</strong> <code>app/Domain/</code></li>
                <li><strong>views:</strong> <code>resources/views/</code></li>
                <li><strong>frontend runtime assets:</strong> <code>public/resources/frontend/</code></li>
                <li><strong>PowerShell execution layer:</strong> <code>scripts/powershell/</code></li>
            </ul>
            <p>That gives implementation teams a far clearer maintenance story than a flat legacy structure would provide.</p>
        </div>

        <div class="doc-section">
            <h2>14. Why Organizations Would Want To Adopt It</h2>
            <p>Organizations usually look for a system like this when the cost of informal identity handling starts becoming visible. Onboarding takes too long. Password resets consume too much support time. Shared credentials become dangerous. HRMS and AD start drifting apart. Audit questions become painful to answer.</p>
            <p><?= $projectName ?> addresses those pain points with one connected workflow model. That is why it can be positioned not simply as an admin portal, but as an internal identity operations product with strong operational and governance value.</p>
        </div>

        <div class="doc-section">
            <h2>15. Current Runtime Model</h2>
            <p>Current live mode runs from the IIS public root at <code>public/</code>. The source-of-truth runtime path remains simple:</p>
            <ul>
                <li><strong>main app:</strong> <code>public/index.php</code></li>
                <li><strong>main API:</strong> <code>public/api/index.php</code></li>
                <li><strong>main shell:</strong> <code>app/Application/Http/admin_portal.php</code></li>
            </ul>
            <p>Compatibility files remain only where old URL survival still matters. Real business logic does not live there.</p>
        </div>

        <div class="doc-section">
            <h2>16. Current Code Layout</h2>
            <pre>
{ROOT}/
|- app/
|- App_Data/
|- bootstrap/
|- config/
|- logs/
|- public/
|- resources/
|- scripts/
|  \- powershell/
\- analysis/
            </pre>
            <p>Developer-facing truth files:</p>
            <ul>
                <li><code>analysis/current_codebase_blueprint.md</code></li>
                <li><code>analysis/project_blueprint/current_architecture.md</code></li>
                <li><code>analysis/migration/README.md</code></li>
            </ul>
        </div>

        <div class="doc-section">
            <h2>17. Security Notes</h2>
            <ul>
                <li>default password is config-driven</li>
                <li>encrypted password storage is enabled for vault entries</li>
                <li>core user stores are under secure or app-data-backed paths</li>
                <li>permissions are loaded through RBAC before sensitive actions</li>
                <li>notification, audit, and request flows are permission-aware</li>
            </ul>
        </div>

        <div class="doc-section">
            <h2>18. Operational Notes</h2>
            <ul>
                <li>public hosting root must remain <code>public/</code></li>
                <li>if old URLs must stay alive, compatibility files under <code>public/coreAdmin/</code> and <code>public/assets/dashboard/</code> must remain</li>
                <li>for structure reference, use <code>analysis/current_codebase_blueprint.md</code></li>
                <li>frontend browser assets are served from <code>public/resources/frontend/</code></li>
            </ul>
        </div>

        <div class="doc-section">
            <h2>19. Current Status</h2>
            <ul class="changelog-list">
                <li class="log-enhancement"><span class="log-tag">LIVE</span><strong>Current:</strong> public-root hosting active and signed off.</li>
                <li class="log-refactor"><span class="log-tag">ARCH</span><strong>Current:</strong> canonical code migrated to <code>app/</code>, <code>bootstrap/</code>, <code>config/</code>, and <code>resources/</code>.</li>
                <li class="log-docs"><span class="log-tag">DOCS</span><strong>Current:</strong> documentation now reflects current runtime and product behavior.</li>
            </ul>
        </div>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    const actionTakenTitleSpan = document.getElementById('actionTakenTitle');
    const actionTakenMessageDisplay = document.getElementById('actionTakenMessageDisplay');
    if (actionTakenCardContainer && actionTakenTitleSpan && actionTakenMessageDisplay) {
        const message = <?= json_encode($_SESSION['flash_message']) ?>;
        const isSuccess = <?= json_encode($_SESSION['flash_is_success']) ?>;
        actionTakenCardContainer.style.display = 'block';
        actionTakenTitleSpan.textContent = isSuccess ? 'Success' : 'Error';
        actionTakenMessageDisplay.innerHTML = message;
        actionTakenMessageDisplay.className = isSuccess ? 'alert alert-success' : 'alert alert-danger';
        setTimeout(() => {
            actionTakenCardContainer.style.display = 'none';
        }, 20000);
    }
});
</script>
<?php
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_is_success']);
else:
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionTakenCardContainer = document.getElementById('actionTakenCardContainer');
    if (actionTakenCardContainer) {
        actionTakenCardContainer.style.display = 'none';
    }
});
</script>
<?php
endif;
?>
