# Release Plan — GitHub Publishing (Public + Internal)

Checked box = done. This file is the operating manual for the release.

---

## A. Package structure (bot seeded at `/app/accesspilot/github-release/`)

```
github-release/
├── PLAN.md                     ← this file
├── MANIFEST_PUBLIC.md          ← rsync exclusion rules for the public repo
├── templates/                  ← sanitized configs + license
│   ├── .env.example
│   ├── config_app_public.php
│   ├── license_public.php
│   └── LICENSE
├── public-docs/                ← generated, client-facing
│   ├── README.md
│   ├── LIFECYCLE.md
│   ├── FEATURES.md
│   ├── SECURITY.md
│   └── ARCHITECTURE.md
└── scripts/
    ├── build_public_repo.sh
    ├── build_internal_repo.sh
    └── sanitize_configs.sh
```

## B. Repository separation (the core rule)

| Asset | Git repo (remote) | Vault (never committed) |
|-------|-------------------|-------------------------|
| Application source | both Public + Internal | — |
| Config templates (no secrets) | Public | — |
| Real config with secrets | — | `/data/secure` (Linux) / AppData vault (Win) |
| Client-facing docs + **APPLICATION_BOOK.md** | Public | — |
| Internal docs / AGENTS / DEVO_GUIDELINES | Internal ONLY | — |
| **Vendor license tooling (`scripts/license_admin_templates/`)** | **NEVER — vendor revenue asset** | Internal / vendor machine |
| License public key | Public | — |
| License private signing key | — | Internal vendor console / `scripts/license_admin_templates/vault` |

`git` must NEVER contain: `<your-org>` strings, real CMs/WGBD domain, real admin DNs,
the seeding password, the encryption key or the deployment ID. (Gate below.)

## C. Build sequence

1. `bash github-release/scripts/sanitize_configs.sh <public-out>` — replace real tokens with placeholders. [x — written]
2. `bash github-release/scripts/build_public_repo.sh` — rsync src → `/opt/git-accesspilot/accesspilot`; restore `config/license_public.pem`; sanitize; add client docs. [x — written, not yet run]
3. `bash github-release/scripts/build_internal_repo.sh` — full tree (excludes vault/`.env`/archives) → `/opt/git-accesspilot/accesspilot-internal`. [x — written, not yet run]

## D. Secret gate (RUN BEFORE ANY push — exact commands)

```bash
cd /opt/git-accesspilot
# 1. Hard-cleans: org identity, personal contacts, AD names, API endpoints.
#    `accesspilot@123` (default admin, single-use, documented in docs/client) is INTENDED.
grep -RInE "(wgbd|whildc|Welcome123!|rakibcse47|rkbzix|1955-653548|b7e6d48e9c10a|40fabf5491b9c596|whrmsapi|waltonbd|Walton)" \
  accesspilot accesspilot-internal 2>/dev/null \
  | grep -vE "CHANGE_ME|example|\.env\.example|FIRST_LOGIN_AND_SECURITY" \
  || echo "GATE 1 CLEAN"

# 2. Config fingerprint — constants / domain JSONs
grep -RInE "execution_mode\s*=>|ldap_server|bind.*password|DOMAIN_KEY" accesspilot/config accesspilot-internal/config || echo "GATE 2 CLEAN"

# 3. Key material / env / vault files (license_public.pem is EXPECTED public)
find accesspilot accesspilot-internal -type f \( -name "*.pem" -o -name "*.key" -o -name "*.pfx" -o -name "*.env" \) \
  ! -path "*/license_public.pem" 2>/dev/null || echo "GATE 3 CLEAN"

# 4. Internal network IPs (real infra) — must be CLEAN on the PUBLIC repo
grep -RInE "\b(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)" accesspilot \
  --include='*.php' --include='*.ps1' --include='*.js' --include='*.json' \
  | grep -vE "public/vendor|config/ui" || echo "GATE 4 CLEAN"
```

## E. Docs generation (done via public-docs/)

- README / LIFECYCLE / FEATURES / SECURITY / ARCHITECTURE / **APPLICATION_BOOK** written. [x]
- **Public docs POLICY: client/application ONLY** — no backend/implementation disclosure
  (no LDAP/PowerShell/WinRM/Kerberos, PHP internals, file-vault mechanics, endpoint names,
  or "how it's built" details). Technical blueprints live in `github-release/internal-docs/`
  → internal repo only. [x]
- **docs/client** ships as a SCRUBBED staging copy (`github-release/client-docs-clean/`)
  meeting `FEATURE_DOCUMENT_RULES.md`. Keep the staging copy in sync whenever source docs change. [x]
- **No dev .md in public** — `app/**`, `config/**`, `scripts/**`, `docker/deploy/`
  markdown excluded via MANIFEST_PUBLIC.md. [x]
- docs/client/ live-copied; README.md at repo root is public-docs/README.md; APPLICATION_BOOK.md is the start-to-end page/button guide. [x — build step]

## F. Push rules

- Public remote: the new `accesspilot` repo (this is the deliverable).
- Internal remote: `accesspilot-internal` — private, same team, must ALSO clear the gate.
- Do NOT add real secrets as cloud/env vars on any CI that clones these repos.

## G. Registry of real values to keep OUT of git (memorize, then rotate if leaked)

| Value | Where it must live |
|-------|--------------------|
| `Welcome123!` (seed pw) | vault only — proceed to change in UAT |
| `b7e6d4…c9d0` (encryption key) | vault only |
| `40fabf…d95e9` (deployment_id) | vault only |
| WGBD AD domain / `wgbd.com` | deployment JSON in vault |
| `DC=whildc,DC=com` base DN | deployment JSON in vault |
| `rakibcse47@gmail.com` / RKBZIX / phone | license.php real copy in vault (public gets CHANGE_ME) |
| `accesspilot@123` (docker admin) | **INTENTIONAL** — default admin, single-use, documented in docs/client — deployers MUST change on first login |
| HRMS API (`whrmsapi.waltonbd.com`) | Public gets `hrms.example.com` placeholder |

## H. Remaining steps

- [ ] Run `sanitize_configs.sh` on a scratch copy and eyeball the diff.
- [ ] Run both build scripts → `/opt/git-accesspilot` (publishes the tree).
- [ ] Run the section D secret gate — confirm `CLEAN` three times.
- [ ] `git status` review in both repos (no unintended strays).
- [ ] Commit public first, push to the public repo; then internal.
- [ ] Add `MANIFEST_PUBLIC.md` enforcement comment to team AGENTS (root).
- [ ] OPTIONAL: add a pre-push git hook (Section D) to the internal repo.