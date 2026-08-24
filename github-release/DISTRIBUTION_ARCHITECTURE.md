# AccessPilot — Distribution Architecture (CURRENT)

> **Model:** token-gated live install. Code is NEVER distributed as a downloadable
> artifact. Clients install on their live server via an installer that clones the
> private distribution repo and deletes the clone afterwards.
> *(Supersedes DUAL_REPO_BLUEPRINT.md — releases/tarballs are retired.)*

---

## 1 · The three repositories

```
┌──────────────────────────────────────────┐
│ PUBLIC  engr-rakib/accesspilot           │  marketing + docs hub
│ ├─ README.md (badges, trial, contact)    │  NO code · NO releases · NO tags
│ ├─ docs/ (curated product docs)          │
│ ├─ install.sh / install.ps1              │  token-gated installers
│ ├─ LICENSE · assets/logo_icon.png        │
└───────────────┬──────────────────────────┘
                │ installer runs on CLIENT's live server
                │ ACCESSPILOT_INSTALL_TOKEN=<vendor-issued token>
                ▼
┌──────────────────────────────────────────┐
│ PRIVATE engr-rakib/accesspilot-dist      │  the distribution
│ ├─ sanitized product tree (build output) │  installer clones THIS
│ ├─ configs = placeholder templates       │  ├─ clone → deploy → clone deleted
│ ├─ license_public.pem (public key only)  │  └─ token scoped to THIS repo only
│ └─ docker/ incl. deploy tooling          │
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│ PRIVATE engr-rakib/accesspilot-internal  │  vendor-only backup (NEVER shared)
│ ├─ /app/accesspilot complete project     │
│ ├─ REAL configs · App_Data · vaults      │
│ ├─ scripts/license_admin_templates/      │  ← RSA signing private_key.pem
│ ├─ docs/Agents|internal|Technical        │
│ └─ github-release/ (this pipeline)       │
└──────────────────────────────────────────┘
```

**Rule:** nothing from `internal` ever reaches `dist`; nothing from `dist` ever
reaches `public`. One-way flow, enforced by the build scripts below.

---

## 2 · `github-release/` folder map

```
github-release/
├── DISTRIBUTION_ARCHITECTURE.md   ← this file
├── MANIFEST_PUBLIC.md             ← exclusion rules for the dist build
├── PLAN.md · RELEASING.md         ← process notes (RELEASING: releases retired)
│
├── public-docs/                   ← SOURCE OF TRUTH for public docs
│   ├── README.md                  ← landing page (Trendpilot branding, trial, contact)
│   └── APPLICATION_BOOK · ARCHITECTURE · FEATURES · LIFECYCLE · SECURITY .md
│
├── client-docs-clean/             ← SCRUBBED copies of docs/client
│   └── features/ + guides/        ← (backend terms removed per FEATURE_DOCUMENT_RULES)
│
├── templates/
│   ├── install.sh                 ← token-gated Linux installer (clone→deploy→rm clone)
│   ├── install.ps1                ← token-gated Windows/IIS installer
│   ├── config_app_public.php      ← sanitized app config template (version stamp!)
│   └── LICENSE
│
└── scripts/
    ├── build_public_repo.sh       ← builds DIST tree → /opt/git-accesspilot/accesspilot
    │                                (rsync + MANIFEST excludes + docs curation)
    ├── sanitize_configs.sh        ← secrets → placeholders, full-tree infra-name scrub
    ├── strip_vendor_from_public.py← removes vendor console (4 files + 7 wiring patches)
    ├── build_public_site.sh       ← builds PUBLIC hub → /opt/git-accesspilot/site
    │                                (docs + installers + LICENSE + logo ONLY)
    └── push_both.sh               ← ONE COMMAND to push all three repos
```

---

## 3 · Build & push flow

```
edit code in /app/accesspilot
        │
        ▼
bash github-release/scripts/push_both.sh --rebuild -m "message"
        │
        ├─ build_public_repo.sh   → dist tree (rsync → docs → sanitize → strip vendor)
        ├─ push accesspilot-dist        (private, installer target)
        ├─ push accesspilot-internal    (private, vendor backup)
        └─ build_public_site.sh   → docs hub → push accesspilot (public)

Options:
  --rebuild      rebuild dist from source before pushing
  --site-only    docs-only change (skip dist + internal)
  -m "msg"       commit message for all three
```

Auth: `~/.accesspilot_gh_token` (chmod 600) or `$ACCESSPILOT_GH_TOKEN`.

---

## 4 · Client onboarding (vendor runbook)

1. Client requests trial/purchase → verify identity
2. GitHub → Settings → Developer settings → **Fine-grained PAT**:
   - Repository access: **only `accesspilot-dist`**
   - Permissions: Contents **Read-only**
   - Expiration: trial length / 1 year
3. Send client the token + install one-liner (from public README)
4. Client runs installer on their server → portal boots in evaluation mode
5. License flow: client sends machine ID + site ID → vendor issues RSA-2048
   certificate → License Center → Apply Certificate
6. Revoke/rotate: delete/regenerate the PAT — installs stop instantly

---

## 5 · Verification gates (before every push)

| Gate | Expectation |
|------|-------------|
| Private keys in dist | `grep -RIl "PRIVATE KEY" <dist>` → empty |
| Vendor console in dist | `grep -RIlE "vendor_console\|vendor_license_api"` → empty |
| Infra names in dist | `grep -RIl "whildc\|waltonbd"` → empty (sanitized) |
| Code in public hub | only README/docs/install.sh/install.ps1/LICENSE/assets |
| Releases/tags on public | **zero** (retired distribution channel) |
| Installer clone test | clone dist → critical files OK → no vault/vendor leak |

---

## 6 · Retired (kept for history)

- **Release assets (tar.gz/zip)** — replaced by token-gated clone. Old releases deleted;
  tag names v7.42.0–v7.43.1 are burned/removed (GitHub immutable-release rules —
  see RELEASING.md before ever re-enabling releases).
- **`tag_release.sh`** — legacy; only useful if releases are ever re-enabled.
- Public repo git history — old code commits unreachable (orphan force-push).
  For a guaranteed purge: delete + recreate the public repo in the web UI.

---
*Trendpilot · AccessPilot release engineering — internal-only document.*
