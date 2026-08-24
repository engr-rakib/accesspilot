# AccessPilot License System — A–Z Guide

**Easy-language breakdown of how licenses work from start to finish.**

---

## Table of Contents

1. [What Is This License System?](#1-what-is-this-license-system)
2. [The Two Sides: Vendor & Client](#2-the-two-sides-vendor--client)
3. [Keys — The Core of Security](#3-keys--the-core-of-security)
4. [Algorithm: RSA-SHA256](#4-algorithm-rsa-sha256)
5. [Algorithm: AES-256-CBC (Deployment ID)](#5-algorithm-aes-256-cbc-deployment-id)
6. [Step 1: Client Deploys the Application](#6-step-1-client-deploys-the-application)
7. [Step 2: Organization Setup — Identity is Born](#7-step-2-organization-setup--identity-is-born)
8. [Step 3: Vendor Generates a License](#8-step-3-vendor-generates-a-license)
9. [Step 4: Client Applies the License](#9-step-4-client-applies-the-license)
10. [Step 5: Verification — What Happens Inside](#10-step-5-verification--what-happens-inside)
11. [Step 6: Runtime — Daily Operation & Expiry](#11-step-6-runtime--daily-operation--expiry)
12. [Configuration Files Reference](#12-configuration-files-reference)
13. [Common Questions](#13-common-questions)

---

## 1. What Is This License System?

The license system is a **digital signing and verification system**. It ensures that only authorized deployments can run AccessPilot. Think of it like a passport:

- The **vendor** (you) issues the passport (license file)
- The **client** shows the passport to the application
- The application checks the passport's **signature** and **validity**

The system uses two cryptographic techniques:

| Technique | Purpose |
|-----------|---------|
| **RSA-SHA256** | Signs the license so nobody can forge it |
| **AES-256-CBC** | Encrypts the client's identity inside the Deployment ID |

---

## 2. The Two Sides: Vendor & Client

```
┌─────────────────────────┐         ┌─────────────────────────┐
│     VENDOR (You)        │         │     CLIENT (Customer)    │
│                         │         │                         │
│  Has the PRIVATE KEY    │         │  Has the PUBLIC KEY      │
│  Can SIGN licenses      │         │  Can VERIFY licenses     │
│  Runs PowerShell tools  │         │  Runs the web portal     │
│  Manages license files  │         │  Applies license files   │
└─────────────────────────┘         └─────────────────────────┘
         │                                      │
         │  1. Client sends: Domain name        │
         │     + Deployment ID                  │
         │◄─────────────────────────────────────│
         │                                      │
         │  2. Vendor creates signed license    │
         │                                      │
         │  3. Sends license.json ─────────────►│
         │                                      │
         │  4. Client pastes into License page  │
         │                                      │
         │  5. App verifies signature + binding │
         │                                      │
```

### What each side owns

| Item | Vendor | Client | Purpose |
|------|--------|--------|---------|
| `private_key.pem` | ✅ Has it | ❌ Never gets it | Signs licenses |
| `license_public.pem` | ✅ Has copy | ✅ Has it | Verifies licenses |
| License JSON (signed) | ✅ Creates | ✅ Applies | The actual certificate |
| Deployment ID | ❌ Gets from client | ✅ Auto-generated | Encrypted identity |

---

## 3. Keys — The Core of Security

### What is a PEM file?

PEM is a text format for cryptographic keys. It looks like this:

```
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA... (gibberish text)
...
-----END RSA PRIVATE KEY-----
```

### The two keys

#### Private Key (`private_key.pem`)

- Stored ONLY on the vendor's machine
- **Never** shared with anyone
- Used to **SIGN** license files
- If lost: cannot generate new licenses
- If stolen: anyone can forge licenses

#### Public Key (`license_public.pem`)

- Distributed WITH the application
- Stored in `config/license_public.pem`
- Used to **VERIFY** license signatures
- Cannot sign anything — only verifies
- Safe to share with anyone

### Key sizes

Both keys are **2048-bit RSA** keys. This means:
- Very strong security (industry standard)
- Signatures are 256 bytes long
- Almost impossible to crack with current computers

### How keys are generated

When you run `generator.php --allow-keygen`:

```php
$res = openssl_pkey_new([
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA
]);
```

This creates a matching pair: one private, one public. They are mathematically linked — like a lock (public) and its unique key (private).

---

## 4. Algorithm: RSA-SHA256

### What is RSA-SHA256?

It's two things combined:

1. **SHA256** — Creates a unique "fingerprint" (hash) of the license data
2. **RSA** — Encrypts that fingerprint with the private key

### The signing process (step by step)

```
License Data                      Signing String
┌──────────────┐                  ┌──────────────────┐
│ license_id   │                  │ LIC-20260609-1234│
│ product_name │  → Join with │  → │ ACCESSPILOT       │
│ issued_to    │    pipe (|)      │ ACME CORP         │
│ domain_name  │    and UPPER     │ ACME.COM          │
│ deployment_id│                  │ DEPLOY-ID-HERE    │
│ expires_on   │                  │ 2026-12-31        │
│ issued_at    │                  │ 2026-06-09        │
│ max_domains  │                  │ 5                 │
└──────────────┘                  └──────────────────┘
                                         │
                                         ▼
                                  SHA-256 Hash
                                         │
                                         ▼
                              ┌──────────────────────┐
                              │ 32 bytes of scrambled │
                              │ data (the hash)       │
                              └──────────────────────┘
                                         │
                              Encrypt with Private Key
                                         │
                                         ▼
                              ┌──────────────────────┐
                              │ RSA Signature         │
                              │ (256 bytes, binary)   │
                              └──────────────────────┘
                                         │
                              Convert to Base64 text
                                         │
                                         ▼
                              ┌──────────────────────┐
                              │ "a1b2c3...xyz=="      │
                              │ (344 chars of text)   │
                              └──────────────────────┘
```

The final Base64 signature is added to the JSON as the `"signature"` field.

### The signing string construction

From `generator.php`:

```php
$signParts = [
    $payload['license_id'],      // LIC-20260609-1234
    $payload['product_name'],    // ACCESSPILOT
    $payload['issued_to'],       // ACME CORP
    $payload['domain_name'],     // ACME.COM
    // deployment_id (only if not empty)
    $payload['expires_on'],      // 2026-12-31
    $payload['issued_at'],       // 2026-06-09
    // max_domains (only if set)
];

// Result: "LIC-20260609-1234|ACCESSPILOT|ACME CORP|..."
$signingString = strtoupper(implode('|', $signParts));
```

### The verification process (step by step)

```
Received License JSON
┌──────────────────┐
│ license_id       │
│ product_name     │
│ issued_to        │  → Rebuild the SAME signing string
│ domain_name      │
│ deployment_id    │
│ expires_on       │
│ issued_at        │
│ max_domains      │
│ signature ██████ │
└──────────────────┘
         │
         ├─── Build signing string → SHA-256 → Hash A
         │
         ├─── Take signature field → Base64 decode → Raw signature bytes
         │
         └─── Use PUBLIC KEY to decrypt signature → Hash B
                         │
                    Compare Hash A vs Hash B
                         │
              ┌──────────┴──────────┐
              │                     │
           Match!               Don't match!
         License is REAL      License is FORGED
```

From `license_service.php`:

```php
// Rebuild signing string from received fields
$baseParts = [
    $fields['license_id'],
    $fields['product_name'],
    $fields['issued_to'],
    $fields['domain_name'],
    $fields['deployment_id'],
    $fields['expires_on'],
    $fields['issued_at'],
];

// Append max_domains for newer licenses
$signingString = strtoupper(implode('|', $baseParts));
if (!empty($fields['max_domains'])) {
    $signingString .= '|' . $fields['max_domains'];
}

// Verify using public key
$result = openssl_verify(
    $signingString,
    $signature,
    $publicKey,
    OPENSSL_ALGO_SHA256
);
// $result = 1 if valid, 0 if invalid
```

### Why backward compatibility matters

Older licenses didn't include `max_domains` in the signing string. The verifier tries **both**:
1. Without `max_domains` (for old licenses)
2. With `max_domains` (for new licenses)

This is why the signature still works after upgrading.

---

## 5. Algorithm: AES-256-CBC (Deployment ID)

### What is AES-256-CBC?

A symmetric encryption algorithm — same key encrypts and decrypts. Used to hide the organization's identity inside the Deployment ID.

### Why is this needed?

When a client registers their organization:
- They enter **Organization Name** and **Primary Domain**
- The system **encrypts** `org_name|domain_name` into the Deployment ID
- When the vendor pastes this ID, the system **decrypts** it → auto-fills the form
- This prevents tampering: nobody can modify the org/domain without breaking the ID

### Encryption process

```
plaintext = "Acme Corporation|acme.com"
                │
                ▼
AES-256-CBC encryption
with server's secret key
                │
        IV (16 random bytes) + Ciphertext
                │
                ▼
Format: hex(IV) + ":" + hex(Ciphertext)
                │
                ▼
Example: "a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6:
          8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6"
```

From `helpers.php`:

```php
function encrypt_deployment_data(string $plaintext): string
{
    $key = deployment_encryption_key();  // 32-byte secret
    $iv = random_bytes(16);             // Random initialization vector
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-cbc',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    return bin2hex($iv) . ':' . bin2hex($ciphertext);
}
```

### Decryption process

```php
function decrypt_deployment_data(string $encoded): ?string
{
    $parts = explode(':', $encoded, 2);
    $iv = hex2bin($parts[0]);           // First 32 hex chars = IV
    $ciphertext = hex2bin($parts[1]);   // Rest = encrypted data
    $key = deployment_encryption_key();
    return openssl_decrypt(
        $ciphertext,
        'aes-256-cbc',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    // Returns "Acme Corporation|acme.com"
}
```

### Key derivation

```php
function deployment_encryption_key(): string
{
    $key = config_get('encryption_key', '');
    // Ensure exactly 32 bytes for AES-256
    return substr(hash('sha256', $key, true), 0, 32);
}
```

### Deployment ID format comparison

| Format | Example | Length | Decryptable? |
|--------|---------|--------|-------------|
| Old UUID | `b2cdb7ad-6015-6828-38dd-1d28f4b65f71` | 36 chars | No (legacy) |
| New Encrypted | `a1b2...:8a9b...` | ~80-100 chars | Yes → org + domain |

---

## 6. Step 1: Client Deploys the Application

### What happens on first boot

1. Application files are copied to the server
2. IIS is configured to serve from `public/`
3. The admin runs `scripts/powershell/KEymasterConfigPro.ps1`
4. This script collects:
   - Active Directory domain
   - Base DN
   - Default password policy
   - Admin credentials
5. This identity is stored in:

```
C:/inetpub/Desk_secure_files/accesspilot_deployment_identity.xml
```

Contents:
```xml
<DeploymentIdentity>
    <Domain>wgbd.com</Domain>
    <AppName>AccessPilot</AppName>
    <BaseDN>DC=wgbd,DC=com</BaseDN>
</DeploymentIdentity>
```

### Files created during deployment

| File | Purpose |
|------|---------|
| `config/app.php` | Main config — stores deployment_id, org_name, domain_name |
| `{secure}/accesspilot_deployment_identity.xml` | Runtime identity from PowerShell setup |
| `{secure}/license_state.json` | Active license state (empty until activated) |
| `config/license_public.pem` | Public key for signature verification |

---

## 7. Step 2: Organization Setup — Identity is Born

### What happens in the web portal

1. Admin logs in → goes to **System Configuration**
2. Under **Organization Setup**, fills:
   - **Organization Name** (e.g., "Acme Corporation")
   - **Primary Domain** (e.g., "acme.com")
3. Clicks **Register**

### Behind the scenes

```
User clicks Register
        │
        ▼
saveOrg() in system_config_actions.js
        │  POST { org_name, domain, base_dn }
        ▼
sync_shared_config() in system_config.php
        │
        ├── Saves org_name → config/app.php
        ├── Saves domain_name → config/app.php
        │
        └── If BOTH org_name AND domain_name are set:
                │
                ▼
                encrypt_deployment_data("Acme Corporation|acme.com")
                │
                ▼
                Stores result as deployment_id → config/app.php
                │
                ▼
                Deployment ID = "a1b2...:8a9b..." (hex:hex)
```

### The Deployment ID

After registration, the **Deployment ID** field shows the encrypted value. This ID:
- Uniquely identifies this deployment
- Secretly contains the org name and domain (encrypted)
- Cannot be forged without the server's secret encryption key
- Must be shared with the vendor to create a license

### The flow diagram

```
┌────────────────────────────────────────────┐
│         Organization Setup Card            │
│                                            │
│  Organization Name: [Acme Corporation]     │
│  Primary Domain:     [acme.com    ]        │
│                                            │
│  [Register]                                │
│                                            │
│  Deployment ID: a1b2c3d4e5f6...           │
│  (copy this and send to vendor)            │
└────────────────────────────────────────────┘
         │
         │  Client sends to Vendor:
         │  - Deployment ID (encrypted)
         │  - Domain name (for manual check)
         ▼
┌────────────────────────────────────────────┐
│         Vendor License Console             │
│                                            │
│  Paste Deployment ID: [a1b2c3d4e5f6...]   │
│                                            │
│  ✅ Decoded: Acme Corporation / acme.com   │
│                                            │
│  Client Name: [Acme Corporation] ← auto   │
│  Domain Name: [acme.com        ] ← auto   │
│  Expiry Date: [2027-06-18      ]           │
└────────────────────────────────────────────┘
```

### What if the org/domain changes later?

When the admin updates org name or domain:
- The system automatically **regenerates** the Deployment ID
- Old licenses with the old Deployment ID will **FAIL** binding check
- New licenses must be issued with the new Deployment ID

---

## 8. Step 3: Vendor Generates a License

### Two ways to generate

#### Way 1: Web UI (Vendor Console)

Go to `index.php?page=vendor_license`:

1. **Paste the client's Deployment ID**
   - Auto-decrypts → Org name and Domain auto-fill ✅
2. Set Expiry Date and Max Domains
3. Click **Save License**
   - License is stored in the secure vault
4. Click **Download JSON** or **Download PEM**
   - If private key is uploaded → license is signed automatically
   - If no private key → unsigned payload (sign via PowerShell)

#### Way 2: PowerShell (traditional)

Run on vendor's machine:

```powershell
# Load environment
. .\scripts\license_admin_templates\vault\vendor-env.local.ps1

# Issue new license
.\scripts\license_admin_templates\Issue-License.ps1
```

### What the generator.php does

This is the core signing engine. Let's trace through it:

```
1. Receive inputs:
   --id="LIC-20260609-4592"
   --product="AccessPilot"
   --client="Acme Corporation"
   --domain="acme.com"
   --deployment-id="a1b2c3d4e5f6..."
   --expiry="2027-06-18"
   --max-domains="5"

2. Build payload array:
   {
     license_id: "LIC-20260609-4592",
     product_name: "AccessPilot",
     issued_to: "Acme Corporation",
     domain_name: "acme.com",
     deployment_id: "a1b2c3d4e5f6...",
     expires_on: "2027-06-18",
     issued_at: "2026-06-09",
     max_domains: 5
   }

3. Build signing string:
   "LIC-20260609-4592|ACCESSPILOT|ACME CORPORATION|
    ACME.COM|A1B2C3D4E5F6...|2027-06-18|2026-06-09|5"

4. Read private key from file

5. Sign with RSA-SHA256:
   signature = openssl_sign(signingString, privateKey, OPENSSL_ALGO_SHA256)

6. Add signature to payload:
   payload.signature = base64_encode(signature)

7. Output signed JSON
```

### The final signed JSON

```json
{
    "license_id": "LIC-20260609-4592",
    "product_name": "AccessPilot",
    "issued_to": "Acme Corporation",
    "domain_name": "acme.com",
    "deployment_id": "a1b2c3d4e5f6...:8a9b...",
    "expires_on": "2027-06-18",
    "issued_at": "2026-06-09",
    "max_domains": 5,
    "signature": "a1b2c3d4...344 characters of base64..."
}
```

### JSON vs PEM format

| Format | Extension | Content |
|--------|-----------|---------|
| JSON | `.json` | Raw JSON with signature field |
| PEM | `.pem` | Base64-encoded JSON wrapped in `-----BEGIN LICENSE-----` / `-----END LICENSE-----` headers |

PEM example:
```
-----BEGIN LICENSE-----
ewogICAgImxpY2Vuc2VfaWQiOiAiTElDLTIwMjYwNjA5LTQ1OTIiLAogICAgInByb2R1
Y3RfbmFtZSI6ICJBY2Nlc3NQaWxvdCIsCiAgICAiaXNzdWVkX3RvIjogIkFjbWUgQ29y
...
-----END LICENSE-----
```

### Where licenses are stored (web UI)

All vendor-generated licenses are saved in the secure vault:

```
{ACCESSPILOT_SECURE_BASE_PATH}/vendor_licenses/licenses.json
```

This is a JSON array where each entry has:

```json
{
    "id": "LIC-20260609-4592",
    "product_name": "AccessPilot",
    "issued_to": "Acme Corporation",
    "domain_name": "acme.com",
    "deployment_id": "a1b2...:8a9b...",
    "expires_on": "2027-06-18",
    "issued_at": "2026-06-09",
    "max_domains": 5,
    "type": "issue",
    "status": "active",
    "created_at": "2026-06-09 10:30:00",
    "updated_at": "2026-06-09 10:30:00"
}
```

---

## 9. Step 4: Client Applies the License

### In the web portal

1. Admin goes to **License Center** (`index.php?page=license`)
2. Pastes the signed JSON (or uploads `.pem` file)
3. Clicks **Synchronize Renewal**

### Behind the scenes

```
User clicks Synchronize
        │
        ▼
applyLicenseButton click handler
        │  POST { license_input: signedJSON }
        ▼
license.php API (POST handler)
        │
        ▼
license_apply_input(signedJSON)
        │
        ├── 1. Parse the JSON
        │
        ├── 2. Validate certificate payload:
        │       ├── Check all required fields exist
        │       ├── Verify RSA-SHA256 signature
        │       └── Verify runtime binding
        │
        ├── 3. If valid:
        │       ├── Write to license_state.json
        │       ├── Log activity
        │       └── Send notification
        │
        └── 4. Return result to browser
```

### What happens if verification fails?

| Error | Meaning | Fix |
|-------|---------|-----|
| `Missing license field: signature` | JSON doesn't have signature | Generate with private key |
| `Signed license certificate verification failed` | Signature doesn't match | Public key doesn't match private key |
| `Certificate domain mismatch` | Domain in license ≠ runtime domain | Use correct domain |
| `Certificate product mismatch` | Product name wrong | Must be "AccessPilot" |
| `Certificate deployment ID mismatch` | Deployment ID in license ≠ runtime | Use correct Deployment ID |
| `Expired license` | Past expiry + grace | Get a renewal |

---

## 10. Step 5: Verification — What Happens Inside

### The verification chain

Every time the license page loads, `license_get_status()` runs:

```
license_get_status()
        │
        ├── 1. Read license_state.json
        │
        ├── 2. Check file exists?
        │       ├── No → status = "missing_certificate" → RESTRICTED
        │       └── Yes → continue
        │
        ├── 3. Verify RSA-SHA256 signature
        │       ├── Invalid → status = "invalid_signature" → RESTRICTED
        │       └── Valid → continue
        │
        ├── 4. Check runtime binding:
        │       ├── domain_name matches? → No → RESTRICTED
        │       ├── product_name matches? → No → RESTRICTED
        │       └── deployment_id matches? → No → RESTRICTED
        │                               All match → continue
        │
        ├── 5. Check expiry:
        │       ├── Past grace? → status = "expired" → RESTRICTED
        │       ├── Past expiry? → status = "grace_period"
        │       ├── Within 90 days? → status = "warning"
        │       └── All good → status = "active"
        │
        └── 6. Return full status object
```

### The 4 deployment phases

```
Phase 1: HEALTHY (Active)
├── Signature valid
├── Binding matches
├── Not expired
└── All features enabled

Phase 2: WARNING (90 days before expiry)
├── Still active
├── Renewal banners shown
└── Normal operation continues

Phase 3: GRACE (7 days after expiry)
├── Extended functionality
├── Read-only? No, still writable
└── Must renew soon

Phase 4: LOCK (expired + grace passed / no license)
├── Signature invalid or expired
├── Read-only mode
├── Data-modifying actions blocked
├── Automation routines stopped
└── APIs protected
```

### What "restricted" means

When the system is in restricted mode:
- Users can still log in and view data
- **Write operations are blocked** (create, edit, delete)
- License renewal page remains accessible
- A red banner shows "RESTRICTED" clearly

Blocked operations include:
- Creating/modifying/deleting users
- Changing passwords
- Exporting data
- Running directory operations
- Any POST request to APIs (except license API)

---

## 11. Step 6: Runtime — Daily Operation & Expiry

### Daily status checks

The license page loads → `license_get_status()` runs → status is displayed
The API gateway checks `license_is_restricted()` on every request

### Timer countdown

The browser shows a live countdown:
```
Remaining Operational Window
6 months 19 days 13 hours 30 minutes 10 seconds remaining
```

This is calculated from `expires_on + grace_days` minus current time.

### What happens at expiry

```
expires_on date
        │
        ▼
  ┌─────────────────┐
  │ Phase 2: Warning │  ← 90 days before
  │ (still active)   │
  └─────────────────┘
        │
        ▼ expires_on passes
  ┌─────────────────┐
  │ Phase 3: Grace   │  ← 7 days after expiry
  │ (still writable) │
  └─────────────────┘
        │
        ▼ grace ends
  ┌─────────────────┐
  │ Phase 4: Lock    │  ← Restricted
  │ (read-only)      │
  └─────────────────┘
```

### To restore full access

1. Vendor generates a new/renewed license
2. Client pastes it → system verifies → status returns to Active

---

## 12. Configuration Files Reference

### All files that matter

| File | What it does | Who has it |
|------|-------------|------------|
| `config/license_public.pem` | RSA public key for verification | Vendor + Client |
| `config/app.php` | Main config (org, domain, deploy_id, encryption key) | Client only |
| `config/license.php` | License paths and warning days | Client only |
| `config/storage.php` | Secure vault and log paths | Client only |
| `scripts/license_admin_templates/core/generator.php` | PHP signing engine | Vendor only |
| `scripts/license_admin_templates/vault/private_key.pem` | RSA private key for signing | Vendor ONLY |
| `{secure}/license_state.json` | Active license state | Client only |
| `{secure}/accesspilot_deployment_identity.xml` | Runtime identity from deployment | Client only |
| `{secure}/vendor_licenses/licenses.json` | All vendor-generated licenses (web UI) | Vendor only |

### Environment variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `ACCESSPILOT_SECURE_BASE_PATH` | Where all secure files live | `C:/inetpub/Desk_secure_files` |
| `ACCESSPILOT_LOG_BASE_PATH` | Where audit logs live | `C:/access_pilot_logs` |
| `ACCESSPILOT_VENDOR_PHP` | PHP executable path (vendor) | Auto-detected |
| `ACCESSPILOT_VENDOR_PRIVATE_KEY_PATH` | Private key location (vendor) | `vault/private_key.pem` |
| `ACCESSPILOT_LICENSE_PUBLIC_KEY_PATH` | Public key output (vendor) | `config/license_public.pem` |
| `OPENSSL_CONF` | OpenSSL config file (vendor) | `{PHP}/extras/ssl/openssl.cnf` |

---

## 13. Common Questions

### Q: Why two different encryption methods (RSA and AES)?

They serve different purposes:

| Method | Used for | Why not the other? |
|--------|----------|-------------------|
| **RSA-SHA256** | Signing the license | Asymmetric — vendor signs, client verifies. AES can't do this. |
| **AES-256-CBC** | Encrypting Deployment ID | Symmetric — same server encrypts and decrypts. RSA is too slow for this. |

### Q: Can the client modify the license JSON?

They can modify it, but:
1. Any change breaks the signature → verification fails
2. The system won't accept a forged license
3. They'd need the private key to re-sign

### Q: What happens if the private key is lost?

- Cannot generate new licenses
- Existing licenses still work (they're already signed)
- You'd need to create a NEW keypair and update `license_public.pem` on ALL client deployments
- All existing licenses would become invalid

### Q: What if someone steals the private key?

- They could sign fake licenses
- Response: rotate the keypair immediately
- Update `license_public.pem` on all deployments
- Re-issue all legitimate licenses

### Q: Can old (UUID) Deployment IDs still work?

Yes. The `decrypt_deployment_data()` function returns null for non-encrypted strings. The vendor UI shows a "Could not decode" message, and the vendor enters org/domain manually. The license will still work — just without the auto-fill convenience.

### Q: Key rotate korle old licenses ki hoy?

Old licenses **INVALID hoye jabe**. Kenona:
- Old license old private key diye signed
- New public key old signature verify korte parbe na
- **Solution:** Sob client server e new `config/license_public.pem` deploy + sob client er jonno new license re-issue

### Q: 1 ta private key diye koto license sign kora jay?

**Unlimited.** Same key, different license data → different signature. Ei ta asymmetric cryptography er standard behavior. CloudFlare er moto service gulao 1 ta private key diye million+ site sign kore.

### Q: generator.php vs Issue-License.ps1 — difference ki?

- `generator.php` = engine (CLI diye direct run kora jay, sob flag pass kore)
- `Issue-License.ps1` = wrapper (user prompt kore, generator call kore, file e save kore)
- `Issue-License.ps1` internally `core/generator.php` call kore `--allow-keygen` flag diye

### Q: What's the difference between Issue and Renew?

| | Issue | Renew |
|--|-------|-------|
| License ID prefix | `LIC-` | `REN-` |
| When to use | First-time license | Extending an existing license |
| Client setup | Full registration needed | Already has active license |
| Form behavior | All fields required | Pre-filled from existing |

### Q: When does the Deployment ID regenerate?

Whenever the admin clicks **Register/Update** in Organization Setup with both org name AND domain filled. The new Deployment ID will be different because:
1. AES-256-CBC uses a random IV each time
2. Even the same data produces different encrypted output

This is by design — old licenses with the old ID will be rejected, forcing renewal with the new identity.

### Q: What's the complete file path for everything?

```
VENDOR MACHINE:
  scripts/license_admin_templates/
    ├── Issue-License.ps1
    ├── Renew-License.ps1
    ├── Initialize-Vendor-License-Environment.ps1
    ├── LICENSE_A-Z.md                    ← This document
    ├── core/
    │   └── generator.php                 ← Signing engine
    ├── vault/
    │   ├── private_key.pem
    │   ├── vendor-env.local.ps1
    │   └── README.md
    ├── guideline/
    │   ├── README.md
    │   ├── DEPLOYMENT_ORDER.md
    │   ├── VENDOR_SECURITY_AND_DEPLOYMENT.md
    │   └── license/
    │       └── LICENSE_ARCHITECTURE.md
    └── README.md
  dist_release_lic/                          ← Generated licenses

CLIENT SERVER:
  config/
    ├── app.php                              ← deployment_id, org_name, etc.
    ├── license.php                          ← paths
    ├── license_public.pem                   ← Public key
    └── storage.php                          ← vault paths
  C:/inetpub/Desk_secure_files/
    ├── accesspilot_deployment_identity.xml  ← Runtime identity
    ├── license_state.json                   ← Active license
    └── vendor_licenses/licenses.json        ← Vendor-generated (web UI)

WEB PORTAL PAGES:
  index.php?page=license                     ← Client applies license
  index.php?page=system_config               ← Organization Setup
  index.php?page=vendor_license              ← Vendor generates licenses
```
