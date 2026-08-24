# AccessPilot — Backup & Restore (Operator Guide)

> Ei doc ta **operator** er jonno. Ki command run korle ki hoy, kobe backup nite hobe, kivabe restore korbe — shudhu eta eikhane likha ache.
> 
> **Technical details** (file structure, tar internals, scenarios): `docs/Technical/backup_restore/TECHNICAL.md`

---

## 1. Quick Commands

| Kaj | Command |
|-----|---------|
| **Code + Data backup** | `sudo bash /app/accesspilot/docker/deploy/backup.sh` |
| **Shudhu data backup** | `sudo bash /app/accesspilot/docker/deploy/backup.sh --data-only` |
| **Shudhu code backup** | `sudo bash /app/accesspilot/docker/deploy/backup.sh --code-only` |
| **Interactive restore** | `sudo bash /app/accesspilot/docker/deploy/rollback.sh` |
| **List available backups** | `sudo bash /app/accesspilot/docker/deploy/rollback.sh --list` |
| **Dekhte ki ki backup ache** | `ls -lh /bkp/*.tar.gz` |

---

## 2. Backup — Kobe nite hobe

| Situation | Ki backup nite hobe | Command |
|-----------|---------------------|---------|
| **Daily (routine)** | Shudhu data | `backup.sh --data-only` |
| **Weekly (full)** | Code + Data | `backup.sh` |
| **Deployment er aage** | Code backup | `backup.sh --code-only` |
| **Kono major change er aage** | Code + Data | `backup.sh` |

**Backup kothay save hoy:** `/bkp/` (alada LVM partition)
**Koto din rakhe:** Last 5 versions, older auto-delete.

---

## 3. Backup — Step by Step

### 3a. Daily Data Backup

```bash
# ========================================
# DATA BACKUP (Daily routine)
# ========================================
# Ki hoy:  /data/secure + /data/logs → /bkp/data_YYYYMMDD.tar.gz
# Time:    2-5 seconds
# Run:     Container running thakleo safe

sudo bash /app/accesspilot/docker/deploy/backup.sh --data-only

# Output dekhte pare:
#   ✅ Data backup done
#   -rw-r--r-- 1 root root 6.4M /bkp/data_20260728.tar.gz
#   🧹 Cleaning old data backups (keep 5)
```

### 3b. Full Backup

```bash
# ========================================
# FULL BACKUP (Code + Data)
# ========================================
# Ki hoy:  Code (/app/accesspilot → /bkp/code_YYYYMMDD.tar.gz)
#          Data (/data/secure + /data/logs → /bkp/data_YYYYMMDD.tar.gz)
# Time:    10-30 seconds

sudo bash /app/accesspilot/docker/deploy/backup.sh

# Output:
#   📦 Creating code backup...
#   ✅ Code backup done  (17M)
#   📦 Creating data backup...
#   ✅ Data backup done  (6.4M)
#   🧹 Cleaning old backups (keep 5)
```

### 3c. Manual Backup (script chara)

```bash
# ========================================
# MANUAL BACKUP
# ========================================
# Use case: backup.sh kaj na korle or specific file include/exclude korte chaile

# Code backup
sudo tar -czf "/bkp/code_$(date +%Y%m%d).tar.gz" \
  --exclude=".git" --exclude=".opencode" \
  -C /app accesspilot

# Data backup
sudo tar -czf "/bkp/data_$(date +%Y%m%d).tar.gz" \
  /data/secure /data/logs
```

---

## 4. Restore — Kobe korte hobe

| Situation | Ki restore korben | How |
|-----------|------------------|-----|
| **Server crash → new server** | Code + Data both | rollback.sh (interactive) |
| **Deployment fail korse** | Code (previous version) | rollback.sh, code select korben |
| **Config corrupt hoye gache** | Shudhu specific file | manual `tar -xzf` specific path |
| **SSL cert expire / lost** | Shudhu data | rollback.sh, data select korben |
| **Jani na ki restore korbo** | Script run korun | `rollback.sh` → prompt guide korbe |

---

## 5. Restore — Step by Step

### 5a. Interactive Restore (Recommended)

```bash
# ========================================
# INTERACTIVE RESTORE
# ========================================
# Ei command run korlei prompt prompt guide korbe:
#   1. Kon version code restore korben → select number
#   2. Full restore naki specific folder → select
#   3. Kon version data restore korben → select number
#   4. Confirm → automatic restore hoye jabe

sudo bash /app/accesspilot/docker/deploy/rollback.sh
```

**Screen e ja dekhabe:**
```
╔════════════════════════════════════════╗
║     Available Backups in /bkp         ║
╚════════════════════════════════════════╝

Code backups:
  code_20260728.tar.gz  (17M)
  code_20260725.tar.gz  (17M)

Data backups:
  data_20260728.tar.gz  (6.4M)

━━━ Step 1: Code Restore ━━━
Select code backup [1-2] or 0 to skip: → 1

━━━ Step 2: Data Restore ━━━
Select data backup [1] or 0 to skip: → 1

⚠️  WARNING: Overwrite will happen!
  Code: code_20260728.tar.gz
  Data: data_20260728.tar.gz

Continue with restore? (yes/no): → yes
```

**Script automatically:** containers stop → extract code → extract data → fix permissions → containers start → verify HTTP.

### 5b. Manual Full Restore (New Server)

```bash
# ========================================
# MANUAL FULL RESTORE (New Server)
# ========================================
# Use: Brand new machine e deploy korchi, puraton backup theke sab firiye anbo

# Step 1: Create directories
sudo mkdir -p /app /data/secure /data/logs
sudo chown 33:33 /data/secure /data/logs

# Step 2: Backup files copy koro (old server theke)
scp root@OLD_SERVER_IP:/bkp/code_20260728.tar.gz /bkp/
scp root@OLD_SERVER_IP:/bkp/data_20260728.tar.gz /bkp/

# Step 3: Extract code
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app

# Step 4: Extract data
sudo tar -xzf /bkp/data_20260728.tar.gz -C /
sudo chown -R 33:33 /data/secure /data/logs

# Step 5: Start
cd /app/accesspilot/docker
sudo docker compose up -d

# Step 6: Check
sleep 3
curl -k https://localhost/health.php
```

### 5c. Specific File Restore

```bash
# ========================================
# PARTIAL RESTORE (specific file)
# ========================================
# Use: Ekta specific file corrupt hoye gache, full restore kora lagbe na

# Step 1: Check file ache ki na backup e
tar -tzf /bkp/code_20260728.tar.gz | grep "config/app.php"

# Step 2: Just oi file restore koro
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app \
  accesspilot/config/app.php

# Step 3: PHP opcache clear (jodi PHP run kore)
docker exec accesspilot_php php -r 'opcache_reset();'
```

---

## 6. Restore er Por Verify

Restore complete howar por check korben:

```bash
# 1. Containers up ache?
docker compose -f /app/accesspilot/docker/docker-compose.yml ps

# 2. HTTP response thik ache?
curl -k https://localhost/health.php

# 3. Old data restore hoyeche?
ls -la /data/secure/ssl/
ls -la /data/secure/profile_img/

# 4. App accessible?
curl -k -o /dev/null -w "HTTP %{http_code}\n" https://localhost/
```

---

## 7. Common Problems & Solution

| Problem | Cause | Solution |
|---------|-------|----------|
| `backup.sh` run korle "permission denied" | Root nai | `sudo` diye run korun |
| Restore er por app 500 error | opcache stale | `docker exec accesspilot_php php -r 'opcache_reset();'` |
| `rollback.sh` e backup list empty | `/bkp/` e kichu nai | First backup run korun: `backup.sh` |
| Old backups delete hoy na | Naming mismatch | Only `code_*` and `data_*` pattern auto-clean hoy |
| Restore er por nginx start kore na | SSL cert path | Check `/data/secure/ssl/` e cert ache ki na |
| Script kaj kore na | Syntax error | `bash -n script.sh` diye check korun |

---

## 8. File Locations

| File | Ki |
|------|----|
| `/bkp/code_*.tar.gz` | Code backup |
| `/bkp/data_*.tar.gz` | Data backup |
| `/app/accesspilot/docker/deploy/backup.sh` | Backup script |
| `/app/accesspilot/docker/deploy/rollback.sh` | Restore script |
| `/app/accesspilot/docs/Technical/backup_restore/TECHNICAL.md` | Detailed technical doc |

---

## 9. Quick Reference — Copy Paste

```bash
# ── Daily Data Backup ──
sudo bash /app/accesspilot/docker/deploy/backup.sh --data-only

# ── Full Backup ──
sudo bash /app/accesspilot/docker/deploy/backup.sh

# ── Interactive Restore ──
sudo bash /app/accesspilot/docker/deploy/rollback.sh

# ── List Backups ──
ls -lh /bkp/*.tar.gz

# ── Manual Code Backup ──
sudo tar -czf /bkp/code_$(date +%Y%m%d).tar.gz --exclude=.git --exclude=.opencode -C /app accesspilot

# ── Manual Data Backup ──
sudo tar -czf /bkp/data_$(date +%Y%m%d).tar.gz /data/secure /data/logs

# ── Manual Code Restore ──
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app

# ── Manual Data Restore ──
sudo tar -xzf /bkp/data_20260728.tar.gz -C /
sudo chown -R 33:33 /data/secure /data/logs

# ── Partial File Restore ──
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app accesspilot/config/app.php
```
