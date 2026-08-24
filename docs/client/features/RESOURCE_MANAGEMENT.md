# AccessPilot — Resource Management Guide (CPU + RAM)

> **Language:** Banglish (Bangla + English)
> **Purpose:** Server er CPU / RAM utilization manually control korar jonno

---

## Overview

Server e ki ki CPU + RAM consume kore:

| Resource | Typical Usage | Source |
|----------|--------------|--------|
| AccessPilot (2 containers) | ~20-80 MB | Nginx + PHP-FPM |
| Docker engine | ~100-200 MB | dockerd + containerd |
| fail2ban | ~30-50 MB | Intrusion prevention |
| **ClamAV (clamd)** | **~800 MB - 1.2 GB** | Antivirus — production server e unnecessary |
| **opencode** | **~500 MB - 2 GB** | AI agent (development tool) |
| **VS Code Server** | **~400 MB - 800 MB** | Remote development |
| UFW / systemd / logrotate | ~10-20 MB | Security + maintenance |

**Bottom line:** Production server e shudhu AccessPilot + Docker + fail2ban + UFW rakha uchit. Baki sob dev tool — bandwidth/server resource noshto kore.

---

## Quick Check + Kill Commands

Eigula shobcheye common command — direct use korben situation onujayi.

### Check (ki ki CPU/RAM consume korche)

```bash
# Top 10 RAM user (highest → lowest)
ps aux --sort=-%mem | head -10

# Top 10 CPU user (highest → lowest)
ps aux --sort=-%cpu | head -10

# Total free/used memory (quick snapshot)
free -h

# Docker container level stats (per container)
docker stats --no-stream
```

### Kill (immediate relief — biggest hogs stop)

```bash
# 1. ClamAV antivirus — ~1 GB RAM free hoy
sudo systemctl stop clamav-daemon clamav-freshclam

# 2. opencode AI agent — ~500 MB - 2 GB free hoy
sudo kill $(pgrep -f "opencode") 2>/dev/null

# 3. VS Code Remote Server — ~400-800 MB free hoy
sudo kill $(pgrep -f "vscode-server") 2>/dev/null
```

### Verify (ki free korlo)

```bash
free -h                              # Check freed memory
ps aux --sort=-%mem | head -5        # Confirm top users gone
```

---

## 1. ClamAV (Antivirus) — Stop + Disable

Ki kore: Real-time virus scan. Production DC/server e dorkar nai.

```bash
# Stop now
sudo systemctl stop clamav-daemon clamav-freshclam

# Prevent auto-start on boot
sudo systemctl disable clamav-daemon clamav-freshclam

# Check status
sudo systemctl status clamav-daemon         # should show "inactive (dead)"
```

**Frees**: ~800 MB - 1.2 GB RAM

**To re-enable later:**
```bash
sudo systemctl enable clamav-daemon clamav-freshclam
sudo systemctl start clamav-daemon clamav-freshclam
```

---

## 2. Opencode (AI Agent) — Manual Start/Stop

Ki kore: AI-powered development agent. Session er shomoy chole. Eta server feature na — dev tool.

```bash
# Stop the current opencode session
# Process kill:
sudo kill $(pgrep -f "opencode") 2>/dev/null

# Check if still running
pgrep -a opencode || echo "Opencode not running"
```

**Frees**: ~500 MB - 2 GB (depends on session size)

**Note:** Opencode er instance toiri hoy AI agent call korle. Apni jokhon ei RESOURCE_MANAGEMENT.md file ta create korar jonno bolchen — tokhon eeta runtime e chole.

---

## 3. VS Code Server (Remote SSH) — Stop/Disable

Ki kore: Remote VS Code connection. Local machine theke connect korle eto memory hoy.

```bash
# Stop vsode-server
sudo systemctl stop vscode-server 2>/dev/null || true

# Kill all vsode-server processes
sudo kill $(pgrep -f "vscode-server") 2>/dev/null

# Disable auto-start
sudo systemctl disable vscode-server 2>/dev/null || true

# Verify
pgrep -a vscode || echo "VS Code Server not running"
```

**Frees**: ~400 MB - 800 MB

---

## 4. Docker System Cleanup (Disk Space)

Ki kore: Old images, stopped containers, build cache — resources dokhol kore rakhe.

```bash
# Show disk usage by Docker
docker system df

# Clean unused resources (images, containers, volumes, build cache)
docker system prune -f

# Deep clean (also removes unused images)
docker system prune -af

# Check AccessPilot container resource usage
docker stats --no-stream
```

**Frees**: Disk space (not RAM, but important for disk usage monitoring)

---

## 5. AccessPilot PHP-FPM Worker Tuning

Ki kore: PHP container e koto gula worker thakbe, koto request por por reset hobe.

Current settings (applied):

| Setting | Value | Meaning |
|---------|-------|---------|
| `pm.max_children` | 20 | Maximum parallel PHP workers |
| `pm.start_servers` | 5 | Startup e koto worker |
| `pm.min_spare_servers` | 3 | Minimum idle workers |
| `pm.max_spare_servers` | 10 | Maximum idle workers |
| `pm.max_requests` | 500 | Prottek worker 500 request por reset |

If server e low RAM situation:

```bash
# Adjust PHP-FPM pool settings on the fly via docker exec
docker exec accesspilot_php sh -c 'echo "pm.max_children = 10" >> /usr/local/etc/php-fpm.d/zz-tuning.conf'
docker exec accesspilot_php sh -c 'echo "pm.start_servers = 3" >> /usr/local/etc/php-fpm.d/zz-tuning.conf'
docker compose restart php
```

**Effect**: Lower `pm.max_children` = less memory, less concurrency.

---

## 6. Nginx Worker Tuning

Ki kore: Nginx worker processes + connections.

Current settings (applied):

| Setting | Value |
|---------|-------|
| `worker_processes` | auto (2 cores = 2 workers) |
| `worker_connections` | 1024 |
| `open_file_cache` | max=1000, 30s |

If CPU high:

```bash
# Check nginx worker count
docker exec accesspilot_web nginx -T 2>&1 | grep worker_processes

# Adjust (edit docker/nginx/default.conf to add):
# worker_processes 1;
# Then restart:
docker compose restart nginx
```

**Effect**: 1 worker = less CPU competition, less concurrency.

---

## 7. Snapshots & Monitoring

```bash
# CPU + RAM snapshot
echo "=== CPU TOP ===" && ps aux --sort=-%cpu | head -8
echo "=== RAM TOP ===" && ps aux --sort=-%mem | head -8
echo "=== MEM FREE ===" && free -h
echo "=== DOCKER STATS ===" && docker stats --no-stream
```

---

## 8. Quick Reference — Common Scenarios

### "Server RAM high, need immediate relief"

```bash
# 1. Stop ClamAV (biggest hog)
sudo systemctl stop clamav-daemon clamav-freshclam

# 2. Kill unnecessary opencode sessions
sudo kill $(pgrep -f "opencode") 2>/dev/null

# 3. Check result
free -h
```

### "App feels slow, CPU high"

```bash
# 1. Check if PHP workers maxed out
docker exec accesspilot_php ps aux | grep php-fpm | wc -l

# 2. Check nginx error log
docker exec accesspilot_web tail -20 /var/log/nginx/error.log

# 3. Restart PHP (clears all workers)
docker compose restart php
```

### "Server booting up — want minimal resource usage"

```bash
# Disable heavy services
sudo systemctl disable clamav-daemon clamav-freshclam vscode-server 2>/dev/null || true

# Only enable what's needed for AccessPilot
sudo systemctl enable accesspilot docker  # auto-start on boot
```

---

## 9. Expected Free Memory Table

| Action | RAM Freed | Cumulative Free |
|--------|-----------|-----------------|
| Stop ClamAV | ~1 GB | ~1 GB |
| Stop opencode session | ~0.5-2 GB | ~1.5-3 GB |
| Stop VS Code Server | ~0.5 GB | ~2-3.5 GB |
| PHP container restart | ~10-50 MB | ~2-3.5 GB |
| Docker system prune | (disk) | (disk space) |

**After cleanup:** 7.7 GB server e ~400-600 MB used, ~7 GB available.
