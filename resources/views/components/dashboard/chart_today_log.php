<?php
$todayLogChartCanvasId = isset($todayLogChartCanvasId) && $todayLogChartCanvasId
    ? $todayLogChartCanvasId
    : 'todayLogChart';
?>
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-chart-pie"></i> Today's Log
        </div>
        <span class="time-badge"></span>
    </div>
    <div class="card-body chart-container dashboard-today-log-body">
        <div class="dashboard-chart-legend-shell">
            <ul class="dashboard-chart-legend-list">
            </ul>
        </div>
        <canvas id="<?= htmlspecialchars($todayLogChartCanvasId, ENT_QUOTES, 'UTF-8') ?>"></canvas>
    </div>
</div>
