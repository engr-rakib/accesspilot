# Vendor Guideline Index

This folder is the vendor-side operating manual for AccessPilot license issuance and deployment.

Read in this order (available at `docs/license/`):

1. `docs/license/LICENSE_ARCHITECTURE.md`
2. `docs/license/DEPLOYMENT_ORDER.md`
3. `docs/license/VENDOR_SECURITY_AND_DEPLOYMENT.md`

Scope:
- how the customer deployment establishes runtime identity
- how the vendor generates signed license JSON (PowerShell scripts + web UI Vendor Console)
- how the customer applies the license through the portal
- how to handle vendor-side private key material safely
- how to bootstrap a reusable vendor signing environment
- how Deployment ID encryption (AES-256-CBC) binds org identity to the license

Important:
- the customer application verifies licenses; it does not generate them
- the vendor private key must never be shipped with a client deployment
- current application storage config is `config/storage.php`, not `config/data_mapping.php`
- vendor environment bootstrap script: `../Initialize-Vendor-License-Environment.ps1`
- Deployment ID is now AES-256-CBC encrypted `org_name|domain_name` — NOT a random UUID
- The same Deployment ID is used in both `config/app.php` and the license certificate; they must match

Troubleshooting — Common Issues:

1. **openssl_pkey_new failed / check openssl.cnf**
   PHP on Windows requires `OPENSSL_CONF` environment variable pointing to a valid `openssl.cnf`.
   The PowerShell scripts (`Issue-License.ps1`, `Renew-License.ps1`) and `generator.php` auto-detect it at
   `{PHP_ROOT}\extras\ssl\openssl.cnf`. If running directly from CLI, set it manually:
   ```powershell
   $env:OPENSSL_CONF = "C:\php8.5.4_nts\extras\ssl\openssl.cnf"
   ```

2. **"Signed license certificate verification failed" after applying license**
   Cause: The public key (`config/license_public.pem`) on the application server does NOT match
   the private key used during license generation.
   Check: Compare timestamps — `config/license_public.pem` must have the same LastWriteTime
   as `scripts/license_admin_templates/vault/private_key.pem`.

2. **"Signed license certificate verification failed" after applying license**
   Cause: The public key (`config/license_public.pem`) on the application server does NOT match
   the private key used during license generation.
   Check: Compare timestamps — `config/license_public.pem` must have the same LastWriteTime
   as `scripts/license_admin_templates/vault/private_key.pem`.
   Fix: Run `--allow-keygen` (or delete both key files and regenerate) to produce a matching pair.
   If the public key was not written (e.g. OPENSSL_CONF missing), extract it manually:
   ```powershell
   $env:OPENSSL_CONF = "C:\php8.5.4_nts\extras\ssl\openssl.cnf"
   php -r "$k=file_get_contents('scripts/license_admin_templates/vault/private_key.pem');
           $r=openssl_pkey_get_private($k);
           $d=openssl_pkey_get_details($r);
           file_put_contents('config/license_public.pem', $d['key']);"
   ```

3. **"Missing license field: expires_on" when applying license**
   The `expires_on` value could not be parsed by PHP's `strtotime()`. Ensure the date is in
   `YYYY-MM-DD` format and the JSON has no BOM (Byte Order Mark). Save files without BOM:
   ```powershell
   [System.IO.File]::WriteAllText($path, $json, [System.Text.UTF8Encoding]::new($false))
   ```

4. **"Certificate domain mismatch" error**
   The license `domain_name` field must match the application's active domain (`get_domain_name()`).
   Check active domain on the application server:
   ```powershell
   php -r "require 'app/Ldap/Config/ldap_config_repository.php'; echo get_domain_name();"
   ```

5. **"Certificate deployment ID mismatch" error**
   The license `deployment_id` field must match `config/app.php` → `deployment_id`.
   The Deployment ID is AES-256-CBC encrypted `org_name|domain_name`. If the client regenerated
   their org/domain after the license was issued, the IDs will differ and a new license is needed.
   To check the runtime Deployment ID:
   ```powershell
   php -r "require 'app/Application/Support/helpers.php'; echo get_deployment_id();"
   ```

6. **License `max_domains` != 0 but domain count equals limit (domain CRUD blocked)**
   `ldap_upsert_domain()` blocks adding new domains when `count(domains) >= max_domains`.
   Generate a new license with a higher `--max-domains` value to lift the cap.

7. **Deployment ID paste does not auto-fill in Vendor Console**
   If the Deployment ID is a legacy UUID (not AES-256-CBC hex:hex format), the auto-decode
   endpoint (`vendor_decode_deploy`) returns `"Could not decode"`. The vendor must enter org
   name and domain manually. The license will still work — only auto-fill is affected.

8. **License validates on submit but shows INVALID_SIGNATURE on page reload**
   Cause: The `max_domains` field is part of the signing string. If the server-side
   verification code (`license_verify_signature()` in `license_service.php`) builds a
   signing string that differs between `license_validate_certificate_payload()` (apply)
   and `license_get_status()` (re-verify on reload), the signature fails on page refresh.
   Root: `license_get_status()` was once missing `max_domains` in `$signedFields`, causing
   a different signing string in the re-verification path.
   Fix (app-side): Ensure `$signedFields` in `license_get_status()` includes every field
   that was part of the original signing string (see `$baseParts` + conditional
   `max_domains` in `license_verify_signature()`). The verifier now tries both with and
   without `max_domains` for backward compatibility.
   When adding new signing fields in the future, update ALL call sites that build
   `$signedFields` — especially `license_validate_certificate_payload()` (apply) AND
   `license_get_status()` (re-verify). Otherwise the license will appear valid on submit
   but invalid after reload.

Key paths reference:
- Generator: `scripts/license_admin_templates/core/generator.php`
- Vault (private key): `scripts/license_admin_templates/vault/private_key.pem`
- Public key (application-side): `config/license_public.pem`
- License output (PowerShell): `dist_release_lic/license_{domain}.json`
- License state (applied): `{ACCESSPILOT_SECURE_BASE_PATH}/license_state.json`
- Vendor licenses library (web UI): `{ACCESSPILOT_SECURE_BASE_PATH}/vendor_licenses/licenses.json`
- Vendor Console page: `index.php?page=vendor_license`
- Deployment ID encryption: `helpers.php` → `encrypt_deployment_data()` / `decrypt_deployment_data()`
