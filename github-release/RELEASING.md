# AccessPilot — Release Management (RELEASING.md)

> How the GitHub release process is run, versioned, and the one-line installer is published.
> Modeled on prometheus/node_exporter release discipline.

---

## 1 · Versioning (SemVer)

The app exposes `version` in `config/app.php` (currently `7.42`). Map it to SemVer
`MAJOR.MINOR.PATCH` and publish as a git **tag**:

| Change | Bump | Example |
|--------|------|---------|
| Breaking / platform change | MAJOR | `7.42.0` → `8.0.0` |
| New feature / module | MINOR | `7.42.0` → `7.43.0` |
| Fix / patch | PATCH | `7.42.0` → `7.42.1` |

Rules:
- Tag names are always `v` + SemVer: `v7.42.0`.
- The `latest` GitHub release auto-points to the newest tag.
- `config/app.php` update_date + version must match the tag on every release.

## 2 · Release pipeline

```
1. WORK ON main (public)         mirror to internal repo as needed
2. bash github-release/scripts/tag_release.sh TAG=v7.42.0
     ├── builds public repo (build_public_repo.sh)
     ├── stage = clean?          (no uncommitted changes)
     ├── git tag -a v7.42.0
     ├── assets:
     │     accesspilot.tar.gz    ← Linux/Docker source (version-independent name)
     │     accesspilot.zip       ← Windows/IIS source
     │     install.sh            ← Linux one-liner
     │     install.ps1           ← Windows one-liner
     │     SHA256SUMS            ← checksums for all above
3. git push origin v7.42.0
4. GitHub → Releases → create from tag → paste notes template → attach assets
5. Verify the one-liner on a clean box
```

## 3 · The one-line installer

Public docs tell everyone to run:

```bash
# Linux / Docker
curl -fsSL https://github.com/<OWNER>/<REPO>/releases/latest/download/install.sh | bash

# Windows / IIS
irm https://github.com/<OWNER>/<REPO>/releases/latest/download/install.ps1 | iex
```

What each does:
- **install.sh** — fetches `accesspilot-<ver>.tar.gz`, verifies SHA256 against the release `SHA256SUMS`, extracts to `/opt/accesspilot`, writes `.env`, runs `docker compose up`.
- **install.ps1** — fetches the zip, verifies checksum, extracts to `C:\inetpub\accesspilot`, registers the IIS site, restarts the pool.

**License gating:** installation always succeeds; the app boots in **read-only evaluation**
(HTTP 423 on write actions) until the deployer purchases a license and applies the
certificate via **License Center**.

## 4 · Release notes template

```markdown
# AccessPilot vX.Y.Z

## Headline
_one line: what this release is about_

## New
- feature... (relates to: docs/client/features/...)

## Improvements
- ...

## Fixes
- ...

## Security
- ...

## Upgrade notes
- (migration steps, if any)

## Assets
| File | Purpose |
|------|---------|
| accesspilot.tar.gz (Linux/Docker source) | Docker/Linux source |
| accesspilot.zip (Windows/IIS source)    | Windows/IIS source |
| install.sh / install.ps1  | one-line installers |
| SHA256SUMS                | checksums |
```

## 5 · What must NEVER reach the public repo

- `scripts/license_admin_templates/` — **vendor tooling = your business**. It is
  excluded by `MANIFEST_PUBLIC.md` and the build script. Keep it only in the
  private/internal repo and on vendor machines.
- Real configs, secrets, personal contacts, AD names, HRMS/`whrmsapi` URLs,
  internal IPs, the encryption key, the deployment ID.
- Run the 4 gates in `PLAN.md` Section D before every tag.

## 6 · Checklist before pressing publish

- [ ] `bash scripts/sanitize_configs.sh /opt/git-accesspilot/accesspilot full`
- [ ] All 4 gates in PLAN.md Section D pass on the PUBLIC repo
- [ ] `config/app.php` version + update_date match the tag
- [ ] Assets built + checksums regenerated (`tag_release.sh` does this)
- [ ] Installer smoke-tested on a clean Docker host (or clean Windows VM)
- [ ] License Center reads the same signed-cert flow on both platforms
- [ ] Internal repo updated in the same batch (private remote only)
---

## ⚠️ GitHub Immutable Releases (2025+) — asset upload rules

New repos have **Immutable Releases** enabled by default. Consequences:

1. **NEVER create a published release first and upload assets after** — upload fails
   (`Cannot upload assets to an immutable release`).
2. **Correct flow:** create **DRAFT** release → upload assets → PATCH `draft:false`.
   Drafts are mutable even when immutability is on.
3. **NEVER delete an immutable release** — its tag name is burned forever
   (`tag_name was used by an immutable release`, 422 on reuse). v7.42.0 was lost this way;
   the first public release is v7.42.1.

Checklist:
```bash
# draft
curl -X POST -H "Authorization: token $TOK" .../releases -d '{"tag_name":" vX.Y.Z","draft":true,...}'
# upload assets to uploads.github.com/.../releases/<id>/assets?name=<file>
# publish
curl -X PATCH -H "Authorization: token $TOK" .../releases/<id> -d '{"draft":false}'
```
