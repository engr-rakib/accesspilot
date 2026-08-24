# Deployment Order

**Product:** AccessPilot (UM-Portal)  
**Model:** Signed Certificate Handshake with Encrypted Identity Binding

This is the correct deployment and activation order for the current codebase.

## Phase 1: Deploy the Application

1. Deploy the repository contents to the target server.
2. Point IIS to `public/` as the web root.
3. Ensure these paths are available or can be created:
   - `C:/inetpub/Desk_secure_files`
   - `C:/access_pilot_logs`

## Phase 2: Establish Runtime Identity on the Client Server

Run:
- `scripts/powershell/KEymasterConfigPro.ps1`

This script collects and stores:
- `Domain`
- `BaseDN`
- `DefaultPassword`
- `AppName`
- `BaseLogPath`
- `AdminCredential`

Output:
- `C:/inetpub/Desk_secure_files/accesspilot_deployment_identity.xml`

This XML is the deployment identity anchor used by the live application.

## Phase 2.5: Organization Setup (System Configuration)

1. Admin logs in to the portal → `index.php?page=system_config`
2. Under **Organization Setup**, fill:
   - **Organization Name** (e.g. "Acme Corporation")
   - **Primary Domain** (e.g. "acme.com")
3. Click **Register**

Behind the scenes:
- `config/app.php` is written with `org_name`, `domain_name`, and `deployment_id`
- The **Deployment ID** is NOT a random UUID — it is `AES-256-CBC encrypted "{org_name}|{domain_name}"`
- Format: `hex(IV):hex(ciphertext)` (e.g. `a1b2c3d4...:8a9b0c1d...`)
- If org or domain changes later, the Deployment ID is **regenerated** automatically
- Old licenses with the old Deployment ID will fail binding and require re-issuance

The Deployment ID must be shared with the vendor to generate a license.

## Phase 3: Vendor Issues Signed License

The vendor must collect from the client deployment:
- `Deployment ID` (AES-256-CBC encrypted string)
- `Domain`
- `AppName`

### Option A — Web UI (Vendor Console)

1. Open `index.php?page=vendor_license`
2. Paste the **Deployment ID** — auto-decrypts to fill org name and domain
3. Set Expiry Date and Max Domains
4. Click **Save License** → stored in secure vault
5. **Download JSON** or **Download PEM**
   - If private key is uploaded via Signing Key card → license is **RSA-SHA256 signed** server-side
   - If no private key → unsigned payload (sign via PowerShell locally)

### Option B — PowerShell Scripts

Run:
- `scripts/license_admin_templates/Issue-License.ps1`
- or `scripts/license_admin_templates/Renew-License.ps1`

Those scripts call:
- `scripts/license_admin_templates/core/generator.php`

Output:
- signed JSON certificate written under `dist_release_lic/`
- PEM format available on prompt (`json` / `pem`)

### Signing string (8 fields, all UPPERCASE, pipe-delimited)

```
LIC_ID|PRODUCT|CLIENT|DOMAIN|DEPLOY_ID|EXPIRES|ISSUED_AT|MAX_DOMAINS
```

The verifier tries both **with and without** `max_domains` for backward compatibility.

## Phase 4: Customer Applies License

1. Log in to the portal.
2. Open `index.php?page=license` (License Center).
3. Paste the signed JSON (or upload `.pem` file via Sync Material card).
4. Submit through the License Center UI.

The live application then verifies:
1. RSA-SHA256 signature (tries with and without `max_domains`)
2. `domain_name` in JSON matches runtime `Domain`
3. `product_name` in JSON matches runtime `AppName`
4. `deployment_id` in JSON matches runtime Deployment ID from `config/app.php`
5. expiry and grace-period rules

If all checks pass, the deployment becomes active.

## Phase 5: Validate Runtime Health

After activation:
- open System Configuration
- confirm storage status shows writable connectivity
- confirm the License page reports a valid active or warning state
- confirm the live countdown timer shows remaining operational window

## Runtime Truth Sources

| Component | Current Source of Truth |
| :--- | :--- |
| Deployment identity | `C:/inetpub/Desk_secure_files/accesspilot_deployment_identity.xml` |
| Organization name, domain, Deployment ID | `config/app.php` |
| Secure/log storage roots | `config/storage.php` |
| Active license state | `C:/inetpub/Desk_secure_files/license_state.json` |
| Signature verification key | `config/license_public.pem` |
| Deployment ID encryption key | `config/app.php` → `encryption_key` (SHA-256 → AES-256) |

## Verification Binding (3 checks)

| Check | Runtime Source | License Field |
| :--- | :--- | :--- |
| Product name | `get_app_name()` = "AccessPilot" | `product_name` |
| Domain name | `config/app.php` → `domain_name` | `domain_name` |
| Deployment ID | `config/app.php` → `deployment_id` | `deployment_id` |

All three must match exactly, or the license is restricted.

## Notes

- The application reads the current license state from `config/license.php` default path `C:/inetpub/Desk_secure_files/license_state.json`
- The vendor should never ship `vault/private_key.pem` with a customer package
- Legacy UUID-format Deployment IDs still work but cannot be auto-decoded on the vendor form
- The private key can be uploaded server-side via the Vendor Console Signing Key card for automatic signing on download

## ⚠️ Key Rotation Warning

`vault/private_key.pem` — 1 ta key diye **sob license sign** kora hoy. Key rotate korle:

1. **Old licenses fail** — all existing signed licenses become invalid
2. **Public key mismatch** — all client servers need new `config/license_public.pem`
3. **All clients need re-issue** — every client needs a new signed license

### Safe rule
- Do NOT run `--allow-keygen` for daily license issuance
- Key rotate only when absolutely necessary (key compromised)
- Before rotation: ensure all clients can be reached for public key + license update
