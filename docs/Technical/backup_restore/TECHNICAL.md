# AccessPilot — Backup & Restore Guide

> Ei document e backup ebong restore er jonno step-by-step manual instructions dewa ache.
> 
> **Backup location:** `/bkp/` (alada LVM partition — `vg_data/lv_bkp`)
> **Retention:** Last 5 versions rakhe, older auto-delete hoy.
> **Two categories:** Code (App) + Data (secure vault + logs)

---

## Table of Contents

1. [Backup (Manual tar)](#1-backup-manual-tar)
2. [Backup (Script)](#2-backup-script)
3. [Restore (Manual tar)](#3-restore-manual-tar)
4. [Restore (Script — Interactive)](#4-restore-script-interactive)
5. [Rollback Scenarios](#5-rollback-scenarios)
6. [Cron Setup](#6-cron-setup)
7. [File Structure Reference](#7-file-structure-reference)

---

## 1. Backup (Manual tar)

### 1a. Code Backup — `/app/accesspilot/`

```bash
# === CODE BACKUP ===
# Ki hoy:  /app/accesspilot entire project tar hoye /bkp/ e save hobe
# Exclude: .git + .opencode (version control files, backup e dorkar nai)
# Output:  /bkp/code_20260728.tar.gz

PARENT_DIR="/app"            # Project er parent directory
PROJECT_NAME="accesspilot"   # Project folder name

sudo tar -czf "/bkp/code_$(date +%Y%m%d).tar.gz" \
  --exclude="$PROJECT_NAME/.git" \
  --exclude="$PROJECT_NAME/.opencode" \
  -C "$PARENT_DIR" "$PROJECT_NAME"

# Verify
ls -lh /bkp/code_*.tar.gz
```

**Tar file er vitor ki ache:**
```
accesspilot/
├── app/              # PHP application code
├── bootstrap/        # Bootstrap files
├── config/           # Config files
├── public/           # Web root (index.php, api/)
├── resources/        # Views, CSS, JS
├── docker/           # Docker configs (nginx, compose, Dockerfile)
├── App_Data/         # Runtime data
├── scripts/          # Utility scripts
├── vendor/           # Dependencies
└── ...
```

### 1b. Data Backup — `/data/secure/` + `/data/logs/`

```bash
# === DATA BACKUP ===
# Ki hoy:  /data/secure (config, certs, license, avatars) + /data/logs (audit logs)
# Output:  /bkp/data_20260728.tar.gz

sudo tar -czf "/bkp/data_$(date +%Y%m%d).tar.gz" \
  /data/secure /data/logs

# Verify
ls -lh /bkp/data_*.tar.gz
```

**Important:** Data tar file e **absolute path** diye save hoy (e.g., `/data/secure/...`). Ei jonno restore er shomoy `-C /` use korte hobe.

### 1c. Both Code + Data (ek command e)

```bash
# === FULL BACKUP (Code + Data) ===
# Same as running 1a + 1b together

PARENT_DIR="/app"
PROJECT_NAME="accesspilot"

sudo tar -czf "/bkp/code_$(date +%Y%m%d).tar.gz" \
  --exclude="$PROJECT_NAME/.git" --exclude="$PROJECT_NAME/.opencode" \
  -C "$PARENT_DIR" "$PROJECT_NAME"

sudo tar -czf "/bkp/data_$(date +%Y%m%d).tar.gz" \
  /data/secure /data/logs

echo "Backup complete:"
ls -lh /bkp/code_*.tar.gz /bkp/data_*.tar.gz
```

### 1d. Old Backups Cleanup (manual)

```bash
# === CLEANUP: Keep last 5 versions only ===
# Ei command automatically last 5 tar beye sob delete kore

# Code: keep last 5
ls -t /bkp/code_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r sudo rm -f

# Data: keep last 5
ls -t /bkp/data_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r sudo rm -f
```

---

## 2. Backup (Script)

Script use korle sob automatically hoy — tar + cleanup.

```bash
# === CODE + DATA backup (both) ===
sudo bash /app/accesspilot/docker/deploy/backup.sh

# === Shudhu CODE backup ===
sudo bash /app/accesspilot/docker/deploy/backup.sh --code-only

# === Shudhu DATA backup ===
sudo bash /app/accesspilot/docker/deploy/backup.sh --data-only

# === Ki ki backup ache dekha ===
ls -lh /bkp/*.tar.gz
```

**Script automatically:** 
- Code backup → `/bkp/code_20260728.tar.gz` (excludes .git, .opencode)
- Data backup → `/bkp/data_20260728.tar.gz`
- Old versions clean kore (keeps last 5)

---

## 3. Restore (Manual tar)

### Scenario A: Full Restore (New Server)

```bash
# === FULL RESTORE ===
# Use case: Brand new server e deploy korbo, backup theke restore korbo

PROJECT_ROOT="/app/accesspilot"
PARENT_DIR="/app"

# Step 1: Prerequisites
sudo mkdir -p "$PARENT_DIR" /data/secure /data/logs
sudo chown 33:33 /data/secure /data/logs

# Step 2: Copy backup files from old server
scp root@OLD_SERVER_IP:/bkp/code_20260728.tar.gz /bkp/
scp root@OLD_SERVER_IP:/bkp/data_20260728.tar.gz /bkp/

# Step 3: Extract code
#   -C /app means files go to /app/accesspilot/
sudo tar -xzf /bkp/code_20260728.tar.gz -C "$PARENT_DIR"

# Step 4: Extract data
#   -C / means /data/secure → /data/secure, /data/logs → /data/logs
sudo tar -xzf /bkp/data_20260728.tar.gz -C /
sudo chown -R 33:33 /data/secure /data/logs

# Step 5: Start containers
cd "$PROJECT_ROOT/docker"
sudo docker compose up -d

# Step 6: Verify
sleep 3
curl -k https://localhost/health.php
```

### Scenario B: Shudhu Code Restore

```bash
# === CODE RESTORE ===
# Use case: Code corrupt hoye gache, shudhu code return korte hobe

# Step 1: Stop containers
cd /app/accesspilot/docker
sudo docker compose down

# Step 2: Restore code (specific version)
# Latest version restore
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app

# Or previous version (e.g., 20260725)
sudo tar -xzf /bkp/code_20260725.tar.gz -C /app

# Step 3: Start containers
sudo docker compose up -d
```

### Scenario C: Shudhu Data Restore

```bash
# === DATA RESTORE ===
# Use case: /data/secure corrupt or lost

# Step 1: Restore data (containers running thakleo kora jay, but safer to stop)
cd /app/accesspilot/docker && sudo docker compose down

# Step 2: Restore
sudo tar -xzf /bkp/data_20260728.tar.gz -C /
sudo chown -R 33:33 /data/secure /data/logs

# Step 3: Start
sudo docker compose up -d
```

### Scenario D: Partial Restore (Specific Files)

```bash
# === PARTIAL RESTORE ===
# Use case: Shudhu specific file/directory restore korte hobe

# First: check tar file e ki ki ache
tar -tzf /bkp/code_20260728.tar.gz | grep "config/app.php"

# Then: restore just that file
#   -C /app → path ta tar vitorer moto kore dite hobe
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app \
  accesspilot/config/app.php

# Data file restore
sudo tar -xzf /bkp/data_20260728.tar.gz -C / \
  data/secure/ssl/accesspilot.crt
```

---

## 4. Restore (Script — Interactive)

Script ta **fully interactive** — prompt follow kore restore kora jay:

```bash
sudo bash /app/accesspilot/docker/deploy/rollback.sh
```

### Interactive Flow

```
Step 1: Available backups list dekhabe
        ┌────────────────────────────────┐
        │ Code backups:                   │
        │   [1] code_20260728.tar.gz      │
        │   [2] code_20260725.tar.gz      │
        │ Data backups:                   │
        │   [1] data_20260728.tar.gz      │
        └────────────────────────────────┘

Step 2: "Select code backup [1-N] or 0 to skip"
        → apni number enter korben

Step 3: Tar file er contents dekhabe (first 30 files)
        "Restore all files [1] or specific dirs [2]?"
        → [1] = full restore
        → [2] = specific path enter korben

Step 4: Data backup er jonno same prompt

Step 5: "Continue with restore? (yes/no)"
        → yes diye confirm korle:
           → containers stop
           → tar extract
           → containers start
           → verify

Step 6: Result: "✅ Rollback successful"
```

### Non-Interactive Options

```bash
# Shudhu available backups list dekhte:
sudo bash /app/accesspilot/docker/deploy/rollback.sh --list
```

---

## 5. Rollback Scenarios

### Scenario 1: Failed Update — Full Rollback

```bash
# Problem: New deploy e app crash / 500 error
# Solution: Last backup e fire jawa

sudo bash /app/accesspilot/docker/deploy/rollback.sh

# No need to specify version — "latest" auto select kore
# Yes diye confirm korlei sob restore hoye jabe
```

### Scenario 2: Corrupted Config File

```bash
# Problem: config/app.php accidentally edited, app broken
# Solution: Shudhu oi file ta restore kora

# First: tar er vitor path ta check
tar -tzf /bkp/code_20260728.tar.gz | grep "app.php"

# Then: restore (without stopping containers if not needed)
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app \
  accesspilot/config/app.php

# Kintu PHP container e opcache cache kora thakle — clear korte hobe
docker exec accesspilot_php php -r 'opcache_reset();'
```

### Scenario 3: SSL Certificate Expired

```bash
# Problem: /data/secure/ssl/accesspilot.crt expired
# Solution: Backup theke old cert restore

# Check tar e cert ache ki na
tar -tzf /bkp/data_20260728.tar.gz | grep "ssl/accesspilot"

# Restore just the cert
sudo tar -xzf /bkp/data_20260728.tar.gz -C / \
  data/secure/ssl/accesspilot.crt \
  data/secure/ssl/accesspilot.key

# Nginx reload (container restart charai)
docker exec accesspilot_web nginx -s reload
```

### Scenario 4: Accidental File Deletion

```bash
# Problem: Kono important PHP file delete hoye gache
# Solution: Tar theke specific file restore

# Step 1: Check kon version e file ta ache
for f in /bkp/code_*.tar.gz; do
  if tar -tzf "$f" 2>/dev/null | grep -q "accesspilot/app/Application/Http/Controllers/get_avatar.php"; then
    echo "$(basename $f): FOUND"
  fi
done

# Step 2: Restore
sudo tar -xzf /bkp/code_20260728.tar.gz -C /app \
  accesspilot/app/Application/Http/Controllers/get_avatar.php
```

---

## 6. Cron Setup

Daily backup (data only) — code normally git e thakay code backup cron e dorkar nai:

```bash
# Open crontab
sudo crontab -e

# Add this line (daily at 2 AM)
0 2 * * * /app/accesspilot/docker/deploy/backup.sh --data-only > /dev/null 2>&1

# Verify cron
sudo crontab -l
```

Jodi code backup o cron e lagay:

```bash
# Complete backup — every Sunday at 3 AM
0 3 * * 0 /app/accesspilot/docker/deploy/backup.sh > /dev/null 2>&1
```

---

## 7. File Structure Reference

```
/bkp/                           # Backup directory (LVM: vg_data-lv_bkp)
├── code_20260728.tar.gz        # Code backup (17 MB)
├── data_20260728.tar.gz        # Data backup (6.4 MB)
├── code_20260727.tar.gz        # Previous version (auto-cleaned after 5)
└── ...

/app/accesspilot/               # Code base
├── app/                        # PHP application
├── config/                     # Configuration
├── docker/                     # Docker configs
│   └── deploy/
│       ├── backup.sh           # Backup script
│       ├── rollback.sh         # Restore script
│       ├── harden.sh           # Host hardening
│       ├── BACKUP_RESTORE.md   # This file
│       └── DEPLOY.md           # Deployment guide
├── public/                     # Web root
├── resources/                  # Views, assets
└── App_Data/                   # Runtime data

/data/secure/                   # Vault
├── config/                     # App config
├── ssl/                        # SSL certificates
├── profile_img/                # Avatars
└── ...

/data/logs/                     # Logs
├── nginx/                      # Nginx access + error logs
└── php_error_logs/             # PHP errors
```

---

## Quick Reference (Cheat Sheet)

| Task | Command |
|------|---------|
| Code backup | `sudo bash /app/accesspilot/docker/deploy/backup.sh --code-only` |
| Data backup | `sudo bash /app/accesspilot/docker/deploy/backup.sh --data-only` |
| Full backup | `sudo bash /app/accesspilot/docker/deploy/backup.sh` |
| List backups | `ls -lh /bkp/*.tar.gz` |
| Interactive restore | `sudo bash /app/accesspilot/docker/deploy/rollback.sh` |
| See available backups | `sudo bash /app/accesspilot/docker/deploy/rollback.sh --list` |
| Manual code backup | `sudo tar -czf /bkp/code_$(date +%Y%m%d).tar.gz --exclude=.git --exclude=.opencode -C /app accesspilot` |
| Manual data backup | `sudo tar -czf /bkp/data_$(date +%Y%m%d).tar.gz /data/secure /data/logs` |
| Manual code restore | `sudo tar -xzf /bkp/code_20260728.tar.gz -C /app` |
| Manual data restore | `sudo tar -xzf /bkp/data_20260728.tar.gz -C / && sudo chown -R 33:33 /data/secure /data/logs` |
| Partial restore | `sudo tar -xzf /bkp/code_20260728.tar.gz -C /app accesspilot/config/app.php` |
| Backup cleanup (keep 5) | `ls -t /bkp/code_*.tar.gz \| tail -n +6 \| xargs rm -f` |
| Cron (daily data) | `0 2 * * * /app/accesspilot/docker/deploy/backup.sh --data-only` |
