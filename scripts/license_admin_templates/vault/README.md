# Vault

This directory stores the vendor's **RSA private key** (`private_key.pem`) and **environment config** (`vendor-env.local.ps1`).

---

## What is private_key.pem?

Eta **2048-bit RSA private key** — vendor er most sensitive file. Ei key diye **sobok license sign** kora hoy. Ekta key diye unlimited license sign kora jay (same key, different license data → different signature).

---

## Who creates this key?

| Trigger | Key created? |
|---------|-------------|
| `Issue-License.ps1` first run (passes `--allow-keygen`) | ✅ Auto-create |
| `core/generator.php --allow-keygen` | ✅ Direct create |
| `Issue-License.ps1` 2nd+ run (key already exists) | ❌ Skip |
| `core/generator.php` without `--allow-keygen` & no key | ❌ Error |

**Engine code** (`core/generator.php`):
```php
$res = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($res, $privateKey);
$pub = openssl_pkey_get_details($res)["key"];
file_put_contents('vault/private_key.pem', $privateKey);
file_put_contents('config/license_public.pem', $pub);
```

---

## How generator.php and Issue-License.ps1 work together

```
Issue-License.ps1
    │  prompts user for client name, domain, expiry, etc.
    │
    ▼
core/generator.php  ← (called with --allow-keygen)
    │
    ├── 1. Check if vault/private_key.pem exists
    │       ├── No → generate new keypair (vault/ + config/)
    │       └── Yes → skip keygen
    │
    ├── 2. Build signing string from input fields
    ├── 3. Sign with RSA-SHA256 (using private key)
    └── 4. Output signed JSON → back to PS script → save to file
```

---

## Key Rotation — CRITICAL WARNING

### ⚠️ What happens if you generate a NEW keypair?

| Before rotation | After rotation |
|----------------|---------------|
| License_A (old key signed) + old public key ✅ | License_A (old key signed) + **new** public key ❌ |
| License_B (old key signed) + old public key ✅ | License_B (old key signed) + **new** public key ❌ |
| — | New License_C (new key signed) + new public key ✅ |

**ALL existing licenses become INVALID immediately.**

### Why?

Client server e `config/license_public.pem` ta old public key rakhe. New keypair generate korle:
1. `vault/private_key.pem` → **new** private key
2. `config/license_public.pem` → **new** public key

Old licenses old private key diye signed — new public key diye verify kora possible na.

### If you MUST rotate keys (3 steps):

```powershell
# Step 1: Generate new keypair
php scripts/license_admin_templates/core/generator.php --allow-keygen

# Step 2: Copy new public key to EVERY client server
#   config/license_public.pem → {client}/config/license_public.pem

# Step 3: Re-issue licenses for ALL clients
#   .\scripts\license_admin_templates\Issue-License.ps1  (for each client)
```

### Safe rule

- **Do NOT** run `--allow-keygen` casually
- **Do NOT** rotate keys unless absolutely necessary (key compromised, etc.)
- Daily license issue er jonno always **same keypair** use korun

---

## Commands Reference

```powershell
# Generate keypair only (no license issued)
php scripts/license_admin_templates/core/generator.php --allow-keygen

# Issue a new license (auto-creates key if missing)
.\scripts\license_admin_templates\Issue-License.ps1

# Renew a license
.\scripts\license_admin_templates\Renew-License.ps1

# Run generator directly with all options
php scripts/license_admin_templates/core/generator.php ^
    --id="LIC-20260609-1234" ^
    --product="AccessPilot" ^
    --client="Acme Corp" ^
    --domain="acme.com" ^
    --deployment-id="a1b2c3..." ^
    --expiry="2027-06-18" ^
    --max-domains="5" ^
    --allow-keygen
```
