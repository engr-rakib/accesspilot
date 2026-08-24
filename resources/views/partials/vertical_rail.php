<?php
/**
 * resources/views/partials/vertical_rail.php
 * 
 * Nested 'Options' flyout rail with standard FontAwesome 5 Free icons.
 * Moves high-priority items back to main rail and adds Profile hub.
 */
$menu_items = function_exists('config_get')
    ? (config_get('menu', []) ?: [])
    : (include __DIR__ . '/../../../config/menu_config.php');

$current_page = $_GET['page'] ?? 'home';
if (!isset($_GET['page']) && strpos($_SERVER['REQUEST_URI'], 'index.php') !== false && empty($_GET)) {
    $current_page = 'home';
}

// Sub-menu items for the Options Hub
$flyout_names = ['License', 'Vendor License', 'Configuration', 'Identity & Access', 'User Guide', 'Documentation'];
$main_rail_items = [];
$flyout_items = [];

foreach ($menu_items as $item) {
    $name = trim($item['name']);
    if ($name === 'Sign Out' || $name === 'Home' || $name === 'Profile') continue;
    
    // Permission can be a single key or pipe-separated OR-list (e.g. 'page_monitoring|page_ad_administration')
    $perms = empty($item['permission']) ? [] : array_filter(array_map('trim', explode('|', $item['permission'])));
    $has_perm = empty($perms);
    if (!$has_perm) {
        foreach ($perms as $p) {
            if (function_exists('has_permission') && ($p === '*' || has_permission($p))) {
                $has_perm = true;
                break;
            }
        }
    }
    if (!$has_perm) continue;

    if ($name === 'Exchange') {
        $exConfig = function_exists('ldap_exchange_active_domain_config') ? ldap_exchange_active_domain_config() : ['enabled' => true];
        if (isset($exConfig['enabled']) && !$exConfig['enabled']) continue;
    }

    if (in_array($name, array_map('trim', $flyout_names))) {
        $flyout_items[] = $item;
    } else {
        $main_rail_items[] = $item;
    }
}
?>
<div class="rail-system-container">
    
    <!-- PANE 1: Primary Rail -->
    <aside class="main-rail">
        <div class="rail-top-group">
            <!-- Logo / Home -->
            <a href="<?= route_url('index.php') ?>" class="rail-item <?php echo ($current_page === 'home') ? 'active' : ''; ?>" data-noc-tip="Home">
                <i class="fas fa-home"></i>
            </a>

            <!-- Main Modules -->
            <?php foreach ($main_rail_items as $item): ?>
                <?php 
                    $item_page = '';
                    if (preg_match('/page=([^&]+)/', $item['url'], $matches)) {
                        $item_page = $matches[1];
                    }
                    if (!$item_page && strpos($item['url'], 'about') !== false) $item_page = 'about';
                    
                    $is_active = ($current_page === $item_page);

                    $icon = $item['icon'];
                    if ($item_page === 'dashboard') $icon = 'fa-tachometer-alt';
                    if ($item_page === 'user_activity') $icon = 'fa-history';
                    if ($item_page === 'user_management') $icon = 'fa-users-cog';
                    if ($item_page === 'monitoring') $icon = 'fa-satellite-dish';
                    if ($item_page === 'password_manager') $icon = 'fa-key';
                    if ($item_page === 'email_tools') $icon = 'fa-envelope-open-text';
                    if ($item_page === 'exchange') $icon = 'fa-exchange-alt';
                    if ($item_page === 'about') $icon = 'fa-info-circle';
                ?>
                <a href="<?= $baseURL . $item['url'] ?>" class="rail-item <?php echo $is_active ? 'active' : ''; ?>" data-noc-tip="<?= htmlspecialchars(trim($item['name'])) ?>">
                    <i class="fas <?= $icon ?>"></i>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Spacer -->
        <div class="rail-spacer" style="flex-grow: 1;"></div>

        <!-- Bottom Actions -->
        <div class="rail-bottom">
            <!-- Options Hub Trigger -->
            <div class="rail-item" id="railOptionsTrigger" data-noc-tip="Options">
                <i class="fas fa-th-large"></i>
            </div>

            <!-- Profile Hub -->
            <?php 
                $sessionAvatar = $_SESSION['avatar'] ?? '';
                $railAvatarUrl = $baseURL . $app_config['app_info']['logo_path'];
                if (!empty($sessionAvatar)) {
                    $railAvatarUrl = $baseURL . '/api/index.php?endpoint=get_avatar&file=' . urlencode($sessionAvatar);
                }
            ?>
            <a href="<?= $baseURL ?>/index.php?page=profile" class="rail-item <?php echo ($current_page === 'profile') ? 'active' : ''; ?>" id="railProfileBtn" data-noc-tip="My Profile">
                 <!-- EFFECT: avatar border transition | Purpose: smooth border color change on hover -->
                 <img src="<?= $railAvatarUrl ?>" alt="Profile" style="width: 28px; height: 28px; border-radius: 50%; border: 2px solid transparent; transition: border-color 0.2s;">
            </a>

            <!-- Fast Logout -->
            <a href="<?= route_url('logout.php') ?>" class="rail-item logout-rail-btn" data-noc-tip="Sign Out">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </aside>

    <!-- PANE 2: Options Flyout Rail -->
    <aside class="child-rail-flyout" id="optionsFlyoutRail">
        
        <div class="flyout-items-wrapper">
            <?php usort($flyout_items, function($a, $b) {
                $order = ['License' => 0, 'Vendor License' => 1, 'Configuration' => 2, 'Identity & Access' => 3, 'User Guide' => 4, 'Documentation' => 5];
                $aIdx = $order[trim($a['name'])] ?? 99;
                $bIdx = $order[trim($b['name'])] ?? 99;
                return $aIdx <=> $bIdx;
            }); ?>
            <?php foreach ($flyout_items as $item): ?>
                <?php 
                    $item_page = '';
                    if (preg_match('/page=([^&]+)/', $item['url'], $matches)) {
                        $item_page = $matches[1];
                    }
                    $is_active = ($current_page === $item_page);
                    $icon = $item['icon'];
                    
                    if ($item_page === 'license') $icon = 'fa-certificate';
                    if ($item_page === 'system_config') $icon = 'fa-sliders-h';
                    if ($item_page === 'identity_access') $icon = 'fa-id-card';
                    if ($item_page === 'documentation' || $item_page === 'documentation_guide') $icon = 'fa-book';
                ?>
                <a href="<?= $baseURL . $item['url'] ?>" class="rail-item <?php echo $is_active ? 'active' : ''; ?>" data-noc-tip="<?= htmlspecialchars(trim($item['name'])) ?>">
                    <i class="fas <?= $icon ?>"></i>
                </a>
            <?php endforeach; ?>
        </div>
        

    </aside>
</div>

<!-- UI: rail flyout toggle | Purpose: show/hide child navigation flyout on hover -->
<script>
(function() {
    const trigger = document.getElementById('railOptionsTrigger');
    const flyout = document.getElementById('optionsFlyoutRail');
    let hideTimeout;

    const show = () => {
        clearTimeout(hideTimeout);
        flyout.classList.add('is-visible');
        trigger.classList.add('active');
    };

    const hide = () => {
        hideTimeout = setTimeout(() => {
            if (!flyout.matches(':hover') && !trigger.matches(':hover')) {
                flyout.classList.remove('is-visible');
                trigger.classList.remove('active');
            }
        }, 300);
    };

    if (trigger && flyout) {
        trigger.addEventListener('mouseenter', show);
        trigger.addEventListener('mouseleave', hide);
        flyout.addEventListener('mouseenter', () => clearTimeout(hideTimeout));
        flyout.addEventListener('mouseleave', hide);

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            const visible = flyout.classList.toggle('is-visible');
            trigger.classList.toggle('active', visible);
        });
    }
})();
</script>
