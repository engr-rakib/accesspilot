# AccessPilot — Development Guidelines & Conventions

> **Single source of truth** for all UI development. Based on actual CSS audit (theme.css, components.css, layout.css, base.css) and all page templates.

---

## 1. Architecture Overview

**Laravel-style PHP** (no framework — custom routing via `include_path()`).

| Layer | Path | Purpose |
|-------|------|---------|
| Application | `app/Application/Http/` | Controllers, admin portal, SPA routing |
| Domain | `app/Domain/` | AD actions, HRMS, Auth, Licensing (RSA-2048), RBAC |
| LDAP | `app/Ldap/` | In-process PHP LDAP (ext-ldap) for AD operations |
| Infrastructure | `app/Infrastructure/` | PowerShell runner, persistence, logging |
| Views | `resources/views/` | PHP templates (layouts, pages, components) |
| Frontend | `public/resources/frontend/` | JS modules, CSS files |
| API | `public/api/index.php` | API gateway (CSRF-protected, 50+ endpoints) |
| Entry | `public/index.php` | Front controller for page requests |

**Dual backend**: LDAP (primary, via `ext-ldap`) + PowerShell fallback for AD operations.
**Dual platform**: Same codebase runs on Linux (Docker Nginx + PHP 8.2-FPM) and Windows (IIS + PHP 8.5.4 NTS).

### Key Architecture Rules
- NEVER include `action_taken_card.php` inside individual view files. It is managed globally by `master.php` and `spa_response.php`.
- Core UI elements accessed via `window` object for cross-module reliability.
- All page requests flow through `public/index.php` (single entry point). SPA page requests: `/index.php?page=xxx` → `admin_portal.php` → `page_registry.php`.

---

## 2. The 3-Pane Shell (Mandatory)

| Component | Width/Height | CSS | Background |
|-----------|-------------|-----|------------|
| Navigation Rail | 68px | `.shell-vertical-rail` | `#e9edef` |
| Assistant Pane | 280px (CSS token `--context-width: 260px` but shell override wins) | `.shell-context-pane` | `#ffffff` |
| Workspace | Fluid | `.shell-workspace` | `#f0f2f5` |
| Workspace Header | 52px | `.shell-header` / `--shell-header-height` | — |
| User icon (header) | 28px circular | `.shell-header .user-icon` | — |

**Active color:** Bold Purple (`#8b2eb8`).
**Lock Dimensions — NEVER override per-page.**

### Key Selectors (for CSS overrides)
- Side card buttons: `html body.shell-whatsapp.theme-red .shell-context-pane .action-button`
- Rail buttons: `html body.shell-whatsapp.theme-red .rail-item`
- Header buttons: `html body.shell-whatsapp.theme-red .workspace-header .shell-header-tools .shell-tool-btn`

---

## 3. Layout & Spacing System

### 3.1 CSS Layout Contract
- Shell/workspace spacing only controlled from `layout.css` (`FINAL SHELL LAYOUT LOCK` block).
- Shared card/title spacing only from `components.css`.
- `theme.css` is for visual styling ONLY — no layout, gutter, workspace padding, or card title height overrides.

### 3.2 Workspace Padding
`padding: 12px` on `.workspace-content-scroll` via `--shell-workspace-pad-x/y`. Global — never override per-page.
Do NOT wrap page content in `.container-fluid` — workspace already provides 12px padding.

### 3.3 Card Vertical Gap

Every top-level card MUST use `margin-bottom: 10px !important`:

```html
<div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;">
```

Rules:
- `!important` is required because `components.css` globally sets `.card { margin-bottom: 0 !important }`.
- Always use inline style `margin-bottom:10px !important` — do NOT rely on CSS classes or variables for this.
- NEVER use `margin-top` for card gaps — collapses unpredictably.
- NEVER use Bootstrap `mb-2` (8px) — use `margin-bottom:10px` for consistency.

### 3.4 Column Gutter — NO `gx-0`
**Do NOT use `class="row gx-0"`** for card wrappers. Always use standard `.row`. Layout.css defaults handle all horizontal spacing.

### 3.5 New Page Checklist

1. Template: `<div class="row">` (no `gx-0`).
2. No `container-fluid`, no `gx-0`, no `pe-xl-*`/`ps-xl-*`.
3. Card gap: `style="overflow:hidden !important;margin-bottom:10px !important;"` on every card.
4. Card body: `class="card-body no-padding"` with `style="padding:0 !important;margin:0 !important;"`.
5. Title: `<h3 class="log-title-wrapper app-table-title">` (see §4).
6. Content wrapper: `<div class="p-2">` inside card-body.
7. Verify: card→card vertical gap = 10px, column→column gap = 6px, workspace edge = 12px.

---

## 4. Card Design Consistency

### 4.1 Exact Card Structure (Mandatory — ALL pages)

```html
<div class="card app-table-card" style="overflow:hidden !important;margin-bottom:10px !important;">
    <div class="card-body no-padding" style="padding:0 !important;margin:0 !important;">
        <h3 class="log-title-wrapper app-table-title">
            <span><i class="fas fa-ICON me-1"></i>CARD TITLE <span class="badge bg-secondary font-tech" style="font-size:0.65rem;">Badge</span></span>
            <span class="font-tech text-muted" style="font-size:0.7rem;">Status text</span>
            <!-- OR -->
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm">Action</button>
            </div>
        </h3>
        <div class="p-2">
            <!-- content -->
        </div>
    </div>
</div>
```

### 4.2 Card Title Rules

| Property | Value |
|----------|-------|
| Element | `<h3 class="log-title-wrapper app-table-title">` |
| Left side | `<span>` wrapping icon + title text |
| Right side | `<span>` for status text, OR `<div class="d-flex">` for action controls |
| Title font | Inherited from `<span>` inside h3 — use `font-size:var(--font-xs)` inline or default |
| Title weight | Bold (inherited from `<h3>`) |
| Padding (title bar) | `8px 16px` — from `.log-title-wrapper` CSS |
| Bottom border | `1px solid rgba(15, 23, 42, 0.08)` — from `.log-title-wrapper` CSS |
| Min height | `36px` — from `.log-title-wrapper` CSS |

Do NOT use:
- Plain `<div class="app-table-title">` (must be `<h3 class="log-title-wrapper app-table-title">`)
- Inline `<span>` as title without the h3 wrapper
- `.card-title` class for page-level card titles

### 4.3 Card Body Pattern

- **WITH title bar**: `card-body.no-padding` + `<h3 class="log-title-wrapper">` + `<div class="p-2">` for content
- **WITHOUT title bar** (compact cards in grid): `card-body.p-2` with inline title span inside

### 4.4 Section Color Coding

Use `border-left:3px solid #COLOR` to distinguish sections:
- Amber (`#f59e0b`) — Application / Status
- Blue (`#3b82f6`) — System / Container
- Purple (`#8b2eb8`) — Infrastructure / Gauges
- Cyan (`#06b6d4`) — Analytics / Charts
- Magenta/green for tool-specific cards — see existing pages for reference

### 4.5 Button Rules

#### Form Action Buttons (`.app-form-actions`)

Every form footer MUST use `.app-form-actions` wrapper. Buttons styled by `components.css` + `theme.css`.

| Property | Value |
|----------|-------|
| Height | 36px (min + fixed) |
| Padding | `0 14px` |
| Font size | 0.82rem |
| Font weight | 700 |
| Border radius | `var(--red-clay-radius)` |
| Min width | 108px |

**Button Order (MANDATORY):**

| Context | DOM 1st (left) | DOM 2nd (right) |
|---------|----------------|-----------------|
| Card forms (`.app-form-actions`) | `.btn-secondary` (Cancel) | `.btn-primary` (Submit) |
| Inline table row edit | `.btn-primary` (Save) | `.btn-secondary` (Cancel) |
| Modals | `.btn-primary` (flex-fill) | `.btn-secondary` (flex-fill) |

**DO NOT:**
- Use `.btn-sm` on form action buttons (reserved for table/inline icon buttons).
- Use custom button classes (`.btn-noc-premium`, etc.).
- Add `.fw-bold`, `.px-3` etc.— `.app-form-actions` already provides via CSS.
- Use `.btn-outline-secondary` for Cancel — use `.btn-secondary`.

#### Table Action Buttons
```html
<button class="btn btn-outline-primary btn-sm px-2 py-0"><i class="fas fa-pen"></i></button>
```
Height: 28px, padding: 0 6px.

#### Inline Icon Buttons (password toggle, resolve host)
```html
<button class="btn btn-sm btn-outline-secondary" type="button"><i class="fas fa-eye"></i></button>
```

### 4.6 Form Controls

| Element | Class | DO NOT use |
|---------|-------|------------|
| Input/select | `form-control` | `form-control-sm` |
| Label | `form-label` | `sys-label` |
| Input group | `input-group` | `input-group-sm` |

All labels MUST be `<label class="form-label" for="...">`. `.sys-label` is reserved for standalone display labels.
All `<form-label>` / `<form-control>` use **0.85rem** default, `padding: 8px 12px` (from `components.css`).

### 4.7 Long Value Overflow
Cert/ID fields with long base64 strings (Deployment ID, Cert ID) must use `word-break: break-all` to prevent overflow on narrow columns.

### 4.8 Icon-in-Input Pattern (REVERTED / DO NOT USE)

The `.sys-input-icon` pattern (position:relative wrapper + absolutely positioned spans) was implemented then **reverted**. CSS classes `.sys-input-icon`, `.sys-input-icon-trigger`, `.sys-input-icon-static` removed.

**Use the Manual User Creation style instead:**
- Plain `<input class="form-control">` — NO icon-inside-input wrapper.
- Password fields: `<div class="input-group">` with `<button class="btn btn-outline-secondary">` for eye toggle.
- Action buttons: `<button class="btn btn-outline-info">` for lookup/search.
- Do NOT use `input-group` + `input-group-text` — shell-whatsapp CSS (`border-radius:10px !important`) breaks input-group layout. Use `btn-outline-secondary` in input-group-append/prepend.

### 4.9 Status/Feedback Element Empty State

```css
.your-status-element:empty {
    display: none;
}
```
- Never use `min-height` on initially-empty status elements — creates phantom whitespace.
- Keep `margin-top` — only applies when visible (display:none hides margins).
- JS sets `innerHTML` — no toggle classes needed.

### 4.10 Health/Status Cards (Color-Coded Border)
```html
<div class="card app-table-card sys-health-hub" id="sys_health_hub">
```
Modifier classes: `.sys-health-healthy` (green), `.sys-health-warning` (yellow/orange), `.sys-health-critical` (red).

---

## 5. Typography System

**Source of truth:** `config/ui.php` → `typography.font_sizes`. Injected as CSS custom properties via `master.php`.

HTML base: **15px**. Body line-height: **1.6**. Responsive: 15px → 13px (≤992px) → 11px (≤768px).

### Font Family Tokens

| Token | Value | Usage |
|-------|-------|-------|
| `--primary-font` | Roboto | Body text, headings, form labels |
| `--technical-font` | monospace | `.font-tech` class, code blocks, system output |

### Font Size Tokens

| Token | Value (@15px base) | Usage |
|-------|-------|--------|
| `--font-xs` | 0.7rem (10.5px) | badges, status labels, card title text |
| `--font-sm` | 0.8rem (12px) | table headers, sidecard buttons, small labels |
| `--font-base` | 0.95rem (14.25px) | body, inputs, buttons |
| `--font-md` | 1rem (15px) | stat values, emphasis |
| `--font-lg` | 1.15rem (17.25px) | section headers, page titles |
| `--font-xl` | 1.3rem (19.5px) | large stat values |
| `--font-xxl` | 1.6rem (24px) | section heroes |
| `--font-table` | 0.8rem (12px) | table body cells (td) |
| `--font-info` | 0.85rem (12.75px) | info card content |
| `--font-feedback` | 0.85rem (12.75px) | feedback message card |

### Rules

- CSS MUST use `var(--font-*)` — no hardcoded `px` or `rem` values in CSS files
- Exception: JS-generated HTML (Canvas overlays, dynamic chart labels) may use `10px` as minimum readable size — but prefer `var()` via inline style when possible
- Card titles inside `h3.log-title-wrapper` use size from child `<span>` — typically `var(--font-xs)` (0.7rem) or `0.85rem` for `.card-title`
- DO NOT create separate visual font systems across dashboard, monitoring, reports, or forms
- To change sizes: update only `config/ui.php` → `typography.font_sizes` — all variables update automatically

### Key CSS Classes

| Class | File | Properties |
|-------|------|------------|
| `.font-tech` | `base.css:80` | `font-family: var(--technical-font), monospace !important; font-size: var(--font-base, 0.875rem);` |
| `.card` | `components.css` | `border-radius:12px; box-shadow:0 4px 12px rgba(15,23,42,0.08); background:var(--ws-card-bg,#fff); border:1px solid var(--border-color);` |
| `.app-table-card` | `system_config.css` + 6 theme overrides | Themed background/border/shadow per active theme |
| `.log-title-wrapper` / `.app-table-title` | `components.css:889` | `display:flex; justify-content:space-between; align-items:center; padding:8px 16px; min-height:36px; border-bottom:1px solid rgba(15,23,42,0.08);` |
| `.card-title` | `components.css:399` | `font-size:0.92rem; font-weight:700; text-transform:uppercase; letter-spacing:0.45px;` |

### Universal Colors
- **Body text**: `var(--text-color)` (themed)
- **Muted text**: `var(--text-muted)` or `#6b7280`
- **Card background**: `var(--ws-card-bg, #ffffff)`
- **Border**: `var(--border-color, rgba(0,0,0,0.1))`
- **Icon accent**: `#2563eb` (blue), `#d97706` (amber), `#dc2626` (red)
- **Font**: Roboto via Google Fonts (`--primary-font`)

---

## 6. Scrollbar Standard

- Workspace scrollbar: thin (4px width, transparent track). `components.css`.
- Global body/html scrollbar hidden via `base.css` (intentional for shell layout).
- `.workspace-content-scroll scrollbar-width: thin`.
- Scrolling still enabled; `scrollbar-gutter: stable` prevents layout shift.
- No layout theft: scrollbar styling must not add extra visual gutter.

---

## 7. CSS Rules & Conflict Resolution

### 7.1 File Responsibilities

| File | Role | Authority |
|------|------|-----------|
| `layout.css` | Shell dimensions, workspace padding, row/column gaps | **FINAL LAYOUT LOCK** |
| `components.css` | `.card`, `.form-control`, `.form-label`, `.app-form-actions .btn`, `.btn-icon`, `.card-title`, `.log-title-wrapper`, `.app-table-title`, `.app-table-card .card-body.no-padding` | **Shared component contract** |
| `dashboard.css` | Dashboard-specific styles (chart containers, metric tiles) | Dashboard page only |
| `theme.css` | Theme variant visual styling + `.app-table-card` per-theme | **Visual only** — no layout |
| `pages.css` | Page-specific overrides, namespace-scoped | Lowest — only for page-specific |
| `system_config.css` | System config page only + `.app-table-card` base definition | Scoped to `.sys-config-portal` |
| `sidecard.css` | Side card button backgrounds (gradient fallback only) | Backgrounds only |
| `base.css` | HTML reset, body typography, `.font-tech`, scrollbar, html font-size | Global baseline |

### 7.2 CSS Variable Cascade
```
inline <style> (config colors)    ← highest specificity
    ↓
theme.css .theme-red block        ← fallback gradients
    ↓
theme.css non-themed block        ← base gradients
```

### 7.3 `.app-table-card` Theming

`.app-table-card` has NO base CSS definition in components.css — it is only styled per-theme in `theme.css` and `system_config.css`. The `.card` class provides the base card styles. `.app-table-card` is an **identity class** for theme override hooks:

```css
/* theme.css example — each theme has its own block */
html body.shell-whatsapp.theme-red .app-table-card {
    background: var(--red-card-bg) !important;
    border: 1px solid var(--red-card-border) !important;
    border-radius: var(--red-clay-radius) !important;
    box-shadow: var(--red-top-highlight), ... !important;
}
```

When creating a new card, always add both `.card` and `.app-table-card` classes.

### 7.4 Rules to Prevent Conflicts

1. NEVER define `.theme-red .shell-context-pane .action-button` in `sidecard.css` — all theme button properties go in `theme.css` only.
2. Use `var()` with CSS variables for per-type backgrounds (respects config values).
3. Keep `.theme-red` CSS variable values as saturated gradients, not muted solids.
4. Use `inset 0 0 0 999px rgba(0,0,0,N)` for darkening instead of changing gradient colors.
5. Same UI problem: **replace** old conflicting rule; do NOT add stacked overrides.
6. Hidden UI elements: do NOT use global `display: inline-flex !important` — it breaks inline `display:none`.
7. Page CSS must stay inside a clear namespace (e.g., `.sys-config-portal ...`). Generic selectors (`.log-table`, `.card-title`, `.row`) remain in shared CSS.
8. Namespace rule: do NOT redefine `.shell-workspace`, `.workspace-content-scroll`, `.shell-context-pane`, or header dimensions in scattered CSS.

### 7.5 Global CSS Conflicts to Know

| File:Line | Selector | Effect |
|-----------|----------|--------|
| `pages.css` | `.btn-primary` | All primary buttons: `padding: 12px; font-weight: 600; border-radius: 10px` (overridden by `.app-form-actions .btn`) |
| `pages.css` | `.form-control` | All inputs: `padding: 12px; margin-bottom: 15px; border-radius: 10px` |
| `components.css` | `.app-form-actions .btn` | Form buttons: 36px height, 0 14px padding, 0.82rem font, 700 weight |
| `components.css` | `.form-label` | Labels: 0.82rem, 600 weight, 4px margin-bottom |
| `components.css` | `.form-control` | Inputs: 0.85rem, 8px 12px padding |
| `theme.css` | `.app-form-actions .btn` | Theme-red button sizing + shadow |
| `theme.css` | `.app-form-actions .btn-primary` | Theme-red primary button background |

### 7.6 Button Variant Colors
All variant color rules (`.btn-outline-secondary`, `.btn-danger`, `.btn-success`, `.btn-primary`, `.btn-warning`) must live in ONE place in `components.css` with `!important` on `background`, `border-color`, `color`.

### 7.7 Manage Section / Existing Users Card CSS
- Reuse `user-mgmt-action-cell` and `app-action-buttons` classes — do NOT create separate `#manageGroupMembersTable td .btn-icon` CSS.
- `.btn-icon` base class: `width: 32px`. Action cell override: `28px`. Buttons outside action cells don't get the 28px override — must be explicit.

Font Awesome icons inherit `color` from parent `<button>` via `currentColor`. Ensure `color: inherit !important` on icon elements inside buttons for variant color pass-through.

---

## 8. JS Patterns & SPA Rules

### 8.1 SPA Initialization

- Re-trigger all initialization logic (charts, tree-listeners) on every `spaContentUpdated` event.
- Use init guard pattern:
```javascript
function initYourPage() {
    var root = document.querySelector('.your-page-namespace');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    // bind events
}
document.addEventListener('spaContentUpdated', initYourPage);
```
- Always re-find global elements inside event handlers to avoid "detached DOM element" errors after SPA content updates.

### 8.2 Reference Integrity
- Never call a function (e.g., `clearForm`, `showLoading`) unless it is defined within the local module closure or confirmed as a global `window` property.
- `showActionRibbon` from earlier specs is NOT defined — use `showLoading` / `showLoadingAnimation` on the action result card instead.

### 8.3 Base URL Resolution
```javascript
var baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) ||
    (typeof baseURL === 'string' ? baseURL : window.location.origin);
```

### 8.4 Loading Animation
ALL async fetch/submission operations MUST show loading animation:
```javascript
window.showLoadingAnimation(element);
```
Three colored dots (Blue `#1976D2`, Red `#AA3A46`, Green `#1B5E20`) with text: "Your request is underway...". Defined in `utils.js:53`.

### 8.5 Dropdown Clipping — Three-Part Fix

Dropdowns clipped by `.workspace-content-scroll { overflow-y: auto !important }`. Also, `.slide-in-top` animation has `animation-fill-mode: forwards` which persists `transform: translateY(0)` — even `translateY(0)` creates a CSS containing block breaking `position: fixed`.

**Fix:**
1. `.overflow-visible-card { transform: none !important }` — overrides animation transform.
2. `openDropdownFixed(dropdown, input)` — uses `position: fixed` + `getBoundingClientRect()` for viewport coordinates, bypassing ALL ancestor overflow/transform.
3. `cleanupDropdownFixed(dropdown)` — clears inline position styles on close.

**Also ensure:** `.card-body` must also have `overflow: visible` — `.overflow-visible-card` only sets `.card` to `overflow: visible`, but dropdowns can still be clipped by `.card-body` overflow.
**No more `.dropdown-open` class toggle or workspace overflow manipulation needed.**

See `manual_create_user_actions.js` for reference.

**`getBoundingClientRect()` on `display: none`** returns `{top:0, left:0}`. Always set `display: block` BEFORE calling `getBoundingClientRect()`.

### 8.6 SPA Modal Rules

#### Why Modals Break
SPA pages load inside `.workspace-content-scroll` (`overflow: hidden` + stacking context). Bootstrap modals placed inside render **under** the dark backdrop — fields uneditable.

#### Required Fix (Every SPA Page with Modals)

**Step 1 — JS: Relocate modal to `document.body`:**
```javascript
function relocatePageModals(ids) {
    ids.forEach(function(id) {
        var nodes = document.querySelectorAll('#' + id);
        if (!nodes.length) return;
        var target = nodes[nodes.length - 1];
        nodes.forEach(function(node) {
            if (node !== target) node.remove();
        });
        if (target.parentElement !== document.body) {
            document.body.appendChild(target);
        }
    });
}
```
Call on: page init, `spaContentUpdated`, and before `modal.show()`. Use `bootstrap.Modal.getOrCreateInstance(modalEl)` — not `new bootstrap.Modal()` on every click.

**Step 2 — CSS: z-index above shell rail (1100):**
```css
#yourModalId {
    z-index: 1115 !important;
}
body.modal-open .modal-backdrop.show {
    z-index: 1110 !important;
}
```

**Step 3 — Modal markup:**
```html
<div class="modal fade" id="yourModalId" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">...</div>
            <div class="modal-body pt-2">
                <label class="form-label">...</label>
                <input class="form-control" ...>
            </div>
            <div class="modal-footer border-0 pt-0 app-form-actions">
                <button type="button" class="btn btn-primary flex-fill">Save</button>
                <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
```

#### Inline Row Edit (Preferred over Modal)
For table row Edit/Verify: **expand inline below the row**. Do NOT open a modal.

Pattern (reference: License Tracking in `vendor_view.php`, `vendor_actions.js`):
```html
<tr class="vendor-lic-row" data-lic-id="LIC-...">...</tr>
<tr class="vendor-lic-panel-row" data-lic-id="LIC-..." style="display:none;">
    <td colspan="8">
        <div class="vendor-inline-edit-panel">
            <div class="vendor-inline-edit-grid"><!-- 2-column CSS grid --></div>
            <div class="app-form-actions">
                <button class="btn btn-primary vendor-inline-save">Save Changes</button>
                <button class="btn btn-secondary vendor-inline-cancel">Cancel</button>
            </div>
        </div>
    </td>
</tr>
```

#### Credential-Gated Page Access (Session-Based)
Sensitive pages require credential re-entry once per PHP session:

**PHP** (after successful verify):
```php
$_SESSION['vendor_creds_verified'] = true;
```

**Check endpoint:**
```php
$verified = !empty($_SESSION['vendor_creds_verified']);
echo json_encode(['success' => true, 'verified' => $verified]);
```

**JS init pattern:**
```javascript
function initYourPage() {
    var root = document.querySelector('.your-page');
    if (!root || root.dataset.initialized === 'true') return;
    fetch(API_URL + '&action=check_creds')
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success && res.verified) {
            revealContent();
        } else {
            showCredentialModal();
        }
    })
    .catch(function() { showCredentialModal(); });
}
```

**Rules:**
1. Success verify → store in `$_SESSION` (not cookies/localStorage).
2. Check endpoint returns session state before showing modal.
3. Session persists across SPA navigations. Only logout/new login triggers modal.
4. Cancel hides modal but does NOT grant access.

#### Credential Confirmation Modal (for Config Changes)
- Single modal reused for multiple actions: dynamically change title/description/action type.
- Modal inputs clear on `hidden.bs.modal` event.
- Form data sent via `fetch` POST with `Content-Type: application/json` to `api/index.php?endpoint=system_config_api&action=save_config`.
- Use `Object.fromEntries()` or manual loop to convert `FormData` to plain object for `JSON.stringify()`.
- **Inline feedback** instead of `alert()`: hidden `<div id="modalFeedback">` inside modal body with color-coded backgrounds.
- Disable confirm button + show spinner during submission; re-enable on failure.
- Backend must return `{success: true/false, message: "..."}` JSON.
- Backend: `confirm_user_id` + `confirm_password` validated at top of POST handler via `readUsers()` + `password_verify()`.
- Audit logging: capture old values before update, compare after update. Build diff string like `"Domain: 'old' → 'new'; BaseDN: 'old' → 'new'"`. Log `"No field changes detected"` if nothing changed.

### 8.7 Storage Card with Cancel on Edit
- "Update Paths" button + hidden cancel (×) button that appears when any field changes.
- Cancel reverts fields to original values (store `origSecure`/`origLog` on load).
- Both buttons require credential modal confirmation.

### 8.8 Security Rules
- Never print sensitive license or credential data.
- Use the PowerShell driver for all AD operations (password redaction regex handles sensitive params).
- Use `ldap_escape_dn_component()` on all 5 OU fields.

### 8.9 DO NOT (SPA/Modal)

- Use modal for simple table-row edits when inline expand works.
- Leave modals inside `.workspace-content-scroll` or any `overflow: hidden` parent.
- Use Bootstrap `.row`/`.col-*` inside `<table>` cells — use CSS Grid instead.
- Rely on default Bootstrap z-index (1055) — it loses to shell rail (1100).
- In card forms: put Save before Cancel. Card forms: Cancel left, Save right. Inline: Save left, Cancel right.

---

## 9. Backend Conventions

### 9.1 Multi-ID Support
- Input split by `[\s,;]+` everywhere.
- PowerShell path: `ad_action_service.php:214` splits `$username`, implodes with comma.
- LDAP path: `ldap_directory_writer.php:378-435` loops per user.
- `ldap_user_repository_find_many()` is the multi-user LDAP handler for `get_user_info_bulk`.

### 9.2 Feedback Message Format
- `ldap_feedback_message()`: `$badge\n\nProcessed: N | Success: X | Skipped: Y | Failed: Z`
- Per-user summary (inline `Processed: 1 | ...`) STRIPPED in multi-ID aggregator (`ldap_directory_writer.php:409-410`).
- `ldap_ad_action_message()`: prefixes SUCCESS:/ERROR: and appends summary.
- `directory_info_service.php` strips lines matching `>> Processed:` from info output.

### 9.3 Status Counts (Success/Skipped/Failed) — CRITICAL
- **Already enabled/disabled/unlocked** → `successCount: 0, skippedCount: 1` (NOT 1/1).
- **Actual change** → `successCount: 1, skippedCount: 0`.
- Check `ldap_user_writer.php` for enable/disable/unlock skip branches.

### 9.4 Suggestions (Related IDs)
- LDAP backend returns `suggestions: { lookedUpUser: [id1, id2, ...] }` in response.
- Frontend parses structured suggestions OR falls back to text parsing of "Multiple matching IDs" / "Nearby IDs".
- Suggestion IDs auto-fetched and shown as additional tabs in server info card only.

### 9.5 CSRF Protection Flow
1. Login: `auth_start_user_session()` generates `bin2hex(random_bytes(32))` → stored in `$_SESSION['csrf_token']`.
2. Page load: `master.php` injects `window._csrfToken` from PHP.
3. API calls: global `fetch()` wrapper auto-adds `X-CSRF-Token` header (for `/api/` endpoints only).
4. Validation: `api/index.php` compares header against `$_SESSION['csrf_token']` — applied to all non-GET, non-auth endpoints.

### 9.6 Session Guard
- 15-min idle timeout (`$_SESSION['last_activity']`) — remember-me = 2h (7200s).
- Remember-me cookie (login `time()+7200`) re-asserted after every 5-min `session_regenerate_id()` so it isn't downgraded to a browser-session cookie.
- `session.gc_maxlifetime = 7200` must stay ≥ remember-me idle max, else GC purges the session early.
- Session ID regenerated every 5 min (not per-request — prevents AJAX race conditions).
- Forced logout if idle > max (900s plain / 7200s remember-me); client `master.php` timer fires 1 min early (840s / 7140s).
- Auto-logout redirects to `login.php?message=session_expired` (`session_terminated` for admin kill) — both surfaced as a banner in `login.php`.

### 9.7 API Gateway
- All non-GET requests go through CSRF validation.
- `$csrfExemptEndpoints` = `['auth_api', 'get_ad_hrms_status', 'export_hrms_ad_user_id', 'get_ad_health_check_report', 'export_ad_user_list', 'get_hrms_ad_report', 'monitoring_api']`.
- Direct stub endpoints (`/audit.php`, `/notification.php`) rely on session authentication instead of CSRF.

---

## 10. Info Card Conventions

- **Two card types**: feedback (`actionTakenCard`) and info cards (`serverUserInfoDisplay`, `employeeInfoDisplay`).
- **Tabbed info cards** for multi-user results (single user → 1 tab, multi → N tabs).
- Info cards use `buildTabbedCard()` with tab switching via click delegation.
- `renderServerHtml()` extracts identity fields:
  - Logon Name from `AD Account:` (sAMAccountName)
  - Principal ID from `User Principal ID:` (userPrincipalName)
- **Server card** identity: Logon Name + Principal ID.
- **Employee card** identity: Employee ID (EMP_CODE) + EMP Code (EMP_ID).

---

## 11. Frontend JS Conventions

- `styleFeedbackMessage(msg)` in `utils.js`: converts `Processed:` line to colored `.status-badge` spans.
- `clipboard_utility.js`: strips status line via regex `/\\n?(>>?\\s*)?Processed:[\\s\\S]*$/i`.
- `#actionTakenMessageDisplay`: `display: block !important` (overrides `.alert`'s `display: flex`).
- Feedback duration: operational result cards persist **45 seconds** before auto-hiding.
- Reports (Sync/Mapping): stay visible until user clicks "Close" or "Download".

---

## 12. Tree-View Explorers (OU & Groups)

- **VS Code Style:** Vertical/horizontal guide lines centered at middle of icons and text.
- **Compact Height:** Rows exactly **28px** high.
- **Indentation:** Precise **20px** per nesting level.
- **No-Clipping:** Parent cards `overflow: visible !important`. Dropdowns overlay background.
- **Scroll Limit:** Max 15 rows (400px height) before internal scrolling.
- `renderTree` early-return guard (`hasSelectable`) must recursively check `item.children`, not just top-level `.some()`. Tree roots are OUs; Groups nest under them.

---

## 13. Task Focus & Animations

- **Focus-First Layout:** Action results and forms (Modify/Create) at TOP of workspace, above info cards.
- **Swipe Transitions:** `swipeDown` for entrance, `swipeUp` for exit.
- **Feedback Duration:** 45 seconds before auto-hide (operational). Reports persist until Close/Download.
- **SPA transitions:** `swipeDown` animation shows loading state in `actionTakenCardContainer`.

---

## 14. Dashboard / Activity Separation

- **Technical Dashboard** (`?page=dashboard`): AD Operations, Health Checks, Script Logs. `include_root: false` in log readers.
- **User Activity** (`?page=user_activity`): Application Events (Logins, Sessions, Audit). `categories: []` in log readers.

---

## 15. Developer Lessons Learned

### 15.1 Side Card Button CSS Conflict (SOLVED)
- `theme.css` `.theme-red .shell-context-pane .action-button.btn-*` rules (specificity 0,5,2) overrode `sidecard.css` non-themed rules (specificity 0,4,2).
- `theme.css` `.theme-red` block defined CSS variables as muted solids instead of saturated gradients.
- `sidecard.css` used hardcoded pastel colors instead of `var()`.
- **Fix:** Single source of truth in `theme.css`. Sidecard.css only provides backgrounds via `var()`.

### 15.2 gx-0 Anti-Pattern (SOLVED)
All workspace pages (`system_config`, `vendor_console`, `license_status`) were cleaned. Use layout.css defaults. See §3.4.

### 15.3 PHP session_start() Notices (SOLVED)
44 controllers had unconditional `session_start()` after `api/index.php` already started session. All changed to:
```php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
```

### 15.4 Modal Stacking Context (SOLVED)
Always call `document.body.appendChild(modalEl)` before `new bootstrap.Modal(modalEl)`. See §8.6.

### 15.5 Dropdown Clipping (SOLVED)
Animation `transform: translateY(0)` creates CSS containing block. Three-part fix in §8.5.

### 15.6 Hidden Element `display: none` Override (SOLVED)
Global `.app-form-actions .btn { display: inline-flex !important }` overrode inline `style="display:none"`. Fix: ensure no `!important` display rules conflict with dynamic show/hide.

### 15.7 Icon-in-Input Pattern (REVERTED)
`.sys-input-icon` pattern implemented then reverted. Use plain `form-control` + `input-group` for password eye. See §4.8.

### 15.8 `:last-child` Card Gap (SOLVED)
`:last-child { margin-bottom: 0 }` broke when cards were in wrapper divs. Use `margin-bottom:10px !important` on every card individually.

### 15.9 Domain Stats "Unknown" (SOLVED)
Profile stats showed "Unknown: N" when AD action logged before any `domain_switch` event. Fixed: use `ldap_active_domain_key()` as default domain instead of hardcoded 'Unknown'.

### 15.10 CSRF Timing (SOLVED)
Profile page quick stats showed 0 on page reload because `loadProfile()` called `fetch()` before CSRF interceptor installed. Fixed: `setTimeout(loadProfile, 0)` + defensive `window._csrfToken` check with retry.

### 15.11 Dropdown Z-Index Inside Card Stacking Context (SOLVED)
Dropdowns (`custom-select-dropdown`) appearing behind sibling content (password section, form action buttons) inside the same card, despite having `z-index: 9999`.

**Root Cause:**
- `.card` (Bootstrap) has `position: relative`. `.overflow-visible-card` adds `z-index: 10` → card creates a stacking context at z-index 10.
- `.btn-primary` (theme CSS) sets `position: relative !important` — positioned but no z-index (z-index auto = 0).
- `.workspace-content-scroll .card:hover` (layout.css) sets `transform: translateY(-1px) !important` on hover → creates an additional stacking context.

**Fix — Isolate dropdown in its own stacking context:**
```html
<div class="row" style="position: relative; z-index: 99999;">
    <div class="col-md-12">
        <div class="custom-select-container">
            <input type="text" id="..." class="form-control" placeholder="...">
            <input type="hidden" id="...">
            <div class="custom-select-dropdown">
                <ul class="custom-select-list"></ul>
            </div>
        </div>
    </div>
</div>
```
- Giving the **row** (`position: relative; z-index: 99999`) creates a fresh stacking context at z-index 99999 within the card.
- Multiple dropdown rows should use descending z-index (e.g., OU: `z-index: 99999`, Group: `z-index: 99998`) to maintain proper layering when both are open.

**Key: DON'T rely on the dropdown's own `z-index` alone. Create a stacking context on the PARENT row that encompasses the dropdown AND positions it above competing content.**

---

## 18. Chart / Graph Rendering Conventions (Monitoring Page)

### 18.1 HTML Overlay Text (Mandatory)

**All chart text (legend, axis labels, spike labels, time labels) MUST be rendered as HTML overlay elements, NOT canvas `fillText()`.**

```javascript
// WRONG - canvas pixels, not selectable
cx.fillStyle=s.color;cx.font='9px monospace';cx.fillText(txt,lgX+12,lgY);

// CORRECT - HTML overlay, selectable & copyable
var row=document.createElement('div');
row.style.cssText='position:absolute;left:'+lgX+'px;top:'+lgY+'px;white-space:nowrap;user-select:text;cursor:default;';
row.innerHTML='<span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:'+s.color+';margin-right:6px;"></span><span style="color:'+s.color+';font-size:10px;font-weight:600;">'+txt+'</span>';
ov.appendChild(row);
```

**Overlay container pattern:**
```javascript
var ovId=c.id+'_overlay';
var ov=document.getElementById(ovId);
if(!ov){
    ov=document.createElement('div');
    ov.id=ovId;
    ov.style.cssText='position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;font-family:Roboto,Poppins,Kalpurush,sans-serif;font-size:10px;';
    c.parentElement.style.position='relative';
    c.parentElement.appendChild(ov);
}
ov.innerHTML=''; // Clear on each render
```

### 18.2 Trend Chart Config (renderSysTrendChart)

All trend charts (CPU, DISK, container CPU/MEM/NET) use `renderSysTrendChart()` — custom Canvas 2D + HTML overlay.

| Option | Value |
|--------|-------|
| Font family | `Roboto,Poppins,Kalpurush,sans-serif` |
| Font size | `10px` (minimum) |
| pad.t | 15 |
| pad.b | 30 |
| pad.l | 34 |
| Y-axis label width | 30px container |
| Time labels | Max 3 per chart, label width 44px |
| Spike labels | Clamped within chart bounds, min gap 28px, horizontal stagger + vertical offset per series |
| Legend | Injected into chart title DOM element (`chart-legend-inline`) as `<span>` with colored dot + label + value |
| Tooltip | Dark background (`#0f172a`), colored dot `●` + label + formatted value + time |
| CSV Export | Format: `HH:MM:SS AM/PM`, auto-scale units (KB/MB/GB/s) |

### 18.3 Chart.js Charts (Advanced Analytics)

For Chart.js charts on white backgrounds, ALL color values MUST be dark/visible:

| Element | Light BG Value | Dark BG Value |
|---------|---------------|---------------|
| Legend labels | `#374151` | `rgba(255,255,255,0.7)` |
| Axis ticks | `#6b7280` | `rgba(255,255,255,0.5)` |
| Grid lines | `rgba(0,0,0,0.06)` | `rgba(255,255,255,0.05)` |
| Tooltip bg | `#0f172a` | `#0f172a` |
| Point labels (radar) | `#374151` | `rgba(255,255,255,0.6)` |

### 18.4 Series Colors

```javascript
var pl=['#3b82f6','#ef4444','#22c55e','#f59e0b','#a855f7','#06b6d4','#ec4899','#84cc16','#f97316','#6366f1'];
```

### 18.5 DO / DON'T for Charts

| DO | DON'T |
|----|-------|
| HTML overlay for all text (`user-select:text`) | Canvas `fillText()` for labels |
| Font: `Roboto,Poppins,Kalpurush,sans-serif` | Monospace for chart labels |
| Font size: 10px minimum | Sizes below 10px |
| Series color for legend/spike text | White/fixed color text on light bg |
| Tooltip with colored bullets | Plain text tooltip |
| 12-hour AM/PM timestamps | 24-hour timestamps |
| Export CSV button visible | No export option |
| Dark tooltip bg (`#0f172a`) on all charts | White/default tooltip on light bg |

---

## 19. Testing Requirements

- Hard refresh (Ctrl+F5) required after JS/CSS changes to clear browser cache.
- OPcache clear after PHP changes: `docker exec accesspilot_php php -r 'opcache_reset();'`
- Check browser console for `[INFO] refreshInfoCards: users = [...]` to verify multi-user flow.
- Test with browser DevTools network tab to verify LDAP vs PowerShell backend.
- For Docker: container restart required after WinCP file transfer (inode change).

---

## 20. DO / DON'T Quick Reference

### DO
- Use standard `.row` (no `gx-0`) for layouts
- Use `margin-bottom:10px !important` for card vertical gaps
- Use `overflow:hidden !important` on cards
- Use `class="card app-table-card"` for every card
- Use `<h3 class="log-title-wrapper app-table-title">` for card titles
- Use `card-body.no-padding` + inner `<div class="p-2">` for titled card content
- Use `card-body.p-2` for compact cards without title bar
- Use `var(--font-*)` for all font sizes in CSS
- Use `.font-tech` for monospace/technical text
- Use `.card-title` for small info card headers (in sidecard/context panels)
- Relocate modals to `document.body`
- Use inline row expand instead of modals for table edits
- Use `position: fixed` + `getBoundingClientRect()` for dropdowns when clipping occurs
- Re-trigger init on `spaContentUpdated`
- Clear inputs on `hidden.bs.modal`
- Guard init with `dataset.initialized`
- Strip per-user summary lines in multi-ID aggregator
- Use `border-left:3px solid #COLOR` for section differentiation (see §4.4)

### DON'T
- Use `gx-0` for card wrappers
- Use `mb-2` or any Bootstrap margin class for card gaps — use inline `margin-bottom:10px !important`
- Use `margin-top` for card gaps
- Omit `overflow:hidden` on cards (breaks border-radius)
- Omit `!important` on card margin
- Create custom CSS overrides for column gaps — rely on layout.css defaults
- Use `pe-xl-*`/`ps-xl-*` on columns — layout.css `!important` overrides them
- Use `container-fluid` in workspace pages
- Use `.btn-sm` on form action buttons
- Use custom button classes (`.btn-noc-premium`, etc.)
- Use `.btn-outline-secondary` for Cancel buttons
- Use `form-control-sm`, `input-group-sm`, `sys-label` on form elements
- Leave modals inside `.workspace-content-scroll`
- Include `action_taken_card.php` in view files
- Define theme button properties outside `theme.css`
- Hardcode font sizes in CSS files (JS-generated HTML is excepted)
- Use `min-height` on initially-empty status elements
- Put Save before Cancel in card form footers
- Use `:last-child` gap reset on cards
- Add stacked CSS overrides — replace conflicting rules
- Use `!important` display rules on elements shown/hidden dynamically
- Use `rgba(255,255,255,*)` for text on light/white backgrounds in Chart.js charts
- Use `font-size` below 10px in chart overlays
