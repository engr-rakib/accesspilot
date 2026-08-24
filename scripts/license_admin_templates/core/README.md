# Generator — Signing Engine

**File:** `core/generator.php`

Eta PHP signing engine. CLI arguments ney, signing string build kore, RSA-SHA256 diye sign kore, signed JSON stdout e print kore.

---

## How It Works

```
User Input (CLI args)
    │  --client, --domain, --deployment-id, --expiry, etc.
    ▼
core/generator.php
    │
    ├── 1. Build signing string:
    │       strtoupper("LIC_ID|ACCESSPILOT|CLIENT|DOMAIN|DEPLOY_ID|EXPIRES|ISSUED_AT|MAX_DOMAINS")
    │
    ├── 2. Load private key from vault/private_key.pem
    │       ├── Not found + --allow-keygen → generate new keypair
    │       └── Not found + no --allow-keygen → ERROR
    │
    ├── 3. Sign with openssl_sign() using RSA-SHA256
    │
    ├── 4. Add base64 signature to payload
    │
    └── 5. echo json_encode(payload) → signed JSON output
```

---

## Relationship with Issue-License.ps1

```
Issue-License.ps1 (user prompts → calls generator → saves to file)
       │
       │  & $PHP_PATH core\generator.php --client="..." --domain="..." --allow-keygen
       │
       ▼
core/generator.php (engine — signs and outputs JSON)
       │
       │  outputs signed JSON
       │
       ▼
Issue-License.ps1 (saves JSON to dist_release_lic/license_{domain}.json)
```

**generator.php** = engine (syntax). **Issue-License.ps1** = steering wheel (user-friendly wrapper). Generator directly CLI diye-o use kora jay.

---

## Commands

```powershell
# From repo root — generate new keypair only
php scripts/license_admin_templates/core/generator.php --allow-keygen

# Issue license directly with all options
php scripts/license_admin_templates/core/generator.php ^
    --id="LIC-20260609-1234" ^
    --product="AccessPilot" ^
    --client="Acme Corp" ^
    --domain="acme.com" ^
    --deployment-id="a1b2c3..." ^
    --expiry="2027-06-18" ^
    --max-domains="5" ^
    --allow-keygen

# Via convenience script (recommended)
.\scripts\license_admin_templates\Issue-License.ps1
.\scripts\license_admin_templates\Renew-License.ps1
```

---

## Key File Paths (inside generator.php)

| Constant | Path | Purpose |
|----------|------|---------|
| `$defaultPrivateKeyPath` | `scripts/../vault/private_key.pem` | Load private key for signing |
| `$defaultPublicKeyPath` | `../../../config/license_public.pem` | Write public key on --allow-keygen |
| `$vaultDir` | `scripts/../vault/` | Vault directory |

---

## About --allow-keygen

- **First use:** `Issue-License.ps1` passes `--allow-keygen` so key auto-creates
- **Without key + without `--allow-keygen`:** Error — private key not found
- **With existing key + `--allow-keygen`:** Keygen skip kore, directly sign kore
- **Deliberate key rotation:** `generator.php --allow-keygen` alada run kore

---

## Key Rotation Impact

**1 ta private key diye unlimited license sign kora jay.** New key generate korle:

| Before | After |
|--------|-------|
| License_A (old verified) ✅ | License_A (old signature vs new public key) ❌ |
| License_B (old verified) ✅ | License_B (old signature vs new public key) ❌ |

**Solution:** Key rotate korle:
1. All client server e new `config/license_public.pem` deploy korte hobe
2. All client er jonno new license re-issue korte hobe
