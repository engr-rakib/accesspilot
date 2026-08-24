<?php
/**
 * resources/views/pages/documentation/guide.php
 * 
 * THE DEFINITIVE A-TO-Z ARCHITECTURAL MASTER MANUAL
 * 
 * Features:
 * - Premium PowerPoint-Style Horizontal Decision Trees.
 * - Narrative "Story of an Identity" Technical Breakdown.
 * - Exhaustive coverage of every platform module.
 * - Precise 4rem application gutters & Ivory aesthetic.
 */
if (!defined('_CORE_ADMIN_')) {
    die('Direct access not permitted');
}
?>



<div class="container-fluid master-manual-wrapper slide-in-top">
    <div class="master-manual-card">
        
        <!-- THE MASTER HERO -->
        <header class="manual-hero-section">
            <div class="mb-4">
                <span class="badge bg-success text-uppercase px-4 py-2" style="letter-spacing: 4px; font-size: 0.8rem;">Official Technical Directive</span>
            </div>
            <h1>The Master Architectural Narrative</h1>
            <p>A comprehensive A-to-Z story detailing how AccessPilot automates identity lifecycles, enforces cryptographic security, and delivers sub-second Active Directory orchestration.</p>
        </header>

        <div class="manual-content">

            <!-- CHAPTER 1: THE PUBLIC GATEWAY & REQUEST LIFE -->
            <section class="chapter-box">
                <div class="chapter-title-row">
                    <div class="chapter-icon-circle"><i class="fas fa-users-cog"></i></div>
                    <h2 class="chapter-heading-text">01. The Identity Inception</h2>
                </div>
                
                <div class="story-body-container">
                    <p class="story-paragraph">The story begins outside the administrative vault, at the <strong>Request Portal</strong>. This is where your entire employee base interacts with the identity engine. Every user request—whether for a new account, a password reset, or an emergency unlock—follows a strictly governed technical path.</p>
                    <p class="story-paragraph">End-users are guided by a <strong>Security Awareness Ticker</strong> that communicates best practices in real-time. Once submitted, their request is not just a form; it is a data payload bound to a verified <strong>HRMS Employee ID</strong>, waiting for the "Digital Handshake" of an administrator.</p>
                </div>

                <!-- Horizontal Logic Tree: Request Path -->
                <div class="logic-tree-horizontal">
                    <div class="tree-node-premium node-border-blue">User Submits Access Request</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-orange">Verify HRMS ID Validity</div>
                    <div class="connector-line-h">
                        <span class="branch-label label-yes">Verified</span>
                    </div>
                    <div class="tree-node-premium node-border-dark">Identity Stored in secure vault</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-green">Broadcast Instant Notification</div>
                </div>

                <div class="feature-intelligence-grid">
                    <div class="intelligence-card">
                        <h5>Reactive Polling Stream</h5>
                        <p>The portal utilizes a 7-second high-frequency polling engine. When an administrator resolves a request, a personalized popup appears instantly for the user, communicating the outcome without a page reload.</p>
                    </div>
                    <div class="intelligence-card">
                        <h5>Forensic Request Tracking</h5>
                        <p>End-users can track their lifecycle progress using a technical audit trail. If a request is rejected, the system delivers the administrator's technical feedback directly to the user's view.</p>
                    </div>
                </div>
            </section>

            <!-- CHAPTER 2: THE SECURE GATEKEEPER (LOGIN & LICENSE) -->
            <section class="chapter-box">
                <div class="chapter-title-row">
                    <div class="chapter-icon-circle" style="background: #0f172a;"><i class="fas fa-user-shield"></i></div>
                    <h2 class="chapter-heading-text">02. The Security Perimeter</h2>
                </div>

                <div class="story-body-container">
                    <p class="story-paragraph">Entering the administrative vault requires more than just credentials. Every session is evaluated by the <strong>Licensing Handshake</strong>. AccessPilot ensures that the core deployment identity—the "Heart" of your system—is cryptographically bound to an authorized license.</p>
                    <p class="story-paragraph">If the <strong>AppName</strong> or <strong>Domain</strong> in the <span class="tech-pill-tag">deployment_identity.xml</span> is tampered with, the system triggers an immediate <strong>Global Service Lock</strong>. This prevents unauthorized AD mutations by forcing the entire portal into read-only mode.</p>
                </div>

                <!-- Horizontal Logic Tree: Login & Licensing -->
                <div class="logic-tree-horizontal">
                    <div class="tree-node-premium node-border-dark">Operator Login (Argon2ID)</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-blue">Evaluate RSA-2048 Signature</div>
                    <div class="connector-line-h">
                        <span class="branch-label label-no">Mismatch</span>
                    </div>
                    <div class="tree-node-premium node-border-red">Trigger Global Service Lock</div>
                    <div class="connector-line-h">
                        <span class="branch-label label-yes">Valid</span>
                    </div>
                    <div class="tree-node-premium node-border-green">Authorise Admin Write Access</div>
                </div>

                <div class="pro-architect-callout">
                    <h6>System Architect Insight: Restricted Mode</h6>
                    <p class="story-paragraph mb-0" style="color: #cbd5e1;">AccessPilot's restricted mode is a hard-lock at the API level. Even if the UI is bypassed, the underlying PowerShell runner will reject all write-commands if the cryptographic handshake is not established, ensuring your Active Directory is never compromised by unauthorized portal instances.</p>
                </div>
            </section>

            <!-- CHAPTER 3: AUTOMATED AD ORCHESTRATION -->
            <section class="chapter-box">
                <div class="chapter-title-row">
                    <div class="chapter-icon-circle" style="background: #10b981;"><i class="fas fa-microchip"></i></div>
                    <h2 class="chapter-heading-text">03. The Automation Engine</h2>
                </div>

                <div class="story-body-container">
                    <p class="story-paragraph">This is where AccessPilot demonstrates its technical authority. We have replaced manual, error-prone AD tasks with sub-second **PowerShell Orchestration**. Operations are executed through isolated runners that handle encoding protection and real-time telemetry.</p>
                    <p class="story-paragraph">The system specializes in **Atomic Actions**. For example, the <strong>Reset & Unlock</strong> routine is a single technical thread that resets a password, clears the <span class="tech-pill-tag">lockoutTime</span>, and reactivates the user state—all in one successful execution.</p>
                </div>

                <!-- Horizontal Logic Tree: AD Action Logic -->
                <div class="logic-tree-horizontal">
                    <div class="tree-node-premium node-border-green">Trigger: Instant Action</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-blue">Verify RBAC Permissions</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-dark">Initialise PS Isolated Runner</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-orange">Atomic Write to Domain Controller</div>
                </div>

                <div class="feature-intelligence-grid">
                    <div class="intelligence-card">
                        <h5>Intelligent User Modification</h5>
                        <p>Safely update account metadata. The engine preserves the <strong>OriginalSamAccountName</strong> in the audit vault while synchronizing new SAM IDs and Display Names across the forest instantly.</p>
                    </div>
                    <div class="intelligence-card">
                        <h5>Instant Enable/Disable</h5>
                        <p>Manage offboarding with 100% precision. The **Instant Disable** action modifies the <span class="tech-pill-tag">userAccountControl</span> attribute and force-propagates the change to the Primary Domain Controller.</p>
                    </div>
                </div>
            </section>

            <!-- CHAPTER 4: AUTONOMOUS HIERARCHY MANAGEMENT -->
            <section class="chapter-box">
                <div class="chapter-title-row">
                    <div class="chapter-icon-circle" style="background: #7c3aed;"><i class="fas fa-sitemap"></i></div>
                    <h2 class="chapter-heading-text">04. The Hierarchy Engine</h2>
                </div>

                <div class="story-body-container">
                    <p class="story-paragraph">AccessPilot features an <strong>Autonomous OU Management</strong> engine that eliminates manual container hunting. The system knows exactly where a user belongs based on their organizational metadata.</p>
                    <p class="story-paragraph">When creating a user—either via **HRMS Synchronization** or **Manual Provisioning**—the engine analyzes the employee's hierarchical data: <span class="tech-pill-tag">Operating Unit</span> > <span class="tech-pill-tag">Department</span> > <span class="tech-pill-tag">Section</span>. It then resolves or creates the entire AD path automatically.</p>
                </div>

                <!-- Horizontal Logic Tree: OU Provisioning -->
                <div class="logic-tree-horizontal">
                    <div class="tree-node-premium node-border-blue">New User provisioning</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-orange">Analyse HRMS Metadata</div>
                    <div class="connector-line-h">
                        <span class="branch-label label-no">OU Missing</span>
                    </div>
                    <div class="tree-node-premium node-border-red">Auto-Create Hierarchy</div>
                    <div class="connector-line-h"></div>
                    <div class="tree-node-premium node-border-green">Place User in Correct OU</div>
                </div>

                <div class="feature-intelligence-grid">
                    <div class="intelligence-card">
                        <h5>HRMS One-Click Sync</h5>
                        <p>Zero typing required. Provision accounts directly from employee database records. The system automatically handles name formatting, email binding, and hierarchical placement.</p>
                    </div>
                    <div class="intelligence-card">
                        <h5>Security Group Binding</h5>
                        <p>As the OU hierarchy is created, the engine automatically identifies and assigns the appropriate Security Groups to the user, ensuring immediate access to departmental resources.</p>
                    </div>
                </div>
            </section>

            <!-- CHAPTER 5: TRACEABILITY & SYSTEM HEALTH -->
            <section class="chapter-box">
                <div class="chapter-title-row">
                    <div class="chapter-icon-circle" style="background: #0369a1;"><i class="fas fa-heartbeat"></i></div>
                    <h2 class="chapter-heading-text">05. Total Operational Visibility</h2>
                </div>

                <div class="story-body-container">
                    <p class="story-paragraph">Transparency is the cornerstone of governance. AccessPilot provides two distinct layers of visibility: <strong>Forensic Traceability</strong> and <strong>Infrastructure Diagnostics</strong>.</p>
                    <p class="story-paragraph">Every administrative mutation is recorded in a secure **CSV Audit Trail** outside the public root. We capture the Operator, the Target, the full PowerShell output, and technical telemetry like Client IP and Execution Time.</p>
                </div>

                <div class="feature-intelligence-grid">
                    <div class="intelligence-card" style="background: #0f172a; border: none;">
                        <span class="tech-pill-tag" style="background: #10b981; color: #0f172a; border: none;">DIAGNOSTIC</span>
                        <h5 class="text-white">AD Health Reporting</h5>
                        <p style="color: #94a3b8;">Perform deep-scans of Domain Controller health, replication status, and DNS integrity. Generate forensic user exports for compliance audits with a single click.</p>
                    </div>
                    <div class="intelligence-card">
                        <span class="tech-pill-tag">ANALYTIC</span>
                        <h5>Today's Log Insights</h5>
                        <p>Interactive visual charts provide real-time analysis of system success rates. Identify trends in lockouts or registration requests at a glance from the master dashboard.</p>
                    </div>
                </div>
            </section>

        </div>

        <!-- THE AUTHORITATIVE CLOSER -->
        <footer class="bg-dark text-white p-5 text-center">
            <h2 class="fw-bold mb-4" style="font-family: 'Cinzel', serif; letter-spacing: 5px;">Technically Mature. Operationally Boundless.</h2>
            <p class="opacity-75 mb-5 mx-auto" style="max-width: 900px; font-size: 1.25rem;">AccessPilot is the definitive command center for enterprise Active Directory forests. Deploy once, automate forever.</p>
            <div class="d-flex justify-content-center gap-4 flex-wrap">
                <a href="<?= admin_page_url('license') ?>" class="btn btn-success btn-lg px-5 fw-bold shadow-lg">PURCHASE ENTERPRISE LICENSE</a>
                <a href="<?= admin_page_url('system_config') ?>" class="btn btn-outline-light btn-lg px-5 fw-bold">PROVISION NEW DOMAIN</a>
            </div>
            
            <div class="mt-5 pt-4 border-top border-secondary opacity-50">
                <p class="x-small text-uppercase fw-bold mb-0" style="letter-spacing: 3px;">System Architecture by Rakibuzzaman • Lead Developer & System Architect</p>
            </div>
        </footer>
    </div>
</div>

<script>
    // Smooth scroll for technical narrative navigation
    document.addEventListener('DOMContentLoaded', () => {
        // Any specific story-telling animations can be added here
    });
</script>
