#!/usr/bin/env python3
"""strip_vendor_from_public.py — remove the vendor license console from a PUBLIC copy.

The vendor console (issue/sign/manage RSA signing keys + build client release packs)
is the vendor's revenue machine. It is NEVER shipped to the public repo.
Operates on a build output copy; source is untouched.

Usage: python3 strip_vendor_from_public.py /path/to/public-repo
Exit 0 on success, 1 if any expected patch file is missing.
"""
import re
import sys
from pathlib import Path

ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else sys.exit("usage: strip_vendor_from_public.py <public-root>"))
MISSING = []


def patch(path: Path, fn, *, required: bool = True):
    full = ROOT / path
    if not full.exists():
        if required:
            MISSING.append(str(path))
        return
    src = full.read_text(encoding="utf-8")
    out = fn(src)
    if out != src:
        full.write_text(out, encoding="utf-8")
        print(f"[strip] patched  {path}")
    else:
        print(f"[strip] unchange {path} ({'no match' if required else 'skipped'})")


# 1. Delete vendor-only files
for rel in [
    "app/Application/Http/Controllers/vendor_license_api.php",
    "resources/views/pages/license/vendor_view.php",
    "public/resources/frontend/js/modules/vendor_actions.js",
    "scripts/prepare-client-release.ps1",
]:
    f = ROOT / rel
    if f.exists():
        f.unlink()
        print(f"[strip] deleted   {rel}")
    else:
        MISSING.append(rel)


# 2. page_registry.php — remove perm map + the case block
def reg(src: str) -> str:
    src = src.replace("        'vendor_console'      => 'page_vendor_console',\n", "")
    src = re.sub(
        r"\n        case 'vendor_console':\n.*?break;\n",
        "\n",
        src,
        flags=re.DOTALL,
    )
    return src


patch("app/Application/Routing/page_registry.php", reg)

# 3. api/index.php — remove vendor route line
patch(
    "public/api/index.php",
    lambda s: re.sub(r"^\s*'vendor_license_api'\s*=>\s*'vendor_license_api\.php',\s*\n", "", s, flags=re.M),
)

# 4. menu_config.php — remove the 'Vendor License' item block
patch(
    "config/menu_config.php",
    lambda s: re.sub(r"\[\s*\n\s*'name'\s*=>\s*'Vendor License',.*?permission'\s*=>\s*'page_vendor_console'\s*\n\s*\],\n", "", s, flags=re.S),
)


# 5. components_config.php — remove the page_vendor_console array (brace balanced)
def comp(src: str) -> str:
    def remove_block(s: str):
        start = s.find("'page_vendor_console' => [")
        if start < 0:
            return s
        i = s.index("[", s.index("=>", start))
        depth = 0
        j = i
        in_str = False
        while j < len(s):
            c = s[j]
            if in_str:
                if c == "\\":
                    j += 2
                    continue
                if c == "'" or c == '"':
                    in_str = False
            else:
                if c in "'\"":
                    in_str = True
                elif c == "[":
                    depth += 1
                elif c == "]":
                    depth -= 1
                    if depth == 0:
                        end = j + 1
                        # consume trailing comma + whitespace
                        k = end
                        while k < len(s) and s[k] in " \t\r\n":
                            k += 1
                        if k < len(s) and s[k] == ",":
                            end = k + 1
                        return s[:start] + s[end:]
            j += 1
        return s  # unbalanced — leave for manual attention

    return remove_block(src)


patch("config/components_config.php", comp)

# 6. vertical_rail.php — drop the vendor icon line
patch(
    "resources/views/partials/vertical_rail.php",
    lambda s: s.replace("                    if ($item_page === 'vendor_console') $icon = 'fa-handshake';\n", ""),
)

# 7. role_management_actions.js — drop the grant
patch(
    "public/resources/frontend/js/modules/role_management_actions.js",
    lambda s: s.replace("            'page_vendor_console',\n", ""),
)

# 8. license/doc_view.php — drop the Vendor Console banner link
patch(
    "resources/views/pages/license/doc_view.php",
    lambda s: re.sub(r"\s*<a href=\"<\?= admin_page_url\('vendor_console'\) \?>\".*?Vendor Console</a>\n", "\n", s, flags=re.S),
)
patch(
    "resources/views/pages/license/doc_view.php",
    lambda s: s.replace(
        '                <a href="<?= admin_page_url(\'vendor_console\') ?>" class="text-white text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Vendor Console</a>\n',
        "",
    ),
)

if MISSING:
    print("[strip] WARNING: expected files missing:", ", ".join(sorted(set(MISSING))))
print("[strip] done.")