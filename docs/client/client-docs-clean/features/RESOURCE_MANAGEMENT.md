# AccessPilot — Resource Management Guide (CPU + RAM)

> **Language:** Banglish (Bangla + English)
> **Purpose:** Server er CPU / RAM utilization control korar jonno

---

## Overview

Server e ki ki CPU + RAM consume kore:

| Resource | Typical Usage | Source |
|----------|--------------|--------|
| AccessPilot (web + application) | ~20-80 MB | The portal itself |
| Docker engine | ~100-200 MB | Containers |
| Automated attacker blocking | ~30-50 MB | Intrusion prevention |
| **Real-time antivirus** | **~800 MB - 1.2 GB** | Antivirus — production server e unnecessary |
| **opencode** | **~500 MB - 2 GB** | AI agent (development tool) |
| **VS Code Server** | **~400 MB - 800 MB** | Remote development |
| Firewall / services / log rotation | ~10-20 MB | Security + maintenance |

**Bottom line:** Production server e shudhu AccessPilot + Docker + security tools rakha uchit. Baki sob dev tool — bandwidth/server resource noshto kore.

---

## Quick Tips

Eigula soto soto decisions — situation onujayi.

### Check (ki ki CPU/RAM consume korche)

In the portal's server settings you can see which services and processes consume the most CPU and RAM at a glance — a live snapshot lists the top users.

### Stop Heavy Helpers

If the AI assistant (opencode) or VS Code Server are running on the same machine, closing them frees a large amount of memory.

### Verify

After stopping heavy helpers, refresh the snapshot to confirm the freed memory.

---

## 1. Real-Time Antivirus — Stop + Disable

Ki kore: Real-time virus scan. Production server e dorkar nai.

- Stop kore din akhon — tarpor boot e auto-start hobe na
- Applied by the server admin (see the portal admin guide)

**Frees**: ~800 MB - 1.2 GB RAM

**To re-enable later:** start korte hole portal admin guide follow koren.

---

## 2. Opencode (AI Agent) — Manual Start/Stop

Ki kore: AI-powered development agent. Session er shomoy chole. Eta server feature na — dev tool.

- Session sesh hole bandh koren — ~500 MB - 2 GB free hoy
- Running dev session thakle portal er server settings theke bondho korte paren

**Frees**: ~500 MB - 2 GB (depends on session size)

**Note:** Opencode er instance toiri hoy AI agent call korle.

---

## 3. VS Code Server (Remote SSH) — Stop/Disable

Ki kore: Remote VS Code connection. Local machine theke connect korle eto memory hoy.

- Connection bandh korle ~400 MB - 800 MB free hoy
- Auto-start off kore din (portal admin guide)

**Frees**: ~400 MB - 800 MB

---

## 4. Docker System Cleanup (Disk Space)

Ki kore: Old images, stopped containers, leftovers — disk space noshto kore rakhe.

- Portal admin guide e unused containers/images cleanup er one-command instruction ache
- Container resource usage dekha jay portal er status screen e

**Frees**: Disk space (not RAM, but important for disk usage monitoring)

---

## 5. Application Workload Tuning

Ki kore: The portal lets you balance memory and speed by adjusting how many tasks it can run at once.

Current settings (applied):

| Setting | Value | Meaning |
|---------|-------|---------|
| Max concurrent tasks | 20 | Highest number of parallel jobs |
| Starting tasks | 5 | Tasks ready at startup |
| Min idle tasks | 3 | Kept ready when quiet |
| Max idle tasks | 10 | Ceiling when quiet |
| Task refresh | 500 | Each task resets after 500 jobs |

If server e low RAM situation:

- Adjust the max concurrent tasks to a lower number (e.g., 10) in the portal's server settings
- Apply changes instantly — no server commands needed

**Effect**: Lower concurrency = less memory, less parallel load.

---

## 6. Gateway Workload Tuning

Ki kore: The portal's gateway balances how many connections it accepts.

Current settings (applied):

| Setting | Value |
|---------|-------|
| Worker awareness | auto (matches server cores) |
| Max connections | 1024 |
| Open file memory | max=1000, 30s |

If CPU high:

- Reduce accepted concurrency so the gateway does less parallel work (see portal admin guide)

**Effect**: Lower concurrency = less CPU competition, more headroom.

---

## 7. Snapshots & Monitoring

- CPU + RAM snapshots and container-level stats are available in the portal's status screens
- See the portal admin guide for where to find each view

---

## 8. Common Scenarios

### "Server RAM high, need immediate relief"

1. Stop the real-time antivirus service (biggest hog — ~1 GB)
2. Close opencode / development sessions
3. Refresh the status snapshot to confirm

### "App feels slow, CPU high"

1. Check in the portal whether the application has hit its concurrency ceiling
2. Reduce max concurrent tasks (see the portal admin guide)
3. Or restart the application container from the deployment tooling

### "Server booting up — want minimal resource usage"

1. Keep only the portal and its security services enabled
2. Development tools stay off (see the portal admin guide)

---

## 9. Expected Free Memory Table

| Action | RAM Freed | Cumulative Free |
|--------|-----------|-----------------|
| Stop antivirus | ~1 GB | ~1 GB |
| Stop opencode session | ~0.5-2 GB | ~1.5-3 GB |
| Stop VS Code Server | ~0.5 GB | ~2-3.5 GB |
| Application restart | ~10-50 MB | ~2-3.5 GB |
| Docker cleanup | (disk) | (disk space) |

**After cleanup:** 7.7 GB server e ~400-600 MB used, ~7 GB available.