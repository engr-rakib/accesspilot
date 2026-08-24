# Vendor Security And Deployment

**Product:** AccessPilot (UM-Portal)  
**Audience:** Vendor operators and release engineers

## 1. Operating Model

AccessPilot uses a split trust model:
- customer deployment stores runtime identity and active license state
- vendor generates signed license JSON externally (PowerShell scripts OR web UI)
- customer application verifies signatures using the public key only

Vendor-side tools live in:
- `scripts/license_admin_templates/` (PowerShell scripts)
- `index.php?page=vendor_license` (Web UI Vendor Console)
- preferred environment bootstrap: `Initialize-Vendor-License-Environment.ps1`

## 2. Sensitive Material

### Public material safe for deployment
- `config/license_public.pem`

### Private material that must stay vendor-side only
- `scripts/license_admin_templates/vault/private_key.pem` (PowerShell path)
- `{ACCESSPILOT_SECURE_BASE_PATH}/vendor_licenses/private_key.pem` (Web UI path)

Rules:
- never ship the private key to clients
- never commit replacement private keys into customer delivery bundles
- ideally keep the private key outside the application repo entirely

Preferred environment variables:
- `ACCESSPILOT_VENDOR_PHP`
- `ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH`
- `ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH`
- `OPENSSL_CONF`

## 3. Current Storage And Identity Model

### Storage roots used by the live application
- secure vault root: `C:/inetpub/Desk_secure_files`
- log root: `C:/access_pilot_logs`

### Main internal runtime files
- `App_Data/setup_complete.lock`
- `App_Data/internal_admin.json`

### Main external runtime files
- `C:/inetpub/Desk_secure_files/accesspilot_deployment_identity.xml`
- `C:/inetpub/Desk_secure_files/license_state.json`
- `C:/inetpub/Desk_secure_files/vendor_licenses/licenses.json` (all license records from web UI)
- `C:/inetpub/Desk_secure_files/vendor_licenses/private_key.pem` (uploaded from web UI)
- vault JSON files under `appusers/`, `requests/`, `passwd/`, `app_notifications/`

### Config files that matter
- `config/storage.php`
- `config/license.php`
- `config/license_public.pem`
- `config/app.php` (contains `org_name`, `domain_name`, `deployment_id`, `encryption_key`)

### Deployment ID: AES-256-CBC encryption
- The Deployment ID is not a random UUID — it is encrypted `org_name|domain_name` using AES-256-CBC
- Format: `hex(IV):hex(ciphertext)` (e.g. `a1b2c3d4...:8a9b0c1d...`)
- Encryption key is derived from `config/app.php` → `encryption_key` via SHA-256 (truncated to 32 bytes)
- Functions in `helpers.php`: `encrypt_deployment_data()` and `decrypt_deployment_data()`
- This binds the org identity cryptographically to the license; tampering breaks the ID
- Legacy UUIDs cannot be decrypted but still work (manual vendor entry required)

## 4. License Issuance Workflow

### Option A — Web UI (Vendor Console at `index.php?page=vendor_license`)

1. **Signing Key card** (one-time setup):
   - Upload RSA private key (PEM text) via textarea
   - System validates the key is a valid 2048-bit RSA private key
   - Stored at `{secure}/vendor_licenses/private_key.pem`
   - Status badge shows "Active (2048-bit RSA)" or "Not Configured"
   - Delete button to remove the key

2. **Generate License form**:
   - **Paste the client's Deployment ID**
     - Auto-decode via AJAX (600ms debounce) → `vendor_decode_deploy` API
     - If decryptable: org name and domain auto-fill, green status indicator shows decoded values
     - If legacy UUID: error message shown, vendor enters fields manually
   - Enter Expiry Date, Max Domains
   - Click **Save License** → stored in secure vault

3. **License Tracking Table**:
   - Full list of all generated licenses with: License ID, Client, Domain, Type, Created, Expires, Remaining (live 1s countdown), Actions
   - Actions: Download JSON, Download PEM, Edit, Verify, Delete
   - Sorted by created_at descending (newest first)

4. **Download (with auto-signing)**:
   - `vendor_download` API reads the license from the vault
   - If a private key is present → `vendor_sign_payload()` builds signing string and signs with RSA-SHA256
   - If no private key → payload without signature field (unsigned)
   - JSON or PEM format selectable

5. **Verify modal**:
   - 8 integrity checks:
     - License ID Format, Client Name, Domain Name, Deployment ID
     - Deployment ID Decryptable (warns if legacy UUID)
     - Deployment ID Matches (warns if decrypted org/domain differs from license fields)
     - Expiry Date, Expiry Status, Max Domains
     - RSA-SHA256 Signature (only if private key present)

6. **Edit modal**:
   - Modify all license fields and status (active / expired / revoked)
   - Saves to licenses.json in secure vault

### Option B — PowerShell Scripts

### Issue new license
- optionally bootstrap env first with `Initialize-Vendor-License-Environment.ps1`
- run `Issue-License.ps1`
- provide client name, domain, expiry, and now **deployment_id** and **max_domains**
- script prompts for output format: `json` or `pem`
- script writes signed payload to `dist_release_lic/`

### Renew license
- run `Renew-License.ps1`
- provide domain, new expiry, client name, deployment_id
- script prompts for output format: `json` or `pem`
- script writes a renewal JSON payload to `dist_release_lic/`

### Signing engine
- `core/generator.php`

Key resolution order:
- `--private-key`
- `ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH`
- local fallback `vault/private_key.pem`

Key generation rule:
- automatic keypair generation now requires explicit `--allow-keygen`
- do this only on a controlled vendor workstation

### Generator + PowerShell relationship

```
Issue-License.ps1
    │  user prompts → collects client info
    │  calls: & $PHP_PATH core\generator.php --allow-keygen ...
    ▼
core/generator.php  (signing engine)
    │  checks vault/private_key.pem → missing? auto-create with --allow-keygen
    │  builds signing string → signs with RSA-SHA256 → outputs signed JSON
    ▼
Issue-License.ps1  saves JSON to dist_release_lic/
```

`generator.php` = engine, `Issue-License.ps1` = wrapper. Generator directly CLI diye-o use kora jay.

### One private key signs all licenses

`vault/private_key.pem` — **1 ta key** diye **unlimited license** sign kora jay. Same key, different license data → completely different signature. Forge kora impossible.

### ⚠️ Key rotation = all old licenses invalid

New keypair generate korle (`--allow-keygen`):

| Before | After |
|--------|-------|
| License_A old key signed ✅ | License_A new public key diye verify fail ❌ |
| License_B old key signed ✅ | License_B new public key diye verify fail ❌ |
| — | All client server e new `config/license_public.pem` deploy korte hobe |
| — | All client er jonno new license re-issue korte hobe |

### If you MUST rotate keys (3 steps)

```powershell
# Step 1: Generate new keypair
php scripts/license_admin_templates/core/generator.php --allow-keygen

# Step 2: Copy new public key to EVERY client server
#   config/license_public.pem → replace at each client's config/

# Step 3: Re-issue licenses for ALL clients
.\scripts\license_admin_templates\Issue-License.ps1  # for each client
```

### Current signing string (8 fields, ALL UPPERCASE, pipe-delimited)

The generator signs the following string:

```
$signingString = strtoupper(implode('|', [
    license_id,
    product_name,
    issued_to,
    domain_name,
    deployment_id,       // ← Added: encrypted org|domain binding
    expires_on,
    issued_at,
    max_domains,         // ← Added: domain cap (optional, backward compatible)
]));
```

The verifier (`license_verify_signature()` in `license_service.php`) tries:
1. Without `max_domains` (backward compatibility for older licenses)
2. With `max_domains` (newer licenses that include this field)

### Fields in a signed license JSON

```json
{
    "license_id": "LIC-20260609-4592",
    "product_name": "AccessPilot",
    "issued_to": "Acme Corporation",
    "domain_name": "acme.com",
    "deployment_id": "a1b2c3d4...:8a9b0c1d...",
    "expires_on": "2027-06-18",
    "issued_at": "2026-06-09",
    "max_domains": 5,
    "signature": "a1b2c3d4...344 chars of base64..."
}
```

### PEM format

When downloaded as PEM, the JSON is base64-encoded and wrapped:

```
-----BEGIN LICENSE-----
{base64 of the JSON, wrapped at 64 chars per line}
-----END LICENSE-----
```

## 5. Customer Activation Workflow

1. customer runs `scripts/powershell/KEymasterConfigPro.ps1`
2. customer enters Organization Name and Primary Domain in System Configuration
3. system generates encrypted Deployment ID via AES-256-CBC
4. customer sends Deployment ID + Domain to vendor
5. vendor pastes Deployment ID → auto-decrypts to org + domain
6. vendor issues signed license (via web UI or PowerShell)
7. customer applies JSON through the License page
8. application verifies signature, binding (domain + product + deployment ID), and expiry

## 6. Delivery Checklist

Before delivering to a client:
- confirm IIS will point to `public/`
- confirm `config/license_public.pem` is present
- confirm no vendor private key is included in the delivery package
- confirm `config/storage.php` uses the intended storage anchors
- confirm `config/app.php` has `encryption_key` set (same for Deployment ID encryption)
- confirm `App_Data/` contains only intended runtime bootstrap files
- confirm `dist_release_lic/` does not contain licenses from other customers

## 7. Recommended Hardening

- move vendor private key storage out of the repo
- restrict issuance tooling to a vendor-only workstation or vault-backed service
- rotate the keypair if the private key has ever been exposed beyond vendor control
- keep customer-specific license files out of shared source archives
- ensure `encryption_key` in `config/app.php` is unique per deployment — same key encrypts and decrypts Deployment IDs
