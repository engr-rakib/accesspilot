<div class="row" style="display: flex !important; flex-wrap: wrap !important; margin: 0; padding: 0;">
    <?php if (has_permission('card_server_info')): ?>
    <div class="col-md-6" style="flex: 0 0 50% !important; width: 50% !important; max-width: 50% !important; box-sizing: border-box !important; padding: 3px !important;">
        <?php
        // Prepare the Server Info content
        ob_start();
        ?>
        <div class="output-section" id="serverUserInfoDisplay" style="padding: 0 !important; margin: 0 !important; width: 100% !important;">
            <h3 class="card-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                <span><i class="fas fa-server me-2"></i>Server Information</span>
            </h3>
            <div style="padding: 0.5rem 0.75rem;">
                <div class="alert alert-info info-card-title mb-0" style="font-size: var(--shell-font-body, 0.85rem) !important;">
                    <i class="fas fa-info-circle me-2"></i> Provide user id to view information
                </div>
            </div>
        </div>
        <?php
        $card_content = ob_get_clean();
        $card_classes = 'h-100';
        include include_path('resources/views/components/global/ui_card.php');
        ?>
    </div>
    <?php endif; ?>

    <?php if (has_permission('card_employee_info')): ?>
    <div class="col-md-6" style="flex: 0 0 50% !important; width: 50% !important; max-width: 50% !important; box-sizing: border-box !important; padding: 3px !important;">
        <?php
        // Prepare the Employee Info content
        ob_start();
        ?>
        <div class="output-section" id="employeeInfoDisplay" style="padding: 0 !important; margin: 0 !important; width: 100% !important;">
            <h3 class="card-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
                <span><i class="fas fa-user me-2"></i>Employee Information</span>
            </h3>
            <div style="padding: 0.5rem 0.75rem;">
                <div class="alert alert-info info-card-title mb-0" style="font-size: var(--shell-font-body, 0.85rem) !important;">
                    <i class="fas fa-info-circle me-2"></i> Provide user id to view information
                </div>
            </div>
        </div>
        <?php
        $card_content = ob_get_clean();
        $card_classes = 'h-100';
        include include_path('resources/views/components/global/ui_card.php');

        // Cleanup
        unset($card_content, $card_classes);
        ?>
    </div>
    <?php endif; ?>
</div>
