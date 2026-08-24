<?php
/**
 * resources/views/components/global/action_taken_card.php
 * 
 * Standardized Operational Result Ribbon using the unified UI Card component.
 */

// Default values for the card
$actionTaken = $actionTaken ?? null;
$actionTakenIcon = $actionTakenIcon ?? (!empty($message) ? 'fas fa-info-circle' : '');
$actionTakenContainerStyle = $actionTakenContainerStyle ?? '';
$actionTakenAnimation = $actionTakenAnimation ?? 'slide-in-bottom';
$isVisible = !empty($message) ? 'visible' : '';

// 1. Prepare the inner content using the same professional pattern as Log/Info cards
ob_start();
?>
<div id="actionTakenCardContent" style="padding: 0 !important; margin: 0 !important; width: 100% !important;">
    <div class="log-title-wrapper app-table-title" style="margin: 0 !important; border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;">
        <h6 class="card-title mb-0" style="display: flex; align-items: center; color: var(--primary-color, #183593) !important;">
            <i id="actionTakenIcon" class="<?php echo $actionTakenIcon ?: 'fas fa-info-circle'; ?> me-2"></i>
            <span id="actionTakenTitle"><?php echo htmlspecialchars($actionTaken ?: 'Operational Result'); ?></span>
        </h6>
        <div class="d-flex align-items-center gap-1">
            <button class="btn btn-sm text-muted p-1" onclick="copyMessageToClipboard(this)" data-noc-tip="Copy Result">
                <i class="fas fa-copy"></i>
            </button>
            <button class="btn btn-sm text-muted p-1" onclick="document.getElementById('actionTakenCardContainer').classList.remove('visible')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <div class="copy-content" style="display: none;">
        <?php echo htmlspecialchars($message ?? ''); ?>
    </div>
    
    <div style="padding: 0.5rem 0.75rem;">
        <div id="actionTakenMessageDisplay" class="alert alert-info mb-0" style="border-radius: 8px; font-size: var(--font-feedback, 0.85rem) !important; color: #334155 !important;">
            <?php echo !empty($message) ? str_replace("\n", "<br>", htmlspecialchars($message)) : ''; ?>
        </div>
    </div>
</div>
<?php
$card_content = ob_get_clean();

// 2. Wrap in standardized UI Card
$card_id = 'actionTakenCardContainer';
$card_classes = $isVisible . ' ' . $actionTakenAnimation . ' overflow-visible-card';
include include_path('resources/views/components/global/ui_card.php');

// Cleanup
unset($card_content, $card_id, $card_classes, $isVisible, $actionTakenAnimation, $actionTakenTitle);
?>
