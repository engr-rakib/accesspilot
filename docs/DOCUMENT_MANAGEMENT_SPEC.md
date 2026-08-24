# Document Management System

**Specification — how documentation is organized, tagged, and filtered for client export.**

---

## 1. Directory Structure

```
docs/
├── INDEX.md                       ← reading order, core system map, architecture summary
├── manifest.json                  ← metadata for every document
├── DOCUMENT_MANAGEMENT_SPEC.md    ← this file
├── client/                        ← ALWAYS included in client export
│   └── guides/                    ←   Operator manuals, step-by-step instructions
│       └── <page>-GUIDE.md        ←     One guide per page/section
├── internal/                      ← NEVER included in client export
│   ├── license/                   ←   License system, deployment internals
│   ├── application/               ←   Feature specs, architecture, data flow
│   │   ├── security/              ←     Security architecture, compliance
│   │   └── *.md                   ←     Developer/agent documentation
│   └── *.md                       ←   Meta-documents about the project itself
└── Technical/                     ← NEVER included in client export
    └── <CATEGORY>/<TOPIC>/        ←   Deep technical docs by category
        └── *.md                   ←     Full technical reference
```

### Current File Inventory

| Path | Export | In-App Help | Audience |
|------|--------|-------------|----------|
| `client/guides/API_DOCUMENTATION.md` | ✓ | ✓ | Client IT, admin |
| `client/guides/AD_CONFIGURATION_GUIDE.md` | ✓ | ✓ | Client operator |
| `Technical/AD/user handeling/ou&groups/AD_OBJECTS_CONFIG.md` | ✗ | ✗ | Developer, internal |
| `internal/application/INTELLIGENT_USER_CREATION.md` | ✗ | ✗ | Developer, internal |
| `internal/license/LICENSE_A-Z.md` | ✗ | ✗ | Vendor |
| `internal/license/LICENSE_ARCHITECTURE.md` | ✗ | ✗ | Vendor, developer |
| `internal/license/DEPLOYMENT_ORDER.md` | ✗ | ✗ | Vendor, devops |
| `internal/application/security/VENDOR_SECURITY_AND_DEPLOYMENT.md` | ✗ | ✗ | Vendor, security |

---

## 2. Manifest File (`manifest.json`)

Located at `docs/manifest.json`. Declares every document's metadata:

```json
{
  "version": 1,
  "docs": [
    {
      "path": "client/guides/API_DOCUMENTATION.md",
      "title": "HRMS API Integration Guide",
      "audience": ["client_admin", "client_it"],
      "export": true,
      "in_app_help": true,
      "tags": ["api", "hrms", "integration", "setup"]
    }
  ]
}
```

### Fields

| Field | Type | Purpose |
|-------|------|---------|
| `path` | string | Relative path from `docs/` |
| `title` | string | Human-readable document title |
| `audience` | string[] | Who should read this (`client_admin`, `vendor`, `developer`, `security`, `devops`) |
| `export` | bool | `true` = include in client release zip |
| `in_app_help` | bool | `true` = may be linked from in-app help/info buttons |
| `tags` | string[] | For search/filter within the application |

---

## 3. Client Export Filtering

### 3.1 PHP Builder (`vendor_license_api.php`)
The primary build mechanism (Vendor Console → "Build & Download"). The `$stripPaths` array at line 657 includes `'docs/internal/'` — the entire directory is stripped from the ZIP before download.

```php
$stripPaths = array(
    'scripts/license_admin_templates/',
    'analysis/codebase_upgrade_plan/',
    'docs/internal/',     // ← internal docs stripped
    'docs/Technical/',    // ← technical docs stripped
);
```

All files under `docs/client/` are included automatically (not excluded, not stripped).

### 3.2 PowerShell Script (`prepare-client-release.ps1`)
Secondary build script. The `$SensitivePaths` array at line 21 includes `"$ReleaseDir\docs\internal"` — the directory is removed after copying.

```powershell
$SensitivePaths = @(
    "$ReleaseDir\scripts\license_admin_templates",
    "$ReleaseDir\analysis\codebase_upgrade_plan",
    "$ReleaseDir\docs\internal",
    "$ReleaseDir\docs\Technical",
    "$ReleaseDir\phperror8.5.4_nts.log"
)
```

---

## 4. Document Creation Rules

These rules define what doc to create, where to put it, and how to register it.

### 4.1 Audience Rules

| Audience | Directory | Export | Purpose |
|----------|-----------|--------|---------|
| **Client operator** (end-user who configures/uses the app) | `client/guides/` | `true` | Step-by-step instructions, how-to guides, configuration walkthroughs. Plain language. No architecture. |
| **Client IT / admin** (technical user at client site) | `client/guides/` | `true` | API references, integration setup, troubleshooting. Technical but no internal IP. |
| **Developer / internal** (vendor engineering team) | `internal/application/` | `false` | Feature specs, data flow diagrams, sequence diagrams, architecture decisions. Contains internal IP. |
| **Technical deep-dive** (senior dev, architect) | `Technical/<CATEGORY>/<TOPIC>/` | `false` | Full technical reference with app data flow, integration architecture, sequence diagrams, config schemas, code-level details. |
| **Vendor / owner** | `internal/license/` | `false` | License system, deployment order, infrastructure. |
| **Vendor / security** | `internal/application/security/` | `false` | Security architecture, compliance, vendor deployment. |

### 4.2 When to Create a Doc

Create a new doc when:

1. **A new UI card/section/tab is added** the client operator can interact with → create a **guide** in `client/guides/<page>-GUIDE.md`
2. **A new feature is developed** with complex logic, data flow, or architecture → create a **feature spec** in `client/features/<FEATURE_NAME>/` (or a single file if small)
3. **A new configuration option is exposed** to the operator → add to the relevant existing guide, or create a new guide if no guide exists for that page
4. **A new internal process/infrastructure is added** → create in `internal/license/` or `internal/application/security/`

### 4.3 Naming Convention

```
client/guides/<PAGE>-GUIDE.md               ← e.g. AD_CONFIGURATION_GUIDE.md
internal/application/<FEATURE_NAME>.md       ← e.g. INTELLIGENT_USER_CREATION.md
internal/application/<FEATURE_NAME>/         ← subdirectory for multi-file feature specs
  ├── OVERVIEW.md
  ├── DATA_FLOW.md
  └── API_REFERENCE.md
```

- Filenames: `UPPER_SNAKE_CASE.md` (matches existing pattern)
- Subdirectory for a feature only when the doc exceeds ~300 lines or covers 3+ distinct topics

### 4.4 Document Placement Decision Flow

```
New feature or page?
├── Is it a UI page/section the operator configures?
│   ├── Yes → client/guides/<PAGE>-GUIDE.md
│   └── No  → ↓
├── Is it a complex feature with data flow / architecture?
│   ├── Yes, but concise (overview level) → internal/application/<FEATURE_NAME>.md
│   ├── Yes, deep technical (code-level, sequence diagrams) → Technical/<CATEGORY>/<TOPIC>/
│   └── No  → ↓
├── Is it license / deployment / infrastructure?
│   ├── Yes → internal/license/
│   └── No  → ↓
├── Is it security / compliance?
│   ├── Yes → internal/application/security/
│   └── No  → ↓
└── Deep technical reference with full app data flow?
    └── Yes → Technical/<CATEGORY>/<TOPIC>/
```

### 4.5 Registration Steps

1. **Create the file** in the correct directory per the rules above
2. **Add entry in `manifest.json`** with correct `export` and `in_app_help` flags
3. **If client-facing (`export: true`)** that needs in-app help → set `in_app_help: true`, add a link in the relevant PHP view
4. **If internal (`export: false`)** → keep `in_app_help: false`
5. **Add a short route** in `page_registry.php` and `doc_view.php` `$allowed` array if the doc should be viewable from the admin panel

### 4.6 Checklist for New Docs

- [ ] File named in `UPPER_SNAKE_CASE.md`
- [ ] Placed in correct subdirectory per audience rules
- [ ] `manifest.json` updated with metadata
- [ ] `export` flag correct (client = `true`, internal = `false`)
- [ ] `in_app_help` flag set appropriately
- [ ] If viewable from admin panel: route added in `page_registry.php` + `doc_view.php`
- [ ] If linked from UI: link added to PHP view

---

## 6. In-App Help Integration (Future)

The `manifest.json` supports `"in_app_help": true/false` for future use. When the application renders help tooltips, info cards, or a documentation viewer:

1. Load `docs/manifest.json` at runtime
2. Filter docs where `"in_app_help": true`
3. Display only those documents as help references

This prevents internal docs from accidentally appearing in the application UI.

**Example PHP loader**:
```php
$manifest = json_decode(file_get_contents('docs/manifest.json'), true);
if ($manifest) {
    foreach ($manifest['docs'] as $doc) {
        if ($doc['in_app_help'] && in_array('client_admin', $doc['audience'])) {
            // Show help link for this document
        }
    }
}
```

---

## 7. Card-to-Document Mapping (System Config → AD Objects)

Each card in the AD Objects tab links to a specific document via `index.php?page=<route>`.

| Card | Route | Document | Audience |
|------|-------|----------|----------|
| Per-Domain (info bar) | `ad-guide` | `client/guides/AD_CONFIGURATION_GUIDE.md` | Client operator |
| OU Management | `ad-guide` | `client/guides/AD_CONFIGURATION_GUIDE.md` | Client operator |
| Group Management | `ad-guide` | `client/guides/AD_CONFIGURATION_GUIDE.md` | Client operator |
| User Properties | `ad-guide` | `client/guides/AD_CONFIGURATION_GUIDE.md` | Client operator |

All 4 cards link to the same **operator guide**. Technical specs (`AD_OBJECTS_CONFIG.md`, `INTELLIGENT_USER_CREATION.md`) are in `internal/application/` and not exported. They are developer/internal references — not linked from the operator UI (but viewable via `index.php?page=ad-objects` and `index.php?page=ad-features` for internal users).

### Link Placement

| Card Section | Link label | Location |
|-------------|------------|----------|
| Per-Domain card | `Guide` | Title bar, right side |
| OU Management | `Guide` | Title bar, between title and Customize toggle |
| Group Management | `Guide` | Title bar, between title and Customize toggle |
| User Properties | `Guide` | Hint paragraph, inline |

### Short Route Reference

All doc routes map through `page_registry.php` → `doc_view.php` (markdown renderer):

```
index.php?page=ad-guide      → client/guides/AD_CONFIGURATION_GUIDE.md                  (exported)
index.php?page=ad-objects    → Technical/AD/user handeling/ou&groups/AD_OBJECTS_CONFIG.md  (not exported)
index.php?page=ad-api        → client/guides/API_DOCUMENTATION.md                       (exported)
index.php?page=ad-features   → Technical/AD/user handeling/INTELLIGENT_USER_CREATION.md   (not exported)
```

To add a new route: add a `case` in `app/Application/Routing/page_registry.php` and an entry in `resources/views/pages/license/doc_view.php` `$allowed` array.

---

## 8. Migration Log

| Date | Change |
|------|--------|
| 2026-06-15 | Created `docs/internal/` and `docs/client/` directories |
| 2026-06-15 | Moved 4 license docs → `docs/internal/license/` and `docs/internal/application/security/` |
| 2026-06-15 | Moved `API_DOCUMENTATION.md` → `docs/client/guides/` |
| 2026-06-15 | Moved `AD_OBJECTS_CONFIG.md` → `docs/client/guides/` |
| 2026-06-15 | Moved `INTELLIGENT_USER_CREATION.md` → `docs/client/features/` |
| 2026-06-15 | Deleted old `docs/api/`, `docs/license/`, `docs/operator/` directories |
| 2026-06-15 | Created `docs/manifest.json` with all 8 document entries |
| 2026-06-15 | Updated `vendor_license_api.php` — added `docs/internal/` to `$stripPaths` |
| 2026-06-15 | Updated `prepare-client-release.ps1` — added `docs\internal` to sensitive paths |
| 2026-06-15 | Updated `system_config_view.php` — fixed API_DOCUMENTATION path |
| 2026-06-15 | Updated `doc_view.php` — fixed all 4 license doc paths |
