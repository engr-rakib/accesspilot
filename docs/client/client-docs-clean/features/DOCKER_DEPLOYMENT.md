# Docker Deployment — Enterprise-Grade Container Infrastructure

## Imagine This

Your AD management platform runs 24/7 without interruption. Updates deploy in seconds, not hours. A server crash doesn't mean downtime — containers restart automatically. Your entire stack — web server, application, monitoring — is defined in a single file and deploys identically on any Linux server.

**That's what Docker delivers for AccessPilot.**

---

## Why Docker?

### Stop Fighting Server Configurations

| Before (Windows IIS) | With Docker on Linux |
|---------------------|---------------------|
| Manual IIS setup per server | One `docker compose up -d` — everything runs |
| Windows license costs ($500+) | Ubuntu/Debian — completely free |
| Heavy resource usage (2GB+ RAM idle) | Lightweight (~256MB per container) |
| Reboot required for updates | Zero-downtime rolling restarts |
| Tied to Windows infrastructure | Any Linux server, any cloud |
| Hard to backup/restore | Volume snapshots + simple tar backups |

### What Docker Makes Possible

```
┌─────────────────────────────────────────────────────────────┐
│                    AccessPilot on Docker                     │
│                                                             │
│  One server. Two containers. Full enterprise platform.     │
│                                                             │
│  ┌──────────────────────┐  ┌──────────────────────────┐   │
│  │   Web Gateway        │  │   Application Core        │   │
│  │   ───────────────    │  │   ────────────────────    │   │
│  │   • Static files     │  │   • AD user management    │   │
│  │   • Security rules   │  │   • Exchange management   │   │
│  │   • SSL termination  │  │   • Quick Actions         │   │
│  └──────────┬───────────┘  └──────────┬───────────────┘   │
│             │                          │                   │
│             └──────────┬───────────────┘                   │
│                        │                                   │
│              ┌─────────▼─────────┐                        │
│              │   Active Directory │                        │
│              │   Domain Controllers│                       │
│              └───────────────────┘                        │
└─────────────────────────────────────────────────────────────┘
```

---

## Features That Matter

### Instant Recovery

If the application process itself crashes, Docker restarts it in under 1 second. No manual intervention, no page reload needed. Your helpdesk keeps working.

### Zero-Downtime Updates

Code changes apply by simply restarting the application container — takes 2-3 seconds. Users don't even notice. No maintenance windows required.

### Data That Survives Everything

Your vault (users, credentials, license) and audit logs live on the host filesystem — not inside containers. Even a full container teardown (the nuclear option) can't touch them. Data survives:

- Container restarts ✅
- Server reboot ✅
- Full re-deployment ✅
- Disk failures (with RAID-1) ✅

### One File Defines Everything

The entire infrastructure is in one compose file — about 80 lines. Want to change memory settings? Update a setting and restart. Need to add a monitoring service? Add a few lines to the file.

### Security Built-In

- **Read-only container filesystems** — no malware can write to the container
- **All Linux capabilities dropped** — only the bare minimum is added back
- **No-new-privileges** — child processes can't escalate
- **Application hardening** — extra layers of file and session security
- **Security rules** — blocks known attack paths and hidden-file access

### Secure Connections, Automatically

```
AccessPilot → Your Active Directory (sign-on)
           → Your Exchange servers (mail operations)
           → A secure, ticket-based channel
```

All from inside a single Docker container. No Windows server needed.

---

## What You Get

| Capability | Detail |
|-----------|--------|
| **High Availability** | Auto-restart, health checks, systemd service |
| **Data Safety** | Bind mounts on host RAID-1 — survive any docker operation |
| **Quick Updates** | Upload code via WinCP → run cleanup → restart container — done in 10 seconds |
| **Backup & Restore** | One-command backup of vault + logs + settings. Rollback in 2 minutes. |
| **Monitoring** | Container health, PHP errors, audit logs, disk usage — all visible |
| **Scalability** | Scale the application horizontally with a single command — no settings changes needed |

---

## In Short

Docker turns AccessPilot into a **self-healing, zero-downtime, enterprise-grade platform** that runs on any Linux server. No Windows licenses, no IIS configuration nightmares, no vendor lock-in.

Everything in one compose file. Everything backed up. Everything recoverable.

---

*AccessPilot — Enterprise AD Management, Containerized.*
