<!-- Options Hub (Offcanvas Menu) -->
<?php $current_request_uri = $_SERVER['REQUEST_URI'] ?? ''; ?>
<div class="offcanvas offcanvas-end offcanvas-menu-card" tabindex="-1" id="offcanvasMenu" aria-labelledby="offcanvasMenuLabel" style="width: 320px; border-left: 1px solid #d1d7db; background-color: #ffffff !important; color: #111b21 !important;">
    <div class="offcanvas-header border-bottom py-3" style="background-color: #f0f2f5;">
        <div>
            <h5 class="offcanvas-title fw-bold" id="offcanvasMenuLabel" style="color: #183593;">Options Hub</h5>
            <div class="small text-muted">System configuration & resources</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <!-- 1. Identity & Profile Section -->
        <div class="p-3 border-bottom bg-light-subtle">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="avatar-placeholder bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px; font-size: 1.2rem; font-weight: 700;">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                </div>
                <div>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></div>
                    <div class="x-small text-muted">System Authority</div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <a href="<?= $baseURL ?>/index.php?page=change_password" class="btn btn-outline-primary btn-sm text-start" data-spa-link="true">
                    <i class="fas fa-user-lock me-2"></i> Change Password
                </a>
            </div>
        </div>

        <!-- 2. Core Operational Tools -->
        <div class="options-group p-2">
            <div class="px-2 py-2 small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">System Management</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item border-0 p-0">
                    <a href="<?= $baseURL ?>/index.php?page=license" class="options-link d-flex align-items-center p-2 rounded" data-spa-link="true">
                        <i class="fas fa-certificate fa-fw me-3 text-warning"></i>
                        <span>License Status</span>
                    </a>
                </li>
                <li class="list-group-item border-0 p-0">
                    <a href="<?= $baseURL ?>/index.php?page=system_config" class="options-link d-flex align-items-center p-2 rounded" data-spa-link="true">
                        <i class="fas fa-sliders-h fa-fw me-3 text-info"></i>
                        <span>Global Configuration</span>
                    </a>
                </li>
                <li class="list-group-item border-0 p-0">
                    <a href="<?= $baseURL ?>/index.php?page=password_manager" class="options-link d-flex align-items-center p-2 rounded" data-spa-link="true">
                        <i class="fas fa-key fa-fw me-3 text-success"></i>
                        <span>Credentials Manager</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- 3. Help & Resources -->
        <div class="options-group p-2 border-top">
            <div class="px-2 py-2 small fw-bold text-muted text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Resources</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item border-0 p-0">
                    <a href="<?= $baseURL ?>/index.php?page=documentation_guide" class="options-link d-flex align-items-center p-2 rounded" data-spa-link="true">
                        <i class="fas fa-book-open fa-fw me-3 text-primary"></i>
                        <span>User Guide</span>
                    </a>
                </li>
                <li class="list-group-item border-0 p-0">
                    <a href="<?= $baseURL ?>/index.php?page=documentation" class="options-link d-flex align-items-center p-2 rounded" data-spa-link="true">
                        <i class="fas fa-terminal fa-fw me-3 text-secondary"></i>
                        <span>Technical Docs</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- 4. Account Actions -->
        <div class="p-3 border-top mt-auto">
            <a href="<?= route_url('logout.php') ?>" class="btn btn-danger w-100 shadow-sm">
                <i class="fas fa-sign-out-alt me-2"></i> Sign Out
            </a>
        </div>
    </div>
</div>


