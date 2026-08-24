# AccessPilot — Command Bundle

> All operational commands in one place.  
> If a command is not here, think twice before running it.

---

## Legend

| Badge | Meaning |
|-------|---------|
| ✅ SAFE | No side effects — run anytime |
| ⚠️ CAUTION | Read impact before running |
| 🔴 DANGER | Can destroy data or stop production |
| 🚫 NEVER | Will break the deployment |

---

## 1. Container Lifecycle

### Start Containers

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
```

| | |
|---|---|
| **When** | Fresh deploy, after `down`, after server reboot |
| **Effect** | Pulls nginx image if missing, creates network, starts both containers in background. Idempotent — if already running, no change. |
| **Risk** | ✅ SAFE |

### Stop Containers

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml down
```

| | |
|---|---|
| **When** | Server shutdown, maintenance window, hardware change |
| **Effect** | Stops both containers, removes container instances. Network stays. Bind mount data (`/data/secure`, `/data/logs`) preserved. No data loss. |
| **Risk** | ⚠️ CAUTION — app goes offline until `up -d` |

### Restart PHP Container

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart php
```

| | |
|---|---|
| **When** | After code update (WinCP/Git), config change, OPcache clearing not working, Exchange host resolution needs refresh, PHP memory issue |
| **Effect** | Kills PHP-FPM process, Docker recreates container (same filesystem), PHP-FPM restarts. Active HTTP requests dropped (~2-3 sec downtime). Mounts, network, IP unchanged. |
| **Risk** | ✅ SAFE |

### Restart Nginx Container

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart nginx
```

| | |
|---|---|
| **When** | After nginx config change (`default.conf`), static files returning 404/302 after WinCP (inode cache stale), HTTPS redirect issues |
| **Effect** | Nginx reloads config, re-mounts static file directories (fixes inode issue). ~1 sec downtime. PHP unaffected. |
| **Risk** | ✅ SAFE |

### Restart Both Containers

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart
```

| | |
|---|---|
| **When** | After full code update, after Dockerfile change (image already built), both services need refresh |
| **Effect** | Restarts nginx then PHP. ~3-5 sec total downtime. Faster than `down + up -d` because containers are recreated in place. |
| **Risk** | ✅ SAFE |

---

## 2. Docker Image

### Build Image

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml build
```

| | |
|---|---|
| **When** | First deploy, after Dockerfile change, after pwsh/PSWSMan update needed, when image is missing |
| **Effect** | Reads Dockerfile, executes each RUN command, caches layers. First build: ~10 min (pwsh download + install). Subsequent: ~30 sec (cache hit). No change to running containers — must `up -d` or `restart` to use new image. |
| **Risk** | ✅ SAFE |

### Build With No Cache

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml build --no-cache
```

| | |
|---|---|
| **When** | Stale cache causing build issues, base image (Debian/php) needs security update, pwsh version needs refresh, PSWSMan not installing properly |
| **Effect** | Downloads everything fresh — Debian base, PHP, pwsh, all extensions. 10-15 min. Produces identical image but with latest Debian/pwsh security patches. |
| **Risk** | ⚠️ CAUTION — long downtime if you `down + up -d` immediately after |

### Pull Official Nginx Image

```bash
sudo docker pull nginx:1.25-alpine
```

| | |
|---|---|
| **When** | First deploy (auto-pulled by compose), security update for nginx |
| **Effect** | Downloads latest nginx:1.25-alpine digest. Doesn't affect running container until `restart nginx`. |
| **Risk** | ✅ SAFE |

---

## 3. Logs & Monitoring

### View PHP Container Logs

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs php --tail 50
```

| | |
|---|---|
| **When** | PHP error in browser (white screen, 500 error), debugging code issue, checking if PHP-FPM started, LDAP connection errors |
| **Effect** | Shows last 50 lines of PHP-FPM stdout/stderr. Read-only. Not the same as PHP error log (see below). |
| **Risk** | ✅ SAFE |

### Follow Live PHP Logs

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs -f php
```

| | |
|---|---|
| **When** | Watching real-time PHP output during debug, monitoring request flow, checking cron job output |
| **Effect** | Streams logs continuously. Ctrl+C to stop. |
| **Risk** | ✅ SAFE |

### View Nginx Container Logs

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs nginx --tail 50
```

| | |
|---|---|
| **When** | Static files returning 404, HTTPS redirect not working, access denied errors, 502 Bad Gateway |
| **Effect** | Shows last 50 lines of nginx access + error log. Read-only. |
| **Risk** | ✅ SAFE |

### View PHP Error Log (Host)

```bash
tail -f /data/logs/php_error_logs/php_errors.log
```

| | |
|---|---|
| **When** | Any PHP runtime error, white screen, debugging LDAP/Exchange failures, checking PHP warnings |
| **Effect** | Shows actual PHP errors (not stdout). Persists on host — survives container restart. |
| **Risk** | ✅ SAFE |

### List Container Processes

```bash
docker exec accesspilot_php ps aux
```

| | |
|---|---|
| **When** | Check if PHP-FPM is running, see how many child processes, find hung processes, verify cron is active |
| **Effect** | Lists all processes inside PHP container. Read-only. |
| **Risk** | ✅ SAFE |

### Container Disk Usage

```bash
docker exec accesspilot_php df -h
```

| | |
|---|---|
| **When** | Check disk space inside container, verify bind mounts are mounted, check /data/secure available space |
| **Effect** | Shows filesystem usage from inside container. Read-only. |
| **Risk** | ✅ SAFE |

---

## 4. Debug & Diagnostics

### Shell Into PHP Container

```bash
docker exec -it accesspilot_php bash
```

| | |
|---|---|
| **When** | Manual debugging: check files exist, test LDAP connection with PHP, run PHP scripts interactively, check permissions, verify cron setup |
| **Effect** | Opens interactive bash shell as root inside container. Full access to container filesystem (read-only root FS, but bind mounts writable). Type `exit` or Ctrl+D to leave. |
| **Risk** | ✅ SAFE |

### Check LDAP Extension Loaded

```bash
docker exec accesspilot_php php -r 'echo extension_loaded("ldap") ? "OK" : "MISSING";'
```

| | |
|---|---|
| **When** | After build, after PHP update, when LDAP operations fail, during initial setup |
| **Effect** | Outputs "OK" or "MISSING". Read-only diagnostic. |
| **Risk** | ✅ SAFE |

### Check OPcache Status

```bash
docker exec accesspilot_php php -r 'print_r(opcache_get_status(false));'
```

| | |
|---|---|
| **When** | PHP changes not reflecting, checking if OPcache is enabled, verifying cache hits/misses |
| **Effect** | Shows OPcache statistics: memory usage, hits, misses, cached files. Read-only. |
| **Risk** | ✅ SAFE |

### Clear OPcache

```bash
docker exec accesspilot_php php -r 'opcache_reset();'
```

| | |
|---|---|
| **When** | After PHP file changes (always), after config changes, after WinCP upload, after Git pull |
| **Effect** | Clears all cached PHP bytecode. Next request recompiles PHP files from disk. No downtime. No restart needed. |
| **Risk** | ✅ SAFE |

### Run Arbitrary PHP File

```bash
docker exec accesspilot_php php /var/www/html/scripts/cron_monitor.php
```

| | |
|---|---|
| **When** | Testing cron scripts manually, debugging a specific PHP script, running maintenance scripts |
| **Effect** | Executes the PHP file inside container with full app context. Output appears in terminal. |
| **Risk** | ✅ SAFE |

### Check Kerberos Tickets

```bash
docker exec accesspilot_php klist
```

| | |
|---|---|
| **When** | Exchange operations returning 401 Unauthorized, Exchange operations failing, checking if ticket expired |
| **Effect** | Shows cached Kerberos tickets: principal name, expiry time, server SPN. If no tickets shown, Exchange auth will fail. |
| **Risk** | ✅ SAFE |

### Check PSWSMan Module

```bash
docker exec accesspilot_php pwsh -c 'Get-Module PSWSMan -ListAvailable'
```

| | |
|---|---|
| **When** | Exchange PowerShell cmdlets failing, after pwsh update, after image rebuild, checking PSWSMan version |
| **Effect** | Lists installed PSWSMan module with version. Read-only. If empty, WinRM calls will fail. |
| **Risk** | ✅ SAFE |

### Check Exchange Host Resolution

```bash
docker exec accesspilot_php cat /etc/hosts | grep -i exchange
```

| | |
|---|---|
| **When** | Exchange operations failing, after container restart, checking if resolve_exchange_hosts.php worked |
| **Effect** | Shows Exchange server FQDN → IP mapping in /etc/hosts. Read-only. Empty output = no Exchange host configured or script didn't run. |
| **Risk** | ✅ SAFE |

### Check Cron Jobs in Container

```bash
docker exec accesspilot_php crontab -l
```

| | |
|---|---|
| **When** | Monitoring data not recording, after container restart, checking if cron was set up by startup command |
| **Effect** | Lists cron jobs for www-data. Should show cron_monitor.php (2 entries) + system_history_recorder.php. |
| **Risk** | ✅ SAFE |

### DNS Test From Container

```bash
docker exec accesspilot_php nslookup dc01.whildc.com
```

| | |
|---|---|
| **When** | Cannot reach AD from container, Exchange host not resolving, name resolution issues |
| **Effect** | Queries DNS server from inside container. Shows resolved IP. |
| **Risk** | ✅ SAFE |

### Ping Test From Container

```bash
docker exec accesspilot_php ping -c 3 192.168.1.10
```

| | |
|---|---|
| **When** | Network connectivity issues, AD DC unreachable, Exchange server unreachable, container network troubleshooting |
| **Effect** | Sends 3 ICMP echo requests to target. Shows RTT and packet loss. |
| **Risk** | ✅ SAFE |

---

## 5. PHP & Config

### Verify PHP Security Settings

```bash
docker exec accesspilot_php php -i | grep -E "disable_functions|open_basedir|memory_limit"
```

| | |
|---|---|
| **When** | After php-security.ini change, checking if hardening is applied, debugging "function disabled" errors |
| **Effect** | Shows current values of disable_functions, open_basedir, memory_limit from loaded config. Read-only. |
| **Risk** | ✅ SAFE |

### Check Loaded PHP Config File

```bash
docker exec accesspilot_php php -i | grep "Loaded Configuration File"
```

| | |
|---|---|
| **When** | Config changes not taking effect, checking which php.ini is active, verifying override files loaded |
| **Effect** | Shows path to loaded php.ini and additional .ini files (conf.d). Confirms security.ini and error-logging.ini are loaded. |
| **Risk** | ✅ SAFE |

### Check PHP Error Log Path

```bash
docker exec accesspilot_php php -i | grep error_log
```

| | |
|---|---|
| **When** | PHP errors not showing in log, verifying error_log setting |
| **Effect** | Shows configured error log path. Should be `/data/logs/php_error_logs/php_errors.log`. |
| **Risk** | ✅ SAFE |

---

## 6. Network & Firewall

### Check Container Port Binding

```bash
sudo docker port accesspilot_web
```

| | |
|---|---|
| **When** | After compose change, check which port Docker mapped, verify loopback binding |
| **Effect** | Shows `8080/tcp -> 127.0.0.1:8080`. Confirms port is bound to loopback only. |
| **Risk** | ✅ SAFE |

### List Docker Networks

```bash
sudo docker network ls
```

| | |
|---|---|
| **When** | Network issues, checking accesspilot_net exists, after compose down (network may persist) |
| **Effect** | Lists all Docker networks. Should show `accesspilot_net` (bridge). |
| **Risk** | ✅ SAFE |

### Inspect Container Network

```bash
sudo docker network inspect accesspilot_net
```

| | |
|---|---|
| **When** | Containers can't communicate, checking IP assignments, verifying both containers on same network |
| **Effect** | Shows subnet (172.x.x.x/16), connected containers with their IPs. Nginx should have one IP, PHP another. |
| **Risk** | ✅ SAFE |

### Check UFW Status

```bash
sudo ufw status verbose
```

| | |
|---|---|
| **When** | After first deploy, firewall rule changes, checking if 8080 is blocked, verifying SSH allowed |
| **Effect** | Shows UFW rules: ports allowed/denied. Status should be "active". |
| **Risk** | ✅ SAFE |

### Check iptables DOCKER-USER

```bash
sudo iptables -L DOCKER-USER -v -n
```

| | |
|---|---|
| **When** | After firewall setup, verifying 8080 blocked from external, after server reboot |
| **Effect** | Shows DOCKER-USER chain rules. Should show DROP for port 8080 when source is not 127.0.0.1. |
| **Risk** | ✅ SAFE |

### Test 8080 Access

```bash
curl -s --max-time 3 http://localhost:8080 | head -3
```

| | |
|---|---|
| **When** | After firewall setup, after iptables rule change, verifying container is reachable |
| **Effect** | From localhost: gets HTML/redirect (expected — localhost bypasses DROP rule). From external: connection timeout. |
| **Risk** | ✅ SAFE |

---

## 7. Backup & Restore

### Data-Only Backup

```bash
sudo bash /opt/accesspilot/docker/deploy/backup.sh --data-only
```

| | |
|---|---|
| **When** | Daily cron (scheduled), before any risky operation (config change, update), before rollback |
| **Effect** | Creates timestamped tar.gz of /data/secure + /data/logs + App_Data in /opt/accesspilot/backups/. No downtime. ~10-30 sec. |
| **Risk** | ✅ SAFE |

### Full Backup

```bash
sudo bash /opt/accesspilot/docker/deploy/backup.sh
```

| | |
|---|---|
| **When** | Weekly scheduled backup, before major update (Dockerfile change, rebuild), before migration |
| **Effect** | Creates full backup including code + config + data + App_Data. Larger file, takes longer. No downtime. |
| **Risk** | ✅ SAFE |

### Rollback to Latest

```bash
sudo bash /opt/accesspilot/docker/deploy/rollback.sh
```

| | |
|---|---|
| **When** | Code deployment failed, application broken after update, database/config corrupted, need to undo last change |
| **Effect** | Stops containers → extracts latest backup → overwrites code + config + data → starts containers → verifies HTTP 200. ~1-2 min downtime. |
| **Risk** | ⚠️ CAUTION — any changes since backup will be lost |

### Rollback to Specific Backup

```bash
sudo bash /opt/accesspilot/docker/deploy/rollback.sh 20260619_143000
```

| | |
|---|---|
| **When** | Need to restore a specific version (not latest), cross-reference with known working backup |
| **Effect** | Same as above but uses backup with matching timestamp. |
| **Risk** | ⚠️ CAUTION — same data loss warning |

### Find Actual Image Name

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml images
```

| | |
|---|---|
| **When** | Before exporting image — always check first. Name varies by project directory (e.g., `docker-php`, `accesspilot-php`) |
| **Effect** | Shows repository:tag for both containers. Use that name in docker save/load commands. |
| **Risk** | ✅ SAFE |

### Export Docker Image

```bash
# Replace docker-php with actual name from docker compose images output
sudo docker save docker-php:latest -o /backup/php_image.tar && gzip /backup/php_image.tar
```

| | |
|---|---|
| **When** | Monthly DR backup, before Dockerfile change, before pwsh update, creating portable image for air-gapped server |
| **Effect** | Saves ~1.2 GB image to disk as tar. Compressed ~500 MB. Read-only, no service impact. |
| **Risk** | ✅ SAFE — but needs 1.2 GB free disk |

### Import Docker Image

```bash
gunzip -f /backup/php_image.tar.gz && sudo docker load -i /backup/php_image.tar
```

| | |
|---|---|
| **When** | Restore on new server (DR), image deleted accidentally, air-gapped deployment (no internet to pull layers) |
| **Effect** | Loads image into local Docker cache. Must `compose up -d` after to use. |
| **Risk** | ✅ SAFE |

---

## 8. Storage & Permissions

### Fix Vault Ownership

```bash
sudo chown -R 33:33 /data/secure /data/logs
```

| | |
|---|---|
| **When** | After data restore (tarball extracted as root), PHP can't write to vault, "Permission denied" in logs, after backup rotation deleted old files |
| **Effect** | Changes owner of all files to www-data (UID 33). PHP-FPM can then read/write vault files. |
| **Risk** | ⚠️ CAUTION — don't run if /data/secure is shared with other services that need different owner |

### Fix Vault Permissions

```bash
sudo chmod -R 770 /data/secure /data/logs
```

| | |
|---|---|
| **When** | After data restore, permission errors, after copying from Windows (wrong permissions) |
| **Effect** | Owner/group get rwx, others get nothing. Secure vault from unauthorized read. |
| **Risk** | ⚠️ CAUTION — too restrictive if other services need access to /data/secure |

### Check Disk Usage

```bash
df -h / /data /var/lib/docker
```

| | |
|---|---|
| **When** | Weekly maintenance, disk full alerts, before backup, before build (needs space) |
| **Effect** | Shows available/free space for root, data, docker storage. |
| **Risk** | ✅ SAFE |

### Check Directory Size

```bash
du -sh /data/secure /data/logs
```

| | |
|---|---|
| **When** | Planning backup (estimate size), log rotation needed, investigating disk usage |
| **Effect** | Shows total size of vault and logs directories. |
| **Risk** | ✅ SAFE |

---

## 9. System & Host

### Check Container Status

```bash
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml ps
```

| | |
|---|---|
| **When** | First thing after any operation, after deploy, after restart, when app is down |
| **Effect** | Shows state of both containers: `Up` (healthy) or `Exited`. If `Up (unhealthy)`, container is running but health check failed. |
| **Risk** | ✅ SAFE |

### Check Systemd Service

```bash
sudo systemctl status accesspilot
```

| | |
|---|---|
| **When** | After server reboot (check auto-start worked), after systemd unit change, before server shutdown |
| **Effect** | Shows active/inactive status of accesspilot systemd service. Read-only. |
| **Risk** | ✅ SAFE |

### Check Docker Daemon

```bash
sudo systemctl status docker
```

| | |
|---|---|
| **When** | Containers not starting, "Cannot connect to Docker daemon" error, after Docker install |
| **Effect** | Shows if Docker daemon is running. If inactive, no containers can start. |
| **Risk** | ✅ SAFE |

### Reload Systemd Config

```bash
sudo systemctl daemon-reload
```

| | |
|---|---|
| **When** | After editing `accesspilot.service`, after adding new systemd unit files |
| **Effect** | Reloads all systemd unit files from disk. Required for changes to take effect. No service restart. |
| **Risk** | ✅ SAFE |

### Restart Host Nginx

```bash
sudo systemctl restart nginx
```

| | |
|---|---|
| **When** | After host nginx config change, after SSL cert renewal, after rate limit change |
| **Effect** | Host nginx reloads config. ~1 sec. Only affects HTTPS reverse proxy — Docker containers unaffected. |
| **Risk** | ✅ SAFE |

### Test Host Nginx Config

```bash
sudo nginx -t
```

| | |
|---|---|
| **When** | Before `restart nginx` (always), after editing `/etc/nginx/sites-available/accesspilot`, after SSL cert change |
| **Effect** | Validates nginx config syntax. If it fails, fix errors before restart. |
| **Risk** | ✅ SAFE |

---

## 10. 🔴 DANGEROUS — Use Only When You Know What You're Doing

| Command | When Someone Might Want to Run It | What Actually Happens | Risk |
|---------|----------------------------------|-----------------------|------|
| `sudo docker compose down -v` | "I want a clean reset" | Removes **all** volumes including named volumes. Our bind mounts survive, but if anyone adds named volumes later, they die. Don't make it a habit. | 🔴 Data loss if named volumes added |
| `sudo docker compose down --rmi all` | "I want to free disk space" | Stops containers AND deletes both images (nginx + php). Next `up -d` will re-pull nginx but PHP must be rebuilt: ~10 min downtime. | 🔴 Long rebuild |
| `sudo docker system prune -a` | "Clean everything unused" | Removes all stopped containers, unused networks, dangling images, **and** all unused images (not just dangling). Next `compose build` has zero cache. | 🔴 Slow next build |
| `sudo docker volume prune` | "Clean Docker disk usage" | Removes all named volumes not used by at least one container. Safe now (bind mounts), but will destroy data if someone uses named volumes later. | 🔴 Potential data loss |
| `sudo rm -rf /data/secure/*` | "I want to reset the vault" | **All users deleted. All roles gone. License lost. LDAP config lost.** App becomes unusable. Only recoverable from backup. | 🔴 Irreversible data loss |
| `sudo rm -rf /data/logs/*` | "Free up disk space" | All audit logs, monitoring history, PHP error logs permanently deleted. | 🔴 Audit loss |
| `sudo chown -R 0:0 /data/secure` | Chown to root | PHP-FPM (www-data, UID 33) can no longer write vault files. Login fails, user operations fail. | 🔴 App breaks |
| `sudo chmod -R 777 /data/secure` | "Fix permission errors" | Any user on the system can read vault data (users, passwords, license). **Massive security hole.** | 🔴 Security breach |
| `sudo ufw disable` | "Firewall is blocking something" | All ports open. Server exposed to network. SSH accessible from anywhere. | 🔴 Server vulnerable |
| `sudo iptables -F DOCKER-USER` | "Reset iptables" | Removes 8080 block. Anyone on network can directly access Docker nginx, bypassing HTTPS reverse proxy. Plain HTTP traffic. | 🔴 Security bypass |

---

## 11. 🚫 NEVER Use These — Under Any Circumstance

| Command | Why Someone Might Try It | Why It Will Destroy Everything |
|---------|-------------------------|-------------------------------|
| `sudo rm -rf / --no-preserve-root` | "Server is slow, clean it" | Erases entire filesystem. Server unrecoverable without reinstall. |
| `sudo docker rm -f $(docker ps -aq)` | "Reset all containers" | Force-kills and removes **every** container on this server, not just accesspilot. |
| `sudo kill -9 $(pgrep php-fpm)` inside container | "Kill stuck PHP" | Kills PHP-FPM master process. Container auto-restarts but kills all active HTTP requests mid-flight. Data corruption possible. |
| `sudo chmod 777 / -R` | "Permission denied error" | Every file on system world-writable. Security destroyed. System breaks. Only fix: reinstall OS. |
| `docker exec accesspilot_php php -r 'shell_exec("rm -rf /");'` | Testing limits | Read-only FS blocks writes to /, but if tmpfs writable paths could be damaged. Don't test. |
| Editing docker-compose.yml without backup | "Quick change" | One typo in YAML indentation = containers won't start. Production down. |
| `docker compose up -d` without pulling new image | "Restart with new code" | If you only changed Dockerfile but didn't `build`, `up -d` runs old image. Changes don't apply. |
| `sudo apt remove docker-ce` | "Reinstall Docker" | Removes Docker Engine. Containers die. Images lost. Requires full reinstall. |

---

## 12. Command Sequences — Common Scenarios

### After WinCP Code Upload

```bash
# Situation: You copied code via WinCP from Windows to /opt/accesspilot/
# After upload, static files return 404 and PHP changes don't apply (inode issue)

sudo bash /opt/accesspilot/docker/deploy/post_upload_cleanup.sh   # Remove Windows artifacts
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart php     # Fix PHP bind mount inode
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart nginx   # Fix nginx static file inode
docker exec accesspilot_php php -r 'opcache_reset();'               # Clear PHP bytecode cache
```

### After Git Pull

```bash
# Situation: Code updated via git pull from repository

cd /opt/accesspilot
sudo git pull origin main
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart php
docker exec accesspilot_php php -r 'opcache_reset();'
```

### After Server Reboot

```bash
# Situation: Server restarted (power outage, maintenance reboot)
# Systemd should auto-start via accesspilot.service

sudo systemctl status accesspilot        # Check if auto-started
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml ps    # Verify containers up

# If not running:
sudo systemctl start accesspilot         # Start via systemd
# OR manually:
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d
```

### Exchange Failing (401 Unauthorized)

```bash
# Situation: Exchange operations returning 401 after hours of working fine
# Likely: Kerberos ticket expired, or host resolution lost after restart

docker exec accesspilot_php klist                                   # Check if ticket exists + expiry
docker exec accesspilot_php cat /etc/hosts | grep -i exchange       # Check Exchange host in /etc/hosts
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart php  # Forces re-resolve + new ticket
```

### Full Health Check

```bash
# Situation: Monthly verification, or after any change, or when users report issues

echo "=== Containers ==="
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml ps

echo "=== HTTP Status ==="
curl -k -s -o /dev/null -w "%{http_code}\n" https://localhost

echo "=== LDAP Extension ==="
docker exec accesspilot_php php -r 'echo extension_loaded("ldap") ? "OK" : "MISSING"; echo PHP_EOL;'

echo "=== PHP Version ==="
docker exec accesspilot_php php -v | head -1

echo "=== Disk Usage ==="
df -h / /data

echo "=== Container Uptime ==="
docker exec accesspilot_php uptime
```

### When App Is Down (Emergency)

```bash
# Situtation: Users can't access the app. Page doesn't load.

sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml ps          # Step 1: Are containers running?
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs php --tail 20  # Step 2: Latest PHP errors?
tail -20 /data/logs/php_error_logs/php_errors.log                            # Step 3: PHP runtime errors?
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs nginx --tail 20 # Step 4: Nginx errors?
sudo nginx -t                                                                 # Step 5: Host Nginx OK?
sudo systemctl status nginx                                                   # Step 6: Host Nginx running?
curl -k -I https://localhost                                                  # Step 7: Local response?
ping -c 1 $(hostname -I | awk '{print $1}')                                  # Step 8: Network up?
```

---

## 13. Quick Reference — One-Liners

```bash
# ── Container Lifecycle ──
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml up -d       # Start
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml down        # Stop
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml restart php # Restart PHP
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml ps          # Status

# ── Logs ──
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs php --tail 50
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml logs -f php
tail -f /data/logs/php_error_logs/php_errors.log

# ── Debug ──
docker exec -it accesspilot_php bash
docker exec accesspilot_php php -r 'opcache_reset();'
docker exec accesspilot_php php -r 'echo extension_loaded("ldap") ? "OK" : "MISSING";'
docker exec accesspilot_php klist
docker exec accesspilot_php crontab -l

# ── Backup ──
sudo bash /opt/accesspilot/docker/deploy/backup.sh --data-only
sudo bash /opt/accesspilot/docker/deploy/rollback.sh

# ── Build ──
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml build
sudo docker compose -f /opt/accesspilot/docker/docker-compose.yml build --no-cache

# ── Host ──
sudo nginx -t
sudo systemctl restart nginx
sudo ufw status verbose
sudo iptables -L DOCKER-USER -v -n

# ── Health ──
curl -k -I https://localhost
df -h / /data /var/lib/docker
```
