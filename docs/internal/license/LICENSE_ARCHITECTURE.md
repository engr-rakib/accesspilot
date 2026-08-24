# License Architecture

## 1. Purpose

AccessPilot uses a signed certificate model with encrypted identity binding.

The design intent is:
- vendor generates license certificates
- customer deployment only verifies them
- runtime identity and signed entitlement must match
- Deployment ID cryptographically binds org identity to the license (AES-256-CBC)

## 2. Live Application Components

### Verification service
- `app/Domain/Licensing/license_service.php`

### License apply API (client side)
- `app/Application/Http/Controllers/license_api.php`

### Vendor license API (server-side issuance, web UI)
- `app/Application/Http/Controllers/vendor_license_api.php`

### Helpers (encryption, domain helpers)
- `app/Application/Support/helpers.php`

### Organization Setup (drives Deployment ID generation)
- `app/Application/Http/Controllers/system_config.php`

### Public key
- `config/license_public.pem`

### License config
- `config/license.php`

### Application config (org_name, domain_name, deployment_id, encryption_key)
- `config/app.php`

### Default active license state path
- `C:/inetpub/Desk_secure_files/license_state.json`

### Runtime identity metadata
- `C:/inetpub/Desk_secure_files/accesspilot_deployment_identity.xml`

### Vendor licenses vault (web UI)
- `C:/inetpub/Desk_secure_files/vendor_licenses/licenses.json`

### Vendor private key (web UI)
- `C:/inetpub/Desk_secure_files/vendor_licenses/private_key.pem`

## 3. Signed Payload Structure

The generator produces JSON with these 9 fields:

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

### Signing string construction (generator.php / vendor_build_signing_string)

The string is UPPERCASE, pipe-delimited:

```
LIC-20260609-4592|ACCESSPILOT|ACME CORPORATION|
ACME.COM|A1B2C3D4...|2027-06-18|2026-06-09|5
```

Fields in order:
1. `license_id`
2. `product_name`
3. `issued_to`
4. `domain_name`
5. `deployment_id` (AES-256-CBC encrypted string)
6. `expires_on`
7. `issued_at`
8. `max_domains` (optional — backward compatible)

### Signature verification (license_service.php → license_verify_signature)

The verifier reconstructs the signing string from the received fields and uses `openssl_verify()` with the public key:

1. Build `$baseParts` with fields 1–7
2. Try verification WITHOUT `max_domains` (for pre-max_domains licenses)
3. If that fails AND `max_domains` is present, try WITH `max_domains`
4. Return `true` if either succeeds

This dual-path ensures backward compatibility.

## 4. Deployment ID Encryption (AES-256-CBC)

### Why
The Deployment ID is not a random UUID. It is encrypted `org_name|domain_name` so that:
- The vendor can paste it → decrypt → auto-fill the issue form
- The client cannot tamper with org/domain without breaking the ID
- The ID is bound to both the client's config AND the vendor's license

### How
```php
// Encryption (in system_config.php → sync_shared_config)
encrypt_deployment_data("Acme Corporation|acme.com")
// → "a1b2c3d4...:8a9b0c1d..."

// Decryption (in vendor_license_api.php → vendor_decode_deploy)
decrypt_deployment_data("a1b2c3d4...:8a9b0c1d...")
// → "Acme Corporation|acme.com"
```

### Format
`hex(16-byte IV) : hex(ciphertext)`

### Key derivation
`SHA-256(config/app.php → encryption_key)`, truncated to 32 bytes.

### Legacy compatibility
Old UUID-format Deployment IDs (e.g. `b2cdb7ad-6015-6828-38dd-1d28f4b65f71`) return `null` from `decrypt_deployment_data()`. The system handles this gracefully — vendor enters org/domain manually.

## 5. Binding Rules

The certificate is valid only when ALL THREE match:

| Check | License Field | Runtime Source |
|:---|:---|:---|
| Product | `product_name` | `get_app_name()` = "AccessPilot" |
| Domain | `domain_name` | `config/app.php` → `domain_name` |
| Deployment ID | `deployment_id` (verbatim) | `config/app.php` → `deployment_id` |

Runtime values are derived from:
1. `config/app.php` (highest priority — populated by System Configuration form)
2. `accesspilot_deployment_identity.xml` (fallback for domain only)

### What happens on mismatch

| Mismatch | Portal Behavior |
|:---|:---|
| Invalid signature | Status = `invalid_signature`, fully restricted |
| Domain mismatch | Status = `invalid_binding`, message says which field |
| Product mismatch | Status = `invalid_binding`, message says which field |
| Deployment ID mismatch | Status = `invalid_binding`, "Certificate deployment ID mismatch." |
| All pass | Status = `active`, `warning`, or `grace_period` depending on expiry |

## 6. Status Model

### Active
- valid signature
- valid binding (domain + product + deployment ID)
- not expired

### Warning
- valid and active
- within configured warning window (default 90 days)

### Grace period
- expired
- still within hardcoded 7-day grace window
- full write access maintained

### Restricted
- missing certificate
- invalid signature
- invalid binding
- or expired beyond grace period

## 7. Runtime Enforcement

When restricted:
- users may still access the interface
- write operations are blocked
- license renewal remains possible

This behavior is enforced primarily through:
- `public/api/index.php` (gate checks `license_is_restricted()`)
- `license_service.php` → `license_is_restricted()`

## 8. Vendor Tooling

### PowerShell scripts
- `Issue-License.ps1` (also supports PEM output on prompt)
- `Renew-License.ps1` (also supports PEM output on prompt)
- `core/generator.php` (signing engine)
- `vault/private_key.pem`

### Web UI (Vendor Console at `index.php?page=vendor_license`)
- Generate Issue/Renew licenses with auto-decode from Deployment ID
- License tracking table with live countdown timer (1s refresh)
- Download signed JSON or PEM (auto-signed if private key present)
- Edit/Verify/Delete actions
- Signing Key card (upload RSA private key, view status, delete)

### Vendor License API (11 endpoints)
All in `vendor_license_api.php`:

| Endpoint | Method | Purpose |
|:---|:---|:---|
| `vendor_list` | GET | List all licenses |
| `vendor_get` | GET | Get single license by ID |
| `vendor_save` | POST | Issue or renew a license |
| `vendor_update` | POST | Edit license fields |
| `vendor_delete` | POST | Delete a license |
| `vendor_download` | GET | Download as JSON or PEM (auto-signed) |
| `vendor_verify` | POST | Run 8 integrity checks |
| `vendor_key_status` | GET | Check if private key is configured |
| `vendor_save_key` | POST | Upload RSA private key |
| `vendor_delete_key` | POST | Delete private key |
| `vendor_decode_deploy` | GET | Decrypt Deployment ID → org + domain |

### Signing in the web UI
- `vendor_sign_payload()` builds the same signing string as `generator.php`
- Signs with `openssl_sign()` using the uploaded private key
- If no private key, signature field is omitted (unsigned download)
- Private key stored at `{secure}/vendor_licenses/private_key.pem`

The customer deployment must never receive the private key.

### One key, unlimited licenses

1 ta `private_key.pem` diye **unlimited license** sign kora jay. Same key, different license data → different signature. Forge-proof.

### ⚠️ Key rotation impact

If `generator.php --allow-keygen` is run (new keypair):
- **All old licenses fail** — old signatures don't match new public key
- **Remediation:** Update `config/license_public.pem` on every client + re-issue every license

Current generator hardening:
- vendor PHP path can be supplied through `ACCESSPILOT_VENDOR_PHP`
- private key path can be supplied through `ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH`
- public key path can be supplied through `ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH`
- automatic keypair creation is disabled unless `--allow-keygen` is passed explicitly

## 9. Operational Rule

Identity is established first on the customer server (Phase 2.5 — Organization Setup).
Entitlement is generated second by the vendor (Phase 3 — issue signed license).
Activation succeeds only if entitlement matches identity (Phase 4 — verify all 3 bindings).

## 10. Two Cryptographic Layers Summary

| Layer | Algorithm | Key | Purpose |
|:---|:---|:---|:---|
| License signing | RSA-SHA256 | Private/Public keypair (2048-bit) | Prevents forgery — vendor signs, client verifies |
| Deployment ID | AES-256-CBC | Symmetric key (32 bytes, from `encryption_key`) | Encrypts org identity — same server encrypts and decrypts |
