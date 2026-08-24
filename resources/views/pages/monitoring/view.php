<?php if (!defined('_CORE_ADMIN_')) die('Direct access not permitted'); ?>

<div class="row gx-0 mb-0 slide-in-top">
    <div class="col-12">
        <div class="noc-tabs-bar">
            <button class="noc-tab-item active" data-tab="system"><i class="fas fa-heartbeat"></i>System Monitor</button>
            <button class="noc-tab-item" data-tab="monitoring"><i class="fas fa-satellite-dish"></i>Infrastructure Hub</button>
            <button class="noc-tab-item" data-tab="intelligence"><i class="fas fa-brain"></i>Network Operations</button>
        </div>
    </div>

    <!-- ============ TAB: Infrastructure Hub ============ -->
    <div id="tab-monitoring" class="col-12 noc-tab-content" style="display:none;">
        <div class="noc-summary">
            <div class="noc-summary-item sm-up"><i class="fas fa-check-circle"></i><span class="num" id="nocTotalUp">0</span> Up</div>
            <div class="noc-summary-item sm-down"><i class="fas fa-exclamation-circle"></i><span class="num" id="nocTotalDown">0</span> Down</div>
            <div class="noc-summary-item sm-warn"><i class="fas fa-exclamation-triangle"></i><span class="num" id="nocTotalWarn">0</span> Warning</div>
            <div class="noc-summary-item sm-total"><i class="fas fa-server"></i><span class="num" id="nocTotalAll">0</span> Total</div>
        </div>

        <!-- ====== MULTI-NODE RTT TIMELINE (top) ====== -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-project-diagram me-1"></i>Multi-Node RTT Timeline</span>
                    <div class="d-flex align-items-center gap-1">
                        <select id="rttTimeRange" class="noc-filter-control" style="width:auto;">
                            <option value="15">15 min</option>
                            <option value="60">1 hour</option>
                            <option value="360">6 hours</option>
                            <option value="1440">24 hours</option>
                        </select>
                        <button class="btn btn-sm" id="btnRttPause" style="font-size:0.7rem;height:32px;line-height:1;display:inline-flex;align-items:center;padding:0 10px;border-radius:6px;user-select:text;"><i class="fas fa-pause"></i></button>
                        <button class="btn btn-sm" id="btnRttExport" style="font-size:0.7rem;height:32px;line-height:1;display:inline-flex;align-items:center;padding:0 10px;border-radius:6px;user-select:text;"><i class="fas fa-download"></i></button>
                    </div>
                </h3>
                <div class="p-2">
                    <div id="rttTimelineContainer" style="height:240px;width:100%;position:relative;">
                        <canvas id="multiNodeChart" style="width:100%;height:100%;display:block;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====== MONITORING GRID ====== -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-microchip me-1"></i>Industrial Heartbeat Stream <span class="badge bg-primary rounded-pill align-middle" style="font-size:0.55rem;font-weight:600;letter-spacing:0.3px;vertical-align:middle;">10s telemetry</span></span>
                    <span id="logCountBadge" class="badge bg-primary rounded-pill" style="font-size:0.65rem;">0</span>
                    <div class="d-flex gap-1">
                        <input type="text" id="nocSearchFilter" class="noc-filter-control" placeholder="Filter by name / IP / OS ..." style="width:140px;">
                        <select id="nocStatusFilter" class="noc-filter-control" style="width:auto;">
                            <option value="all">All</option>
                            <option value="up">Up</option>
                            <option value="down">Down</option>
                            <option value="warning">Warning</option>
                        </select>
                        <button class="btn btn-primary" id="btnRunSweep" title="Immediately ping ALL monitored nodes (forced refresh)" style="font-size:0.7rem;height:32px;line-height:1;display:inline-flex;align-items:center;padding:0 10px;border-radius:6px;user-select:text;"><i class="fas fa-sync-alt me-1"></i>Sweep</button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNodeModal" style="font-size:0.7rem;height:32px;line-height:1;display:inline-flex;align-items:center;padding:0 10px;border-radius:6px;user-select:text;"><i class="fas fa-plus-circle me-1"></i>Add</button>
                    </div>
                </h3>
                <div style="overflow-x:auto;">
                    <table class="table app-data-table log-table mb-0">
                        <thead><tr><th style="width:30px">St</th><th>Node</th><th>Hostname</th><th>Owner</th><th style="width:50px">RTT</th><th style="width:40px">Loss</th><th style="width:50px">Health</th><th style="width:75px">Since</th><th style="width:30px" class="text-end">&#9881;</th></tr></thead>
                        <tbody id="nodeListBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ====== FOCUS AREA (deep analysis for selected node) ====== -->
        <div class="row gx-0">
            <div class="col-12">
                <div id="focusPlaceholder">
                    <div class="card app-dashed-placeholder" style="height:100%;">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                            <i class="fas fa-crosshairs fs-3 text-muted opacity-25 mb-1"></i>
                            <h6 class="text-muted fw-bold mb-0" style="font-size:0.78rem;">Select a node for deep analysis</h6>
                            <p class="text-muted mb-0" style="font-size:0.7rem;">Click any row in the monitoring grid</p>
                        </div>
                    </div>
                </div>
                <div id="focusArea" class="app-hidden">
                    <div class="card" style="overflow:hidden !important;margin-bottom:10px !important;">
                        <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                            <h3 class="log-title-wrapper app-table-title">
                                <span><i class="fas fa-chart-line me-1"></i>Performance: <span id="focusIp" class="font-tech"></span></span>
                                <span id="focusStatusBadge" class="badge" style="font-size:0.65rem;">LOADING</span>
                            </h3>
                            <div class="p-2">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-2"><div class="perf-stat-box"><div class="lbl">Avg Latency</div><div class="val text-primary font-tech" id="focusAvg">0ms</div></div></div>
                                    <div class="col-md-2"><div class="perf-stat-box"><div class="lbl">Packet Loss</div><div class="val text-danger font-tech" id="focusLoss">0%</div></div></div>
                                    <div class="col-md-2"><div class="perf-stat-box"><div class="lbl">Jitter</div><div class="val text-warning font-tech" id="focusJitter">0ms</div></div></div>
                                    <div class="col-md-2"><div class="perf-stat-box"><div class="lbl">Uptime</div><div class="val text-success font-tech" id="focusUptime">0%</div></div></div>
                                    <div class="col-md-2"><div class="perf-stat-box"><div class="lbl">Health</div><div class="val font-tech" id="focusHealth">--</div></div></div>
                                    <div class="col-md-2"><div class="perf-stat-box"><div class="lbl">Samples</div><div class="val text-info font-tech" id="focusSamples">0</div></div></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6"><div class="stream-canvas-wrap" style="height:160px;"><canvas id="mainStreamChart" style="width:100%;height:100%;"></canvas></div></div>
                                    <div class="col-md-6"><div class="stream-canvas-wrap" style="height:160px;"><canvas id="secondaryStreamChart" style="width:100%;height:100%;"></canvas></div></div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6"><div class="text-uppercase fw-bold text-muted mb-1" style="font-size:0.65rem;"><i class="fas fa-clock me-1"></i>Recent RTT</div><div class="app-terminal-panel font-tech" id="focusRecentRtt" style="min-height:50px;max-height:50px;position:relative;">Collecting...<i class="fas fa-info-circle text-muted" data-noc-tip="Shows latest RTT values for the focused node. Each line = one ping sample. Lower = better. Spikes indicate congestion or routing changes." style="position:absolute;top:2px;right:4px;font-size:0.6rem;cursor:help;opacity:0.4;"></i></div></div>
                                    <div class="col-md-6"><div class="text-uppercase fw-bold text-muted mb-1" style="font-size:0.65rem;"><i class="fas fa-route me-1"></i>Trace Route</div><div class="app-terminal-panel font-tech" id="focusTrace" style="min-height:50px;max-height:50px;position:relative;">Scanning...<i class="fas fa-info-circle text-muted" data-noc-tip="Auto-trace to focused node. Each line = one hop with IP + latency. Hops beyond target may indicate routing loops or asymmetric path." style="position:absolute;top:2px;right:4px;font-size:0.6rem;cursor:help;opacity:0.4;"></i></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="nodeSummaryContainer"></div>
                </div>
            </div>
        </div>

        <!-- ====== EVENT LOGS ====== -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-history me-1"></i>Event Logs</span>
                    <div class="d-flex align-items-center gap-1">
                        <select id="logNodeFilter" class="noc-filter-control"><option value="">All Nodes</option></select>
                        <input type="date" id="logDateFilter" class="noc-filter-control" style="width:auto;">
                        <input type="time" id="logTimeFrom" class="noc-filter-control" style="width:80px;" value="00:00">
                        <input type="time" id="logTimeTo" class="noc-filter-control" style="width:80px;" value="23:59">
                        <button class="btn btn-primary" id="btnLoadLogs" style="height:32px;line-height:1;display:inline-flex;align-items:center;padding:0 14px;border-radius:6px;font-size:0.7rem;user-select:text;">Load</button>
                        <button id="btnExportLogs" class="btn btn-sm" style="height:32px;line-height:1;display:inline-flex;align-items:center;padding:0 10px;border:1px solid rgba(255,255,255,0.15);background:transparent;color:var(--text-muted);border-radius:6px;font-size:0.65rem;user-select:text;"><i class="fas fa-download me-1"></i>Export</button>
                    </div>
                </h3>
                <div id="logEntriesContainer" style="max-height:220px;overflow-y:auto;padding:3px 8px;">
                    <div class="text-center py-3 opacity-50 small" style="font-size:0.7rem;">Loading today's logs...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Node Modal -->
    <div class="modal fade" id="addNodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content" style="border-radius:12px;background:var(--card-bg,#1e293b);border:1px solid rgba(255,255,255,0.08);">
                <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.06);padding:10px 14px;">
                    <h6 class="modal-title fw-bold" style="font-size:0.8rem;"><i class="fas fa-plus-circle me-1 text-success"></i>Add Node</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size:0.7rem;"></button>
                </div>
                <div class="modal-body p-3">
                    <form id="addNodeForm">
                        <div class="mb-2">
                            <label style="font-size:0.7rem;color:var(--text-muted);">IP Address</label>
                            <input type="text" id="nodeIpInput" class="form-control form-control-sm" placeholder="e.g. 10.0.0.1" style="font-size:0.7rem;font-family:monospace;">
                        </div>
                        <div class="mb-2">
                            <label style="font-size:0.7rem;color:var(--text-muted);">Owner (optional)</label>
                            <input type="text" id="nodeOwnerInput" class="form-control form-control-sm" placeholder="Name or team" style="font-size:0.7rem;">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100" style="font-size:0.75rem;padding:6px;border-radius:6px;"><i class="fas fa-plus me-1"></i>Add Node</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: System Monitor                                          -->
    <!-- ============================================================ -->
    <div id="tab-system" class="col-12 noc-tab-content">
        <!-- SECTION 1: APPLICATION -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #f59e0b;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-chart-pie me-1"></i>APPLICATION USAGE <span class="badge bg-warning text-dark font-tech" style="font-size:0.65rem;">Today</span></span>
                    <span class="font-tech text-muted" id="appMetricsRefresh" style="font-size:0.7rem;">loading...</span>
                </h3>
                <div class="p-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="font-tech fw-bold" id="appMetricOps" style="font-size:var(--font-xl);color:#3b82f6;">--</div>
                        <div style="font-size:var(--font-xs);">Total Actions</div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="font-tech fw-bold" id="appMetricLogins" style="font-size:var(--font-xl);color:#22c55e;">--</div>
                        <div style="font-size:var(--font-xs);">Logins</div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="font-tech fw-bold" id="appMetricFailures" style="font-size:var(--font-xl);color:#ef4444;">--</div>
                        <div style="font-size:var(--font-xs);">Failures</div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="font-tech fw-bold" id="appMetricRate" style="font-size:var(--font-xl);color:#f59e0b;">--</div>
                        <div style="font-size:var(--font-xs);">Success%</div>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="font-tech fw-bold" id="appMetricUsers" style="font-size:var(--font-xl);color:#8b5cf6;">--</div>
                        <div style="font-size:var(--font-xs);">Active Users</div>
                    </div>
                    <div class="col-md-2 text-center" style="display:none;">
                        <div class="font-tech fw-bold" id="appMetricRate" style="font-size:0;"></div>
                        <div style="font-size:var(--font-xs);"></div>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <div style="font-size:var(--font-xs);">Top Actions</div>
                        <div id="appTopActions">
                            <div class="py-1">Loading...</div>
                        </div>
                    </div>
                    <div class="col-12 mt-1">
                        <div style="font-size:var(--font-xs);">Activity by Hour</div>
                        <div id="appHourlyGrid" class="d-flex flex-wrap gap-1" style="font-size:var(--font-xs);">
                            <div class="py-2">Collecting...</div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: CONTAINER -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #3b82f6;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fab fa-docker me-1"></i>CONTAINER</span>
                    <span style="display:flex;align-items:center;gap:6px;font-size:0.65rem;">
                        <span class="font-tech" id="appContainerId" style="color:#3b82f6;">--</span>
                        <span class="font-tech" id="appContainerImage" style="color:#8b5cf6;">--</span>
                        <span class="font-tech" id="appContainerName" style="color:#22c55e;">--</span>
                        <span class="font-tech" style="color:#22c55e;font-weight:600;" id="appContainerStatus2">--</span>
                    </span>
                </h3>
                <div class="p-2">
                <!-- ROW 1: Container resources info bar + full-width FPM chart -->
                <div class="row g-1 mb-1" style="margin:0 -4px;">
                    <div class="col-12" style="padding:0 4px;">
                        <div style="display:flex;gap:4px;margin-bottom:4px;">
                            <div id="containerResourceCards" style="display:flex;gap:4px;flex:2;min-width:0;"></div>
                            <div style="flex:1;min-width:140px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:4px;padding:3px 5px;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-gears text-info" style="font-size:10px;"></i>
                                <span style="font-size:9px;font-weight:600;white-space:nowrap;">FPM WORKERS</span>
                                <span class="font-tech" id="appFpmWorkers" style="font-size:9px;color:#06b6d4;">--</span>
                            </div>
                        </div>
                        <div style="height:130px;overflow:visible;">
                            <canvas id="fpmWorkerChart" style="width:100%;height:100%;display:block;"></canvas>
                        </div>
                    </div>
                </div>
                <!-- ROW 2: Volumes (L) + CPU/MEM gauges bigger (R) -->
                <div class="row g-1 mb-1" style="margin:0 -4px;">
                    <div class="col-md-6" style="padding:0 4px;">
                        <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">VOLUMES</div>
                        <div id="appVolTable" style="font-size:var(--font-xs);max-height:72px;overflow-y:auto;scrollbar-width:none;"></div>
                        <div style="margin-top:4px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:var(--font-xs);">
                                <span>DISK USAGE</span>
                                <span class="font-tech" style="font-size:var(--font-sm);font-weight:600;" id="dockerDiskPct">--</span>
                            </div>
                            <div style="height:6px;background:rgba(255,255,255,0.06);border-radius:3px;margin-top:2px;">
                                <div id="dockerDiskBar" style="height:100%;width:0%;border-radius:3px;transition:width 0.5s;"></div>
                            </div>
                            <div class="d-flex justify-content-between" style="margin-top:3px;font-size:var(--font-xs);">
                                <span class="font-tech" id="dockerDiskUsed" style="color:#ef4444;">--</span>
                                <span class="font-tech" id="dockerDiskFree" style="color:#22c55e;">--</span>
                                <span class="font-tech" id="dockerDiskTotal" style="color:var(--text-muted);">--</span>
                            </div>
                        </div>

                    </div>
                    <div class="col-md-6" style="padding:0 4px;">
                        <div style="display:flex;gap:4px;align-items:center;justify-content:center;">
                            <div style="flex:1;text-align:center;">
                                <div style="font-size:var(--font-sm);font-weight:600;margin-bottom:2px;">CPU</div>
                                <canvas id="dockerCpuGauge" style="width:100px;height:100px;display:inline-block;"></canvas>
                                <div style="font-size:var(--font-base);font-weight:600;color:var(--text-muted);margin-top:2px;" class="font-tech" id="appCpuUsage">--</div>
                            </div>
                            <div style="flex:1;text-align:center;">
                                <div style="font-size:var(--font-sm);font-weight:600;margin-bottom:2px;">MEMORY</div>
                                <canvas id="dockerMemGauge" style="width:100px;height:100px;display:inline-block;"></canvas>
                                <div style="font-size:var(--font-base);font-weight:600;color:var(--text-muted);margin-top:2px;" class="font-tech" id="appMemUsage">--</div>
                            </div>
                            <div style="flex:1;text-align:center;">
                                <div style="font-size:var(--font-sm);font-weight:600;margin-bottom:2px;">NETWORK</div>
                                <canvas id="dockerNetGauge" style="width:100px;height:100px;display:inline-block;"></canvas>
                                <div style="font-size:var(--font-base);font-weight:600;color:var(--text-muted);margin-top:2px;" class="font-tech" id="dockerNetCurrent">--</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ROW 3: Trend charts full width -->
                <div style="border-top:1px solid var(--border-color);padding-top:2px;">
                    <div class="d-flex align-items-center gap-1" style="margin-bottom:2px;">
                        <span style="font-size:var(--font-xs);font-weight:600;">TREND</span>
                        <div class="d-flex gap-1" id="containerTrendRange">
                            <button class="range-btn active" data-minutes="30">30m</button>
                            <button class="range-btn" data-minutes="180">3h</button>
                            <button class="range-btn" data-minutes="1440">24h</button>
                            <button class="range-btn" data-minutes="4320">3d</button>
                            <button class="range-btn" data-minutes="10080">1w</button>
                        </div>
                        <button id="containerTrendExport" class="btn btn-sm btn-icon app-hidden" data-noc-tip="Export CSV"><i class="fas fa-download"></i></button>
                    </div>
                    <div style="font-size:var(--font-xs);margin-bottom:1px;">CPU / MEM / NET</div>
                    <div style="height:180px;overflow:visible;"><canvas id="dockerCpuTrend" style="width:100%;height:100%;display:block;"></canvas></div>
                </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 3: SYSTEM INFRASTRUCTURE                             -->
        <!-- ============================================================ -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #8b2eb8;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-server me-1"></i>SYSTEM INFRASTRUCTURE</span>
                    <span>
                        <span id="sysHealthDot2" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6b7280;"></span>
                        <span class="font-tech" id="sysHealthScore" style="font-size:0.7rem;">--</span>
                        <span id="sysHealthLabel" style="font-size:0.65rem;color:var(--text-muted);margin-left:4px;"></span>
                        <span style="font-size:0.65rem;color:var(--text-muted);margin-left:8px;">Uptime</span>
                        <span class="font-tech" id="sysUptime" style="font-size:0.65rem;">--</span>
                    </span>
                </h3>
                <div class="p-2">
                <!-- Info bar: Kernel, Host, OS, Procs -->
                <div style="display:flex;flex-wrap:wrap;gap:6px 16px;font-size:0.6rem;margin-bottom:6px;padding:4px 6px;background:rgba(0,0,0,0.02);border-radius:4px;">
                    <span><span style="color:#6b7280;">Kernel</span> <span class="font-tech" id="sysKernel" style="color:#111;">--</span></span>
                    <span><span style="color:#6b7280;">Host</span> <span class="font-tech" id="sysHostname" style="color:#111;">--</span></span>
                    <span><span style="color:#6b7280;">OS</span> <span class="font-tech" id="sysOs" style="color:#111;">--</span></span>
                    <span><span style="color:#6b7280;">Procs</span> <span class="font-tech" id="sysProcs" style="color:#111;">--</span></span>
                    <span onclick="loadAll()" style="cursor:pointer;color:#6b7280;"><i class="fas fa-sync" style="font-size:8px;"></i> <span class="font-tech" id="sysLastRefreshed" style="color:#111;">--</span></span>
                </div>
                <!-- Gauges row -->
                <div class="row g-1 mb-1">
                    <div class="col-4 col-md-4 text-center">
                        <div style="font-size:var(--font-xs);">CPU</div>
                        <div style="width:100%;display:flex;justify-content:center;"><canvas id="sysCpuGauge" style="width:120px;height:120px;"></canvas></div>
                        <div class="font-tech" id="sysCpuTab" style="font-size:var(--font-sm);color:#8b2eb8;">--</div>
                        <div id="cpuLoad" style="font-size:var(--font-xs);">--</div>
                    </div>
                    <div class="col-4 col-md-4 text-center">
                        <div style="font-size:var(--font-xs);">MEMORY</div>
                        <div style="width:100%;display:flex;justify-content:center;"><canvas id="sysMemGauge" style="width:120px;height:120px;"></canvas></div>
                        <div class="font-tech" id="sysMemTab" style="font-size:var(--font-sm);color:#22c55e;">--</div>
                        <div id="memDetail" style="font-size:var(--font-xs);">--</div>
                        <div id="sysSwap" style="font-size:var(--font-xs);">--</div>
                    </div>
                    <div class="col-4 col-md-4 text-center" id="sysDiskGaugesColumn">
                        <div style="font-size:var(--font-xs);">DISK</div>
                        <div id="sysDiskGaugesContainer" style="display:flex;flex-wrap:wrap;justify-content:center;gap:3px;margin-top:2px;"></div>
                        <div class="font-tech" id="sysDiskTab" style="font-size:var(--font-sm);color:#3b82f6;">--</div>
                        <div id="diskDetail" style="font-size:var(--font-xs);">--</div>
                        <div id="diskMnt" style="font-size:var(--font-xs);">--</div>
                    </div>
                </div>

                <!-- Trend Charts -->
                <div class="d-flex align-items-center gap-1" style="margin-bottom:4px;">
                    <span style="font-size:var(--font-xs);font-weight:600;">TREND</span>
                    <div class="d-flex gap-1" id="systemTrendRange">
                        <button class="range-btn active" data-minutes="30">30m</button>
                        <button class="range-btn" data-minutes="180">3h</button>
                        <button class="range-btn" data-minutes="1440">24h</button>
                        <button class="range-btn" data-minutes="4320">3d</button>
                        <button class="range-btn" data-minutes="10080">1w</button>
                    </div>
                    <button id="systemTrendExport" class="btn btn-sm btn-icon app-hidden" data-noc-tip="Export CSV"><i class="fas fa-download"></i></button>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <div class="card app-table-card h-100">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:var(--font-xs);font-weight:600;"><i class="fas fa-microchip me-1 text-info"></i>CPU TREND <span class="font-tech" id="cpuTrendCurrent" style="color:#a855f7;font-weight:700;">--</span></span>
                                </div>
                                <div style="height:180px;overflow:visible;"><canvas id="cpuTrendChart" style="width:100%;height:100%;display:block;"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card app-table-card h-100">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:var(--font-xs);font-weight:600;"><i class="fas fa-network-wired me-1 text-cyan"></i>NETWORK TREND <span class="font-tech" id="netTrendCurrent" style="color:#06b6d4;font-weight:700;">--</span></span>
                                </div>
                                <div style="height:160px;overflow:visible;"><canvas id="netTrendChart" style="width:100%;height:100%;display:block;"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card app-table-card h-100">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:var(--font-xs);font-weight:600;"><i class="fas fa-database me-1 text-success"></i>DISK I/O <span class="font-tech" id="diskTrendCurrent" style="color:#22c55e;font-weight:700;">--</span></span>
                                </div>
                                <div style="height:160px;overflow:visible;"><canvas id="diskTrendChart" style="width:100%;height:100%;display:block;"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card app-table-card h-100">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:var(--font-xs);font-weight:600;"><i class="fas fa-chart-pie me-1 text-primary"></i>DISK USAGE</span>
                                </div>
                                <div style="height:160px;overflow:visible;"><canvas id="diskUsageTrendChart" style="width:100%;height:100%;display:block;"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card app-table-card h-100">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size:var(--font-xs);font-weight:600;"><i class="fas fa-hdd me-1 text-warning"></i>PHYSICAL DISKS & LVM</span>
                                </div>
                                <div style="height:200px;"><div id="diskTreeContent" style="font-size:var(--font-xs);">Loading...</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 4: ADVANCED ANALYTICS                                -->
        <!-- ============================================================ -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #f59e0b;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-chart-line me-1"></i>ADVANCED ANALYTICS</span>
                    <span class="font-tech text-muted" id="advAnalyticsRefresh" style="font-size:0.65rem;">--</span>
                </h3>
                <div class="p-2">
                    <div class="row g-1 mb-2">
                        <div class="col-md-6 col-lg-3">
                            <div class="sys-metric"><div class="metric-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;"><i class="fas fa-tachometer-alt"></i></div><div><div style="font-size:0.8rem;font-weight:700;" class="font-tech" id="advSysLoad">--</div><div style="font-size:0.65rem;color:var(--text-muted);">SYS LOAD</div></div></div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="sys-metric"><div class="metric-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;"><i class="fas fa-tasks"></i></div><div><div style="font-size:0.8rem;font-weight:700;" class="font-tech" id="advProcesses">--</div><div style="font-size:0.65rem;color:var(--text-muted);">PROCESSES</div></div></div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="sys-metric"><div class="metric-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;"><i class="fas fa-plug"></i></div><div><div style="font-size:0.8rem;font-weight:700;" class="font-tech" id="advConnections">--</div><div style="font-size:0.65rem;color:var(--text-muted);">CONNECTIONS</div></div></div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="sys-metric"><div class="metric-icon" style="background:rgba(139,46,184,0.15);color:#8b2eb8;"><i class="fas fa-clock"></i></div><div><div style="font-size:0.8rem;font-weight:700;" class="font-tech" id="advCtxSwitches">--</div><div style="font-size:0.65rem;color:var(--text-muted);">CTX SWITCHES</div></div></div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="card app-table-card h-100">
                                <div class="card-body p-2">
                                    <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">CPU / MEMORY BAR</div>
                                    <div style="height:160px;"><canvas id="advCpuMemBar" style="width:100%;height:100%;display:block;"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card app-table-card h-100">
                                <div class="card-body p-2">
                                    <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">NETWORK TRAFFIC</div>
                                    <div style="height:160px;"><canvas id="advNetPie" style="width:100%;height:100%;display:block;"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card app-table-card h-100">
                                <div class="card-body p-2">
                                    <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">DISK USAGE TREEMAP</div>
                                    <div style="height:160px;"><canvas id="advDiskTreemap" style="width:100%;height:100%;display:block;"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card app-table-card h-100">
                                <div class="card-body p-2">
                                    <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">SYSTEM RADAR</div>
                                    <div style="height:220px;"><canvas id="advRadar" style="width:100%;height:100%;display:block;"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card app-table-card h-100">
                                <div class="card-body p-2">
                                    <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">ACTIVITY HEATMAP</div>
                                    <div style="height:160px;"><canvas id="advHeatmap" style="width:100%;height:100%;display:block;"></canvas></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card app-table-card h-100">
                                <div class="card-body p-2">
                                    <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">CPU / MEMORY BUBBLE</div>
                                    <div style="height:160px;"><canvas id="advBubble" style="width:100%;height:100%;display:block;"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 5: SYSTEM DETAILS                                    -->
        <!-- ============================================================ -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #22c55e;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-info-circle me-1"></i>SYSTEM DETAILS</span>
                    <span id="netGatewayInfo" style="font-size:var(--font-xs);color:var(--text-muted);"></span>
                </h3>
                <div class="p-2">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">NETWORK INTERFACES</div>
                            <div style="max-height:160px;overflow-y:auto;">
                                <table class="table table-sm mb-0" style="font-size:var(--font-xs);">
                                    <thead><tr><th>Interface</th><th>RX</th><th>TX</th><th>RX Rate</th><th>TX Rate</th></tr></thead>
                                    <tbody id="netTable"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">TCP CONNECTIONS</div>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;font-size:var(--font-xs);">
                                <div class="p-1" style="background:rgba(59,130,246,0.08);border-radius:4px;flex:1;min-width:60px;text-align:center;">
                                    <div class="font-tech fw-bold text-info" id="tcpEstablished">--</div>
                                    <div style="font-size:9px;color:var(--text-muted);">Established</div>
                                </div>
                                <div class="p-1" style="background:rgba(245,158,11,0.08);border-radius:4px;flex:1;min-width:60px;text-align:center;">
                                    <div class="font-tech fw-bold text-warning" id="tcpTimeWait">--</div>
                                    <div style="font-size:9px;color:var(--text-muted);">Time Wait</div>
                                </div>
                                <div class="p-1" style="background:rgba(239,68,68,0.08);border-radius:4px;flex:1;min-width:60px;text-align:center;">
                                    <div class="font-tech fw-bold text-danger" id="tcpCloseWait">--</div>
                                    <div style="font-size:9px;color:var(--text-muted);">Close Wait</div>
                                </div>
                                <div class="p-1" style="background:rgba(139,46,184,0.08);border-radius:4px;flex:1;min-width:60px;text-align:center;">
                                    <div class="font-tech fw-bold text-purple" id="tcpFinWait">--</div>
                                    <div style="font-size:9px;color:var(--text-muted);">Fin Wait</div>
                                </div>
                                <div class="p-1" style="background:rgba(6,182,212,0.08);border-radius:4px;flex:1;min-width:60px;text-align:center;">
                                    <div class="font-tech fw-bold text-cyan" id="tcpSynSent">--</div>
                                    <div style="font-size:9px;color:var(--text-muted);">SYN Sent</div>
                                </div>
                                <div class="p-1" style="background:rgba(34,197,94,0.08);border-radius:4px;flex:1;min-width:80px;text-align:center;">
                                    <div class="font-tech fw-bold text-success" id="sysTcpTotal">--</div>
                                    <div style="font-size:9px;color:var(--text-muted);">Total</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">DISK I/O</div>
                            <div id="diskIoBody" style="font-size:var(--font-xs);max-height:120px;overflow-y:auto;"></div>
                            <div style="font-size:var(--font-xs);margin-top:2px;">Total: <span class="font-tech" id="sysDiskIoTotal">--</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 6: TOP PROCESSES                                     -->
        <!-- ============================================================ -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #a855f7;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-list me-1"></i>TOP PROCESSES</span>
                    <div class="d-flex gap-1">
                        <button id="btnTopCpu" class="btn btn-sm" style="font-size:var(--font-xs);padding:2px 10px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:var(--text-color);border-radius:4px;cursor:pointer;">CPU</button>
                        <button id="btnTopMem" class="btn btn-sm active-sort" style="font-size:var(--font-xs);padding:2px 10px;border:1px solid rgba(255,255,255,0.1);background:transparent;color:var(--text-color);border-radius:4px;cursor:pointer;">MEM</button>
                    </div>
                </h3>
                <div class="p-2">
                    <div style="max-height:200px;overflow-y:auto;">
                        <table class="table table-sm mb-0" style="font-size:var(--font-xs);">
                            <thead><tr><th>PID</th><th>Command</th><th>CPU</th><th>Memory</th></tr></thead>
                            <tbody id="procTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- SECTION 7: STORAGE DETAILS                                   -->
        <!-- ============================================================ -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #f97316;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span><i class="fas fa-hdd me-1"></i>STORAGE DETAILS</span>
                    <span id="lvmInfoText" style="font-size:var(--font-xs);color:var(--text-muted);"></span>
                </h3>
                <div class="p-2">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">MOUNT POINTS</div>
                            <div style="max-height:160px;overflow-y:auto;">
                                <table class="table table-sm mb-0" style="font-size:var(--font-xs);">
                                    <thead><tr><th>FS</th><th>Type</th><th>Size</th><th>Used</th><th>Avail</th><th>Use%</th><th>Mount</th></tr></thead>
                                    <tbody id="diskMountTable"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="font-size:var(--font-xs);font-weight:600;margin-bottom:2px;">BLOCK DEVICES</div>
                            <div style="max-height:160px;overflow-y:auto;">
                                <table class="table table-sm mb-0" style="font-size:var(--font-xs);">
                                    <thead><tr><th>Name</th><th>Size</th><th>Type</th><th>FS</th><th>Mount</th></tr></thead>
                                    <tbody id="diskBlockTable"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB: Network Operations                                       -->
    <!-- ============================================================ -->
    <div id="tab-intelligence" class="col-12 noc-tab-content" style="display:none;">
        <div class="row g-2" style="margin-bottom:10px !important;">
            <div class="col-md-6">
                <div class="card app-table-card h-100" style="overflow:hidden !important;border-left:3px solid #8b2eb8;">
                    <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                        <h3 class="log-title-wrapper app-table-title">
                            <span><i class="fas fa-network-wired me-1"></i>CIDR</span>
                            <div class="d-flex align-items-center gap-1">
                                <button id="btnCalculateNet" class="btn btn-sm" style="background:linear-gradient(135deg,#8b2eb8,#6d28d9);color:#fff;font-weight:600;height:36px!important;border:1px solid rgba(139,46,184,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;font-size:var(--font-xs);white-space:nowrap;"><i class="fas fa-calculator me-1"></i>Calculate</button>
                                <button id="btnScanNet" class="btn btn-sm" style="background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-weight:600;height:36px!important;border:1px solid rgba(34,197,94,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;font-size:var(--font-xs);white-space:nowrap;"><i class="fas fa-search me-1"></i>Scan</button>
                                <button id="btnCancelScan" class="btn btn-sm d-none" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;height:36px!important;border:1px solid rgba(239,68,68,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;font-size:var(--font-xs);white-space:nowrap;"><i class="fas fa-stop me-1"></i>Stop</button>
                            </div>
                        </h3>
                        <div class="p-2">
                            <input type="text" id="cidrInput" class="form-control" placeholder="e.g. 192.168.1.0/24" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(139,46,184,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;margin-bottom:8px;">
                            <div id="netIntelSummary">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                    <div class="p-1" style="background:rgba(139,46,184,0.05);border-radius:6px;">
                                        <div style="font-size:var(--font-sm);color:var(--text-muted);">Network</div>
                                        <div class="font-tech fw-bold" id="valNet" style="font-size:var(--font-base);color:#8b2eb8;">--</div>
                                    </div>
                                    <div class="p-1" style="background:rgba(139,46,184,0.05);border-radius:6px;">
                                        <div style="font-size:var(--font-sm);color:var(--text-muted);">Netmask</div>
                                        <div class="font-tech fw-bold" id="valMask" style="font-size:var(--font-base);">--</div>
                                    </div>
                                    <div class="p-1" style="background:rgba(139,46,184,0.05);border-radius:6px;">
                                        <div style="font-size:var(--font-sm);color:var(--text-muted);">Broadcast</div>
                                        <div class="font-tech fw-bold" id="valBc" style="font-size:var(--font-base);color:#06b6d4;">--</div>
                                    </div>
                                    <div class="p-1" style="background:rgba(139,46,184,0.05);border-radius:6px;">
                                        <div style="font-size:var(--font-sm);color:var(--text-muted);">Gateway</div>
                                        <div class="font-tech fw-bold" id="valGw" style="font-size:var(--font-base);color:#22c55e;">--</div>
                                    </div>
                                    <div class="p-1" style="background:rgba(139,46,184,0.05);border-radius:6px;grid-column:1/-1;">
                                        <div style="font-size:var(--font-sm);color:var(--text-muted);">CIDR</div>
                                        <div class="font-tech fw-bold" id="valCidr" style="font-size:var(--font-base);color:#f59e0b;">--</div>
                                    </div>
                                </div>
                                <div style="font-size:var(--font-sm);color:var(--text-muted);text-align:center;margin-top:8px;"><i class="fas fa-info-circle me-1"></i>Enter a CIDR and click Calculate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card app-table-card h-100" style="overflow:hidden !important;border-left:3px solid #22c55e;">
                    <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                        <h3 class="log-title-wrapper app-table-title">
                            <span><i class="fas fa-list me-1"></i>SCAN RESULTS <span id="scanCount" style="font-size:var(--font-xs);color:var(--text-muted);font-weight:400;"></span></span>
                            <div class="d-flex align-items-center gap-1">
                                <button id="btnExportScan" class="btn btn-sm app-hidden" style="background:transparent;color:var(--text-muted);height:28px;border:1px solid rgba(255,255,255,0.15);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 8px;user-select:text;font-size:var(--font-xs);"><i class="fas fa-download me-1"></i>Export</button>
                            </div>
                        </h3>
                        <div class="p-2">
                            <div id="scanIndicator" style="display:none;text-align:center;padding:6px;color:var(--text-muted);font-size:var(--font-xs);background:rgba(34,197,94,0.06);border-radius:6px;margin-bottom:6px;">
                                <i class="fas fa-circle-notch fa-spin me-1" style="color:#22c55e;font-size:10px;"></i>Scanning network...
                            </div>
                            <div style="max-height:220px;overflow-y:auto;">
                                <table class="table table-sm mb-0" style="font-size:var(--font-xs);">
                                    <thead><tr><th>IP</th><th>Status</th><th>Latency</th><th>DNS</th><th>Info</th></tr></thead>
                                    <tbody id="scanResultBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Network Tools Section -->
        <div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;border-left:3px solid #06b6d4;">
            <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
                <h3 class="log-title-wrapper app-table-title">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <div style="width:24px;height:24px;border-radius:6px;background:linear-gradient(135deg,#06b6d4,#0891b2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;"><i class="fas fa-tools"></i></div>
                        NETWORK TOOLS
                    </span>
                </h3>
                <div class="p-2">
                    <!-- Mode Toggle -->
                    <div style="display:flex;gap:8px;margin-bottom:10px;background:rgba(6,182,212,0.04);padding:4px;border-radius:8px;width:fit-content;">
                        <button class="noc-toggle-btn active" data-mode="ping" style="font-size:var(--font-xs);padding:6px 18px;border:none;border-radius:6px;cursor:pointer;font-weight:600;transition:all 0.2s;">Ping & Port</button>
                        <button class="noc-toggle-btn" data-mode="dns" style="font-size:var(--font-xs);padding:6px 18px;border:none;border-radius:6px;cursor:pointer;font-weight:600;transition:all 0.2s;">DNS & WHOIS</button>
                    </div>

                    <!-- Ping Mode -->
                    <div id="pingModeSection">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="card app-table-card h-100" style="border-top:2px solid #06b6d4;">
                                    <div class="card-body p-2">
                                        <div style="font-size:var(--font-sm);font-weight:600;color:#06b6d4;margin-bottom:6px;"><i class="fas fa-wifi me-1"></i>MANUAL PING</div>
                                        <div class="d-flex gap-1 mb-1">
                                            <input type="text" id="manualPingIp" class="form-control" placeholder="IP or hostname" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(6,182,212,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;flex:1;min-width:0;">
                                            <button id="btnManualPing" class="btn btn-sm" style="background:#06b6d4;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(6,182,212,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">Ping</button>
                                            <button id="btnStopPing" class="btn btn-sm d-none" style="background:#ef4444;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(239,68,68,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">Stop</button>
                                        </div>
                                        <div id="pingLiveResult" style="font-size:var(--font-xs);max-height:100px;overflow-y:auto;font-family:monospace;min-height:24px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card app-table-card h-100" style="border-top:2px solid #f59e0b;">
                                    <div class="card-body p-2">
                                        <div style="font-size:var(--font-sm);font-weight:600;color:#f59e0b;margin-bottom:6px;"><i class="fas fa-plug me-1"></i>PORT CHECK</div>
                                        <div class="d-flex gap-1 mb-1">
                                            <input type="text" id="portCheckIp" class="form-control" placeholder="IP" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(245,158,11,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;flex:1;min-width:0;">
                                            <input type="text" id="portCheckPort" class="form-control" placeholder="Port (opt)" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(245,158,11,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;max-width:80px;">
                                            <button id="btnPortCheck" class="btn btn-sm" style="background:#f59e0b;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(245,158,11,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">Check</button>
                                        </div>
                                        <div id="portResult" style="font-size:var(--font-xs);font-family:monospace;min-height:24px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card app-table-card h-100" style="border-top:2px solid #3b82f6;">
                                    <div class="card-body p-2">
                                        <div style="font-size:var(--font-sm);font-weight:600;color:#3b82f6;margin-bottom:6px;"><i class="fas fa-route me-1"></i>TRACEROUTE / MTR</div>
                                        <div class="d-flex gap-1 mb-1">
                                            <input type="text" id="routeTarget" class="form-control" placeholder="IP or hostname" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(59,130,246,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;flex:1;min-width:0;">
                                            <button id="btnTraceroute" class="btn btn-sm" style="background:#3b82f6;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(59,130,246,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">Trace</button>
                                            <button id="btnMtrReport" class="btn btn-sm" style="background:#8b2eb8;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(139,46,184,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">MTR</button>
                                        </div>
                                        <div id="routeResult" style="font-size:var(--font-xs);max-height:100px;overflow-y:auto;font-family:monospace;min-height:24px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DNS Mode -->
                    <div id="dnsModeSection" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="card app-table-card h-100" style="border-top:2px solid #22c55e;">
                                    <div class="card-body p-2">
                                        <div style="font-size:var(--font-sm);font-weight:600;color:#22c55e;margin-bottom:6px;"><i class="fas fa-globe me-1"></i>DNS LOOKUP</div>
                                        <div class="d-flex gap-1 mb-1">
                                            <input type="text" id="dnsLookupInput" class="form-control" placeholder="Hostname or IP" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(34,197,94,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;flex:1;min-width:0;">
                                            <input type="text" id="dnsServerInput" class="form-control" placeholder="DNS server (optional)" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(34,197,94,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;max-width:140px;">
                                            <button id="btnDnsLookup" class="btn btn-sm" style="background:#22c55e;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(34,197,94,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">Lookup</button>
                                        </div>
                                        <div id="dnsResult" style="font-size:var(--font-xs);max-height:180px;overflow-y:auto;font-family:monospace;min-height:24px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card app-table-card h-100" style="border-top:2px solid #a855f7;">
                                    <div class="card-body p-2">
                                        <div style="font-size:var(--font-sm);font-weight:600;color:#a855f7;margin-bottom:6px;"><i class="fas fa-search me-1"></i>WHOIS LOOKUP</div>
                                        <div class="d-flex gap-1 mb-1">
                                            <input type="text" id="whoisInput" class="form-control" placeholder="IP or domain" style="font-family:monospace;font-size:var(--font-xs);height:36px!important;border:1px solid rgba(168,85,247,0.25);border-radius:6px;padding-top:4px!important;padding-bottom:4px!important;line-height:1!important;flex:1;min-width:0;">
                                            <button id="btnWhoisLookup" class="btn btn-sm" style="background:#a855f7;color:#fff;font-size:var(--font-xs);font-weight:600;height:36px!important;border:1px solid rgba(168,85,247,0.3);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;line-height:1;padding:0 14px;user-select:text;white-space:nowrap;">Lookup</button>
                                        </div>
                                        <div id="whoisResult" style="font-size:var(--font-xs);max-height:180px;overflow-y:auto;font-family:monospace;min-height:24px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.loadAll=function(){console.log('[monitoring] Manual refresh triggered');};
</script>
