<?php
/**
 * Reusable Card Component
 * 
 * @var string $card_id      (Optional) ID for the card element
 * @var string $card_classes (Optional) CSS classes for the card (e.g., 'slide-in-bottom')
 * @var string $card_content (Required) The inner HTML content of the card-body
 * @var bool   $no_padding   (Optional) Whether to use 'no-padding' on card-body (default: true)
 */
$no_padding = $no_padding ?? true;
?>
<div class="card <?php echo $card_classes ?? ''; ?>" <?php echo !empty($card_id) ? "id='$card_id'" : ''; ?> style="overflow: hidden !important;">
    <div class="card-body <?php echo $no_padding ? 'no-padding' : ''; ?>" style="padding: 0 !important; margin: 0 !important;">
        <?php echo $card_content; ?>
    </div>
</div>
