# Vendor License Operations

This folder is the vendor-side workspace for issuing and renewing AccessPilot licenses.

---

## Directory Structure

```
app root/
├── docs/                              ← 📄 All documentation
│   ├── api/
│   │   └── API_DOCUMENTATION.md       ← HRMS API Integration Guide
│   └── license/
│       ├── LICENSE_A-Z.md             ← Comprehensive A-Z guide
│       ├── DEPLOYMENT_ORDER.md        ← Deployment flow
│       ├── VENDOR_SECURITY_AND_DEPLOYMENT.md  ← Security rules
│       └── LICENSE_ARCHITECTURE.md    ← Architecture details
├── scripts/
│   └── license_admin_templates/       ← 🛠 Vendor tools
│       ├── core/
│       │   ├── generator.php          ← Signing engine (PHP)
│       │   └── README.md              ← Generator usage docs
│       ├── Issue-License.ps1          ← Issue new license
│       ├── Renew-License.ps1          ← Renew existing license
│       └── Initialize-Vendor-License-Environment.ps1  ← Setup env
│       ├── vault/
│       │   ├── private_key.pem        ← RSA private key (vendor only)
│       │   └── README.md              ← Key management + rotation warnings
│       └── guideline/
│           └── README.md              ← Index + troubleshooting
```

---

## What Each File Does

| File | Purpose |
|------|---------|
| `core/generator.php` | Signing engine — builds signing string, signs with RSA-SHA256, outputs signed JSON |
| `Issue-License.ps1` | Prompts user, calls generator.php, saves signed JSON to `dist_release_lic/` |
| `Renew-License.ps1` | Same as Issue but for renewals (prefix REN-) |
| `Initialize-Vendor-License-Environment.ps1` | Sets env vars (PHP path, key paths, openssl.cnf) |
| `vault/private_key.pem` | RSA private key — signs ALL licenses |
| `vault/vendor-env.local.ps1` | Persisted env config (created by Initialize script with -PersistLocalConfig) |

---

## How They Work Together

```
Issue-License.ps1
    │  user prompts → collects client info
    │  calls: & $PHP_PATH core\generator.php --allow-keygen ...
    ▼
core/generator.php
    │  checks vault/private_key.pem
    │  missing? → generate new keypair + public key
    │  builds signing string → signs with RSA-SHA256
    │  outputs signed JSON
    ▼
Issue-License.ps1
    │  saves JSON to dist_release_lic/license_{domain}.json
    ▼
Client receives → pastes in License page → verifies with public key
```

---

## Key Concept: 1 Private Key, Unlimited Licenses

`vault/private_key.pem` — **1 ta key diye unlimited license sign kora jay**.

```php
// License A (different data → different signature)
sign("LIC-001|ACCESSPILOT|CLIENT A|...") → "abc123..."

// License B (different data → different signature)
sign("LIC-002|ACCESSPILOT|CLIENT B|...") → "xyz789..."
```

Same key, different license data → **completely different signature**. Forge kora impossible.

---

## ⚠️ Key Rotation Warning

Jodi `--allow-keygen` run kore **new keypair** generate koren:

| Before | After |
|--------|-------|
| All old licenses verify with old public key ✅ | Old licenses **FAIL** with new public key ❌ |
| — | Need to update `config/license_public.pem` on ALL client servers |
| — | Need to re-issue license for ALL clients |

**Rule:** Do NOT run `--allow-keygen` unless you intend to re-license everyone.

---

## Common Commands

```powershell
# First time setup
.\scripts\license_admin_templates\Initialize-Vendor-License-Environment.ps1 -PersistLocalConfig

# Issue new license
.\scripts\license_admin_templates\Issue-License.ps1

# Renew license
.\scripts\license_admin_templates\Renew-License.ps1

# Only generate keypair (no license)
php scripts/license_admin_templates/core/generator.php --allow-keygen

# Load saved env
. .\scripts\license_admin_templates\vault\vendor-env.local.ps1
```

---

## Related Docs (moved to `docs/`)

- `docs/license/LICENSE_A-Z.md` — Comprehensive A-Z guide
- `docs/license/DEPLOYMENT_ORDER.md` — Deployment order
- `docs/license/VENDOR_SECURITY_AND_DEPLOYMENT.md` — Security rules
- `docs/license/LICENSE_ARCHITECTURE.md` — Architecture
- `docs/api/API_DOCUMENTATION.md` — HRMS API Integration Guide
- `core/README.md` — Generator details
- `vault/README.md` — Key management + rotation
- `guideline/README.md` — Troubleshooting + key paths
