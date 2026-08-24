# Feasibility Study: LDAP vs PowerShell — Performance & Architecture Comparison

## 1. Executive Summary

The portal has two distinct execution paths for Active Directory operations. **Core Operations** (enable, disable, unlock, reset, modify, create) run via PHP LDAP handlers and respond in **< 1 second**. **Intelligence Hub** operations (Sync, Mapping, Users, Health, Groups, Reports) run via PowerShell scripts and take **8–60+ seconds**.

This document explains why, compares the tradeoffs, and recommends a path forward.

---

## 2. Two Execution Paths

### Path A: Core Operations — Native PHP LDAP Handlers

```
Browser click
    │
    ▼
PHP Controller (e.g. enable-user.php)
    │
    ▼
AD Operation Router → ldap_ready check
    │
    ▼
PHP LDAP Handler (ldap_user_writer_set_enabled)
    │
    ▼
ldap_modify_batch() directly on port 389
    │
    ▼
JSON response → UI feedback card
```

**Characteristics:**
- Single PHP process, no subprocess spawning
- Direct LDAP socket connection (port 389)
- In-memory execution
- Zero startup overhead

### Path B: Intelligence Hub — PowerShell Scripts

```
Browser click
    │
    ▼
PHP Controller
    │
    ▼
ad_dispatch_report_operation() → PowerShell script key lookup
    │
    ▼
powershell_build_command() → builds CLI string
    │
    ▼
exec() or proc_open() — spawns powershell.exe subprocess
    │
    ▼
PowerShell runtime initializes (~500ms–2s)
    │
    ▼
PowerShell script loads:
    ├── Dot-sources ldap_ad_helpers.ps1
    ├── Calls Add-Type for System.DirectoryServices
    ├── Creates DirectorySearcher
    └── Executes LDAP queries → processes data → outputs JSON
    │
    ▼
PHP reads stdout → parsing cleanup → JSON decode
    │
    ▼
UI feedback card
```

**Characteristics:**
- PHP spawns a new `powershell.exe` process per operation
- PowerShell runtime cold start: **500ms–2s** overhead per execution
- Assembly loading (`Add-Type System.DirectoryServices`): **200–500ms**
- Output parsing: PowerShell stdout → PHP decoding
- No persistence between calls (each button click starts fresh)

---

## 3. Performance Comparison

### Response Time

| Operation | Core (PHP LDAP) | Intel Hub (PowerShell) | Ratio |
|-----------|----------------|----------------------|-------|
| Single user enable/disable | **0.3–0.8s** | N/A | — |
| Single user unlock | **0.3–0.7s** | N/A | — |
| Password reset | **0.5–1.0s** | N/A | — |
| Modify user | **0.5–1.2s** | N/A | — |
| HRMS Sync (7000 users) | N/A | **~8s** (AD module CLI) / **~15s** (LDAP IIS) | — |
| HRMS→AD Mapping | N/A | **~2s** (LDAP IIS) | — |
| Export Users (7000 users) | N/A | **~8s** (AD module CLI) / **~15s** (LDAP IIS) | — |
| AD Health Check | N/A | **30–60s** | — |
| User Report (1000s users) | N/A | **5–10s** | — |

### Startup Time Breakdown (PowerShell)

| Phase | Time |
|-------|------|
| PHP → exec() call | < 1ms |
| powershell.exe cold start | 500–2000ms |
| Add-Type System.DirectoryServices | 200–500ms |
| Script parsing & JIT compilation | 100–300ms |
| **Total fixed overhead** | **800–2800ms** |

### Data Processing (7000-user export)

| Phase | AD Module (CLI) | LDAP (IIS) |
|-------|----------------|------------|
| Query all users | ~3s | ~6s |
| Admin group checks | ~2s | ~4s |
| CSV formatting | ~1s | ~2s |
| JSON serialization | ~1s | ~1s |
| PowerShell overhead | ~1s | ~2s |
| **Total** | **~8s** | **~15s** |

### Why LDAP fallback is slower than AD module

| Factor | AD Module | LDAP DirectorySearcher |
|--------|-----------|----------------------|
| **Protocol** | ADWS (port 9389) — Kerberos-authenticated, optimised | LDAP (port 389) — plaintext, no auth context |
| **Query engine** | Server-side optimised (catalog service) | Generic LDAP search |
| **Property loading** | Named property set (optimised) | Explicit attribute list (full DN resolution) |
| **Group membership** | `memberOf` backlink + tokenGroups | `memberOf` attribute only (direct members) |
| **Bulk operations** | Batched internally | Page-by-page (1000 at a time) |

---

## 4. Architectural Differences

### Core Operations — Designed for speed

```
In-process PHP    →    Single LDAP modify    →    Response in < 1s
     │                             │
 No process spawn      No data parsing
 No serialization      Direct socket I/O
```

- No subprocess — PHP makes LDAP calls directly
- Single atomic operation (enable, disable, modify one attribute)
- Feedback is immediate (the result of one `ldap_modify_batch`)

### Intelligence Hub — Designed for data processing

```
PHP spawns PowerShell    →    LDAP queries    →    Data processing    →    JSON output    →    PHP parses
     │                            │                     │                        │                  │
 ~2s startup              ~6s query           ~3s transform           ~1s serialize       ~0.5s decode
```

- Operations are **data-heavy**: export 7000 users, cross-reference with HRMS API, build group membership trees
- PowerShell is used for its mature AD module and .NET data processing
- Each button click spawns a completely new PowerShell process
- No connection pooling — LDAP searcher created fresh each time

### The fundamental tradeoff

| Aspect | Core Operations | Intelligence Hub |
|--------|----------------|-----------------|
| **Scope** | Single user, one action | Bulk data, entire directory |
| **State** | Stateless (one modify call) | Stateful (builds data structures) |
| **Data volume** | Bytes (one entry) | Megabytes (entire directory) |
| **Latency sensitivity** | Critical (user waiting) | Moderate (user expects loading) |
| **Processing** | Trivial (set bit, clear bit) | Complex (filter, sort, group, join) |

---

## 5. Why Both Approaches Exist

### Historical reasons

1. **PowerShell was implemented first** — all 12+ scripts were written using RSAT `Get-AD*` cmdlets before any LDAP work began.
2. **LDAP handlers were added later** — for Core Operations only, because single-user actions are high-frequency and latency-sensitive.
3. **Intelligence Hub scripts were never ported** — they are more complex and used less frequently, so the LDAP fallback (via `ldap_ad_helpers.ps1`) was chosen as a faster path to IIS compatibility.

### Technical reasons

Core Operations are **trivial to implement as LDAP handlers**:
- `ldap_modify_batch` with `uac` attribute for enable/disable
- `ldap_modify_batch` with `lockoutTime` for unlock
- `ldap_modify_batch` with `unicodePwd` for password reset

Intelligence Hub scripts are **significantly harder to port**:
- `export-group-user-list.ps1` — 300+ lines of filtering, admin group checking, lastLogonTimestamp processing, OU path parsing, CSV generation
- `get-user-report.ps1` — multi-DC lastLogon synchronization, FileTime math, pagination
- `check-ad-hrms-status.ps1` — HRMS API calls, LDAP cross-reference, date comparison
- `get-ad-health.ps1` — DC diagnostics, DCDiag parsing, HTML report generation, FSMO role checks

---

## 6. Feasibility: Porting Intelligence Hub to PHP LDAP Handlers

| Script | Lines | Porting Effort | Dependencies | Worth porting? |
|--------|-------|---------------|--------------|----------------|
| `check-ad-hrms-status.ps1` | ~150 | Medium | HRMS API, DateTime math | **Maybe** — medium complexity, low frequency |
| `export-hrms-ad-login-id.ps1` | ~80 | Low | Simple LDAP query | **Yes** — trivial to port |
| `export-group-user-list.ps1` | ~300 | High | CSV generation, group membership | **Maybe** — high effort, used less often |
| `get-user-report.ps1` | ~130 | Medium | lastLogon sync across DCs | **Maybe** — medium complexity |
| `get-ad-health.ps1` | ~1200 | Very High | DCDiag, WinRM, CIM, HTML generation | **No** — requires domain-level access scripts |

### What would be gained

- **No PowerShell startup overhead** (save 1–2s per operation)
- **Direct LDAP port 389** — no Kerberos/ADWS dependency
- **Consistent architecture** — all operations go through same LDAP layer
- **No CLIXML/DPAPI dependency** — works under any IIS identity

### What would be lost

- **PowerShell ecosystem** — COM, `repadmin`, `dcdiag`, WMI/CIM, HTML rendering
- **Mature RSAT cmdlets** — `Search-ADAccount`, `Get-ADDomainController`, `Get-ADForest`
- **Easy debugging** — PowerShell ISE, step-through, verbose output
- **Rapid development** — PowerShell is faster to write and test for AD tasks

---

## 7. Recommendations

### Short-term (current state) — ACCEPTED

Keep the current hybrid approach:
- **Core Operations** → PHP LDAP handlers (fast path)
- **Intelligence Hub** → PowerShell scripts with `ldap_ad_helpers.ps1` LDAP fallback

This is already working. The LDAP fallback is slower but functional under IIS. Users see a loading spinner while the script runs.

### Medium-term (next quarter)

**Port `export-hrms-ad-login-id.ps1` to a PHP LDAP handler.**
- ~80 lines, simple logic, big UX win
- Reduces Mapping operation from ~2s to ~0.5s
- Low risk, high visibility

### Long-term (if needed)

**Evaluate a persistent PowerShell runspace.**
- Instead of spawning `powershell.exe` per request, maintain a long-running PowerShell session
- Could use `Invoke-Command` with a dedicated session or a Windows service
- Eliminates 1–2s cold start overhead
- Requires Windows remote management infrastructure

### Never do

**Do NOT port `get-ad-health.ps1` to LDAP.**
- 1200 lines of DC diagnostics
- Requires `repadmin`, `dcdiag`, `WinRM`, `CIM`
- These are not LDAP operations — they are server management operations
- This script needs credentials and domain-level access by design

---

## 8. Performance Targets

| Operation | Current (IIS) | Target | Path |
|-----------|--------------|--------|------|
| Enable/Disable/Unlock | 0.3–0.8s | < 1s | ✅ Already met (PHP LDAP) |
| Password Reset | 0.5–1.0s | < 1s | ✅ Already met (PHP LDAP) |
| Modify User | 0.5–1.2s | < 1s | ✅ Already met (PHP LDAP) |
| Create User | 1–3s | < 2s | 🔄 Improving (PHP LDAP) |
| HRMS→AD Mapping | ~2s | < 1s | 🎯 Port to PHP LDAP |
| HRMS Sync | ~15s | < 10s | ❌ Not practical without AD module |
| Export Users | ~15s | < 10s | ❌ Limited by data volume |
| User Report | ~8s | < 5s | ❌ Limited by lastLogon sync |
| AD Health | 30–60s | < 30s | ❌ Requires DC diagnostics |

---

## 9. Conclusion

The **two-path architecture is correct** for this application:

- **Fast path (PHP LDAP handlers)** for single-user, latency-sensitive operations
- **Batch path (PowerShell + LDAP fallback)** for bulk data processing and complex reports

The Intelligence Hub is inherently slower because it (a) processes thousands of directory objects and (b) runs in a new PowerShell process per request. The LDAP fallback via `ldap_ad_helpers.ps1` adds a few extra seconds but makes the system work under IIS without credentials — which was the primary goal.

If Mapping speed becomes a priority, porting the HRMS→AD ID lookup (~80 lines) to a PHP LDAP handler would give the best ROI. The other Hub operations are unlikely to see dramatic speed improvements without a persistent PowerShell runspace, which adds infrastructure complexity.
