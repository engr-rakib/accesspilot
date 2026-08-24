<?php
if (!isset($projectName) || $projectName === '') {
    $projectName = $app_config['app_info']['name'] ?? $app_config['domain_name'] ?? 'AccessPilot';
}
?>

<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-info-circle me-1"></i>About <?= $projectName ?></span>
                </h3>
                <div class="p-3 content-body">
                    <p>Welcome to <strong><?= $projectName ?></strong> — the nerve center of your digital infrastructure. Born from the battlefield of endless IT operations, this portal isn't just a tool; it's your command console. Every click is a mission, every automated task a victory against chaos. From managing thousands of identities to watching the pulse of your network in real-time, <strong><?= $projectName ?></strong> stands as the silent guardian of your server ecosystem. Built by engineers who lived the pain, crafted for those who demand control without complexity.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-tag me-1"></i>Current Version & Update</span>
                </h3>
                <div class="p-3 content-body">
                    <p><strong>Version:</strong> <?= $currentVersion ?? '1.0.0' ?> — <strong>Last Updated:</strong> <?= $updateDate ?? 'N/A' ?></p>
                    <p>Every update sharpens the blade. This release delivers faster response times, fortified defenses, and a cockpit that feels like an extension of your instincts. We don't just fix bugs — we eliminate friction. Every feature is forged from real battlefield feedback, honed by the demands of production environments where downtime is not an option.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-users me-1"></i>Meet the Team</span>
                </h3>
                <div class="p-3 content-body">
                    <div class="team-member">
                        <div class="team-member-photo">
                            <img src="<?= $baseURL ?>/assets/images/team/rakibuzzaman.jpg?v=<?= $app_config['app_info']['version'] ?>" alt="Rakibuzzaman" class="team-member-img">
                            <i class="fas fa-user-tie team-member-icon"></i>
                        </div>
                        <div class="team-member-details">
                            <strong>Rakibuzzaman (66684)</strong>
                            <span>Lead Developer & System Architect</span>
                            <span>Responsible for core development, system design, and integration.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-star me-1"></i>Key Features & Enhancements</span>
                </h3>
                <div class="p-3 content-body">
                    <div class="row g-2">
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-user-shield me-1"></i>Identity Command Center</div><div class="feature-desc">One-click user provisioning, modification, and de-provisioning across your entire Active Directory. Full RBAC with granular permission mapping.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-heartbeat me-1"></i>Live Infrastructure Radar</div><div class="feature-desc">Real-time heartbeat monitoring with latency tracking, packet analysis, and instant alerting. Every node every second.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-bolt me-1"></i>Automation Engine</div><div class="feature-desc">Schedule and execute complex administrative workflows with a single trigger. From password resets to bulk operations — let the machine do the heavy lifting.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-bell me-1"></i>Sentinel Alert System</div><div class="feature-desc">Multi-channel notification engine that never sleeps. Get pinged on Slack, email, or in-app the moment something needs your attention.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-chart-bar me-1"></i>Mission Reports</div><div class="feature-desc">Generate detailed audit trails, activity logs, and infrastructure health reports. Know exactly what happened, when, and by whom.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-shield-alt me-1"></i>Zero-Trust Security</div><div class="feature-desc">License-enforced access control, encrypted credential vault, and session monitoring. Your infrastructure deserves nothing less than fortress-grade protection.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-sync-alt me-1"></i>HRMS Battle Sync</div><div class="feature-desc">Real-time synchronization with your HR systems. New joiners get access automatically, leavers are disabled instantly — no manual entry required.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-rocket me-1"></i>Lightning Interface</div><div class="feature-desc">SPA-powered, theme-ready, and responsive across all devices. Every page load is instant, every action feels like a reflex.</div></div></div></div>
                        <div class="col-md-4"><div class="card feature-card h-100"><div class="card-body p-2"><div class="feature-title mb-1"><i class="fas fa-database me-1"></i>Employee Intelligence Hub</div><div class="feature-desc">Standalone employee management module with CRUD operations, auto-ID generation, and RESTful API access. Your HR data, fully weaponized.</div></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-book me-1"></i>Documentation & Upgrades</span>
                </h3>
                <div class="p-3 content-body">
                    <p>Every warrior needs a field manual. Dive into the complete <a href="?page=documentation" class="fw-bold">Documentation</a> — your tactical guide to mastering <?= $projectName ?>. From quick-start deployments to advanced operations, everything you need to command your infrastructure is just a click away.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row gx-0 mb-0">
    <div class="col-12">
        <div class="card app-table-card">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-bullseye me-1"></i>Our Mission</span>
                </h3>
                <div class="p-3 content-body">
                    <p>To arm every IT warrior with weapons-grade infrastructure tools — reliable, intuitive, and brutally efficient. We exist to turn complex operations into single commands, to transform reactive firefighting into proactive command, and to make server management feel less like a battle and more like a symphony.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if (isset($_SESSION['flash_message'])):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var c=document.getElementById('actionTakenCardContainer');
    if(c){c.style.display='block';document.getElementById('actionTakenTitle').textContent='<?= $_SESSION['flash_is_success'] ? 'Success' : 'Error' ?>';document.getElementById('actionTakenMessageDisplay').innerHTML='<?= addslashes($_SESSION['flash_message']) ?>';setTimeout(function(){c.style.display='none';},20000);}
});
</script>
<?php
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_is_success']);
else:
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var c=document.getElementById('actionTakenCardContainer');
    if(c){c.style.display='none';}
});
</script>
<?php
endif;
?>
