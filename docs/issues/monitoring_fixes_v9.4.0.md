# Monitoring Fixes — v9.5.4

## Issues Identified & Fixed

### 0. 24/7 Monitoring, 0ms RTT Visualization, Card Reorder
- **24/7 tracking**: Removed stale ping limit (was 2 per refresh cycle) — all stale nodes pinged every sweep
- **History expanded**: PHP history 50→200, JS rH buffer 500→300→1000→500
- **0ms RTT in jitter**: Removed `rv>0` filter — valid sub-1ms RTT values now included in jitter calculation
- **Up/down chart coloring**: Single-node line chart points colored green (up) / red (down); line turns red if any down points
- **Card reorder**: Infrastructure Hub now: Summary → Grid → Focus Area → RTT Timeline → Event Logs
- **JS version**: 9.5.4_

## Issues Identified & Fixed (v9.5.3)

### Monthly Availability Calendar

### 1. RTT 0 in Monitoring Grid
- **Root Cause**: PHP regex `/time[=<](\d+)ms/` only captured integer part. Sub-1ms RTT (e.g. `time=0.345ms`) gave `$ttl = 0`.
- **Fix**: Changed regex to `/time[=<]([\d.]+)ms/`, parse as float, round to 1 decimal. `monitor_service.php:129`
- **Frontend**: Added `rttLabel()` helper — values `< 1` display as `<1ms` instead of `0ms`.

### 2. Charts Show Nothing When RTT is 0
- **Root Cause**: Chart data included `0` values, flattening lines to zero axis. When ALL values were 0, chart appeared empty.
- **Fix**: Convert `0` RTT to `null` in datasets (line chart: `v>0?v:null`). Chart skips `null` points. Bar chart keeps `0` for consistency. Added `spanGaps: false` to avoid connecting across nulls.
- **Result**: Charts only plot when actual RTT data exists; zero-RTT gaps show as breaks.

### 3. Performance Charts Don't Match Grid / Each Other
- **Root Cause**: `mainStreamChart` used `s.history` (stale server JSON data, max 50 pts, discrete ping times). `secondaryStreamChart` used `rH[ai]` (runtime accumulated data, 10s interval). Grid used `s.avg_ttl` (latest value from server).
- **Fix**: Both charts now use `rH[ai]` runtime data (same source). Grid shows latest `s.avg_ttl` via `rttLabel()` with `<1ms` support.
- **Consistency**: All three views now draw from the same data stream.

### 4. Uptime Calculation Wrong (-27m)
- **Root Cause**: Uptime was calculated from the first history entry's time-of-day (today's HH:MM), not from when the node was first monitored. For a node assigned yesterday at 15:00, it showed ~15h instead of the real uptime.
- **Fix**: 
  - Added `assigned_at` field (timestamp of node creation) in `monitor_upsert_server()`
  - Uptime now computed from `assigned_at` → now, minus total downtime seconds
  - Fallback to first history entry time if `assigned_at` missing (legacy nodes)
  - Added `downtime_history` array tracking each down→up transition with timestamps and duration

### 5. No Downtime Tracking
- **Root Cause**: System tracked current status but had no history of outages.
- **Fix**: Added `downtime_history[]` in `monitor_ping_server()`:
  - On transition `up→down`: records `{down_at, up_at: null, duration_seconds: 0}`
  - On transition `down→up`: fills `up_at` and computes `duration_seconds`
- **Display**: Focus area shows recent outages with timestamps and durations.

### 6. Event Logs Not User-Readable
- **Root Cause**: Log entries were plain text with no visual hierarchy, status colors, or context.
- **Fix** (per entry):
  - Color-coded left border (green = UP, red = DOWN)
  - Status icon (`fa-check-circle` / `fa-times-circle`)
  - Bold colored status label
  - Shows: time, node IP, DNS name, RTT (with `<1ms` support), loss %
  - Grouped/filtered by node, date, time range

### 7. No Hourly/Daily/Monthly Summary
- **Root Cause**: No backend endpoint computed availability summaries.
- **Fix**: Added `monitor_get_node_summary()` PHP function + `get_node_summary` API action:
  - **Hourly**: 24-block grid showing availability % per hour (green/yellow/red)
  - **Daily**: Table of last 7 days with up/down counts and availability %
  - **Uptime**: Total % since `assigned_at`
  - **Downtimes**: Count of outages + recent outage timestamps

### 8. Node Filter Resets on Heartbeat
- **Root Cause**: `populateNodeFilter()` was called every 10s in `hb()`. The code saved/restored the selected value, but `loadLogs()` was only called once (`_ll` guard) so auto-refresh never re-queried.
- **Fix**: Removed `_ll` one-shot guard. Added 30s `logTimer` interval that auto-refreshes logs with the currently selected filter. Immediate `loadLogs()` on page init.

### 9. `0 || undefined` Falsy Trap (RTT 0 → `--` in Recent RTT)
- **Root Cause**: `h5[ri].rtt || h5[ri].ttl` — JavaScript `||` treats `0` as falsy. When `rtt === 0`, it falls through to `h5[ri].ttl` (undefined), displaying `--`.
- **Fix**: All `x||y` fallbacks replaced with explicit `x !== undefined ? x : y`. Applied in `updateFocus()` (jitter calc, Recent RTT), `renderLine()`, `renderSecondaryChart()`, `renderMultiChart()`.

### 10. Refresh Response Never Updates `rH`
- **Root Cause**: POST `refresh` returns servers with fresh RTT values (from actual ping), but the callback only updated `sS` (for grid). `rH` retained stale `avg_ttl: 0` entries, so charts showed nothing.
- **Fix**: Callback now updates the last `rH` entry's `rtt` and `status` for each server (matched by timestamp `ts` from closure). Then re-renders `renderMultiChart()` and `updateFocus()`.

### 11. Log Directory Permission Denied
- **Root Cause**: `/data/logs/monitoring/` owned by `root`, but PHP-FPM runs as `www-data`. `monitor_log_technical_event()` couldn't write log files, so `get_logs` returned empty and `get_node_summary` showed 0% availability.
- **Fix**: `chown www-data:www-data -R /data/logs/monitoring`

## Files Changed (v9.5.3) — Monthly Availability Calendar
- `monitor_service.php` — extended daily loop to 365 days, added `monthly` aggregation with per-day availability within each month bucket
- `monitoring_actions.js` — added 4th card in `renderNodeSummary()`: color-coded monthly calendar heatmap (GitHub-contribution style) with month rows, day cells, and legend
- `pages.css` — added `.monthly-grid`, `.month-row`, `.month-label`, `.month-cells`, `.day-cell`, `.month-pct` styles
- `page_registry.php` — bumped JS version to `9.5.3_`

## Files Changed (v9.5.2)

| File | Changes |
|------|---------|
| `monitor_service.php` | RTT float parsing, `assigned_at`, `downtime_history`, `monitor_get_node_summary()` |
| `monitoring.php` | Added `get_node_summary` action |
| `monitoring_actions.js` | Fixed falsy `0||x` bug everywhere, refresh callback updates `rH` and re-renders, `rttVal()` helper |
| `view.php` | Added `#nodeSummaryContainer`, dynamic `focusStatusBadge` |
| `page_registry.php` | Version bump `9.5.2` |

## Legacy Nodes
Nodes created before this fix lack `assigned_at` and `downtime_history`. Uptime falls back to first history entry time for these.
