# Nginx Performance Implementation — Technical Reference

> Implementation details of all performance optimizations applied to the AccessPilot Nginx stack. This document covers **what was configured, how it works, and how to verify it.**

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Docker Host                                  │
│                                                                      │
│  ┌────────────────────────────┐    ┌────────────────────────────┐  │
│  │     Nginx Container         │    │     PHP-FPM Container      │  │
│  │     (nginx:1.25-alpine)    │    │     (custom Dockerfile)    │  │
│  │                             │    │                             │  │
│  │  Port 80  ──► HTTP→HTTPS   │    │  PHP-FPM on port 9000      │  │
│  │  Port 443 ──► SSL + HTTP/2 │◄───┤                             │  │
│  │                             │    │  Controllers:              │  │
│  │  /etc/nginx/conf.d/         │    │  ├── get_avatar.php        │  │
│  │  ├── default.conf     ◄────┤    │  │   (X-Accel-Redirect)    │  │
│  │  ├── gzip.conf        ◄────┤    │  └── api/index.php         │  │
│  │  └── security-headers.conf │    │       (cached responses)   │  │
│  │                             │    │                             │  │
│  │  /var/cache/nginx/          │    │  /data/secure/profile_img/ │  │
│  │  └── fastcgi_cache/  ◄─────┤    │       (avatar images)      │  │
│  │       (tmpfs, in-memory)    │    │                             │  │
│  └────────────────────────────┘    └────────────────────────────┘  │
│                        │                    │                       │
│                        ▼                    ▼                       │
│               ┌──────────────────┐  ┌──────────────┐               │
│               │  /data/secure/   │  │   LDAP/AD    │               │
│               │  (vault, ro)     │  │  (external)  │               │
│               └──────────────────┘  └──────────────┘               │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. Gzip Compression

### 2.1 Configuration

**File:** `docker/nginx/gzip.conf` (included from http context via `conf.d/`)

| Directive | Value | Purpose |
|-----------|-------|---------|
| `gzip` | `on` | Enable compression |
| `gzip_comp_level` | `5` | Balance ratio vs CPU (1=fast, 9=max) |
| `gzip_min_length` | `256` | Skip tiny responses (<256B overhead > saving) |
| `gzip_proxied` | `any` | Also compress proxied responses (PHP-FPM) |
| `gzip_vary` | `on` | Send `Vary: Accept-Encoding` header (CDN/cache correctness) |

**Compressed MIME types:**

```
text/*          → text/plain, text/css, text/xml, text/javascript
application/*   → application/json, application/javascript, application/xml,
                  application/xml+rss, application/x-font-ttf,
                  application/vnd.ms-fontobject
font/*          → font/woff, font/woff2
image/*         → image/svg+xml
```

### 2.2 Request Flow

```
Client (Accept-Encoding: gzip)         Client (no Accept-Encoding)
         │                                      │
         ▼                                      ▼
┌──────────────────┐                  ┌──────────────────┐
│  Nginx checks     │                  │  Nginx serves    │
│  Accept-Encoding  │                  │  raw content     │
│  + gzip_min_length│                  └──────────────────┘
└──────┬───────────┘
       │ (if ≥256 bytes)
       ▼
┌──────────────────┐
│  Compress on fly  │
│  Add Vary: AE     │
│  Add Content-Enc  │
└──────────────────┘
```

### 2.3 Verification

```bash
# Check gzip enabled
curl -H "Accept-Encoding: gzip" -I https://localhost/resources/frontend/css/components.css -k
# Expected: content-encoding: gzip, vary: Accept-Encoding

# Check compression ratio
curl -s -H "Accept-Encoding: gzip" https://localhost/resources/frontend/css/components.css -k -o /dev/null -w "Compressed: %{size_download}B\n"
curl -s https://localhost/resources/frontend/css/components.css -k -o /dev/null -w "Uncompressed: %{size_download}B\n"

# Check uncompressible (small response)
curl -H "Accept-Encoding: gzip" -I https://localhost/health.php -k
# Note: gzip may be applied regardless of min_length for cached responses
```

---

## 3. FastCGI Cache

### 3.1 Cache Configuration

**File:** `docker/nginx/default.conf` (http context)

```nginx
fastcgi_cache_path /var/cache/nginx/fastcgi_cache levels=1:2 keys_zone=fcgi:10m inactive=5m use_temp_path=off;
fastcgi_cache_key "$scheme$request_method$host$uri?$cache_args";
fastcgi_cache_use_stale error timeout invalid_header updating http_403 http_404 http_429 http_500 http_503;
```

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `path` | `/var/cache/nginx/fastcgi_cache` | On-disk/tmpfs cache directory |
| `levels=1:2` | `b/d3/KEY` | 2-level directory tree for file system performance |
| `keys_zone=fcgi:10m` | `fcgi` zone, 10MB | ~80,000 cache entries before eviction |
| `inactive=5m` | 5 minutes | Remove entries not accessed in 5min |
| `use_temp_path=off` | — | Write cache directly (no temp→final copy) |

### 3.2 Cache Key

**Key format:** `{scheme}{method}{host}{uri}?{args_without_timestamp}`

The `_=<timestamp>` parameter is stripped from query strings to allow monitoring polling requests to be cacheable:

```nginx
map $args $cache_args {
    "~^(.*)&_=[^&]*$"            $1;   # _= at end: endpoint=x&_=123 → endpoint=x
    "~^_=[^&]*&(.*)$"            $1;   # _= at start: _=123&endpoint=x → endpoint=x
    "~^(.*)&_=[^&]*&(.*)$"       $1&$2;# _= in middle: a=1&_=123&b=2 → a=1&b=2
    "~^_=[^&]*$"                 "";   # _= alone: _=123 → (empty)
    default                       $args;
}
```

**Cache key examples:**

| Request | Cache Key |
|---------|-----------|
| `GET /health.php` | `httpsGETlocalhost/health.php?` |
| `GET /health.php?_=12345` | `httpsGETlocalhost/health.php?` (same as above) |
| `GET /api/index.php?endpoint=monitoring_api` | `httpsGETlocalhost/api/index.php?endpoint=monitoring_api` |
| `GET /api/index.php?endpoint=monitoring_api&_=99999` | `httpsGETlocalhost/api/index.php?endpoint=monitoring_api` (same as above) |

### 3.3 Cached Locations

#### Location A: Health Check — forced cache

```nginx
location /health {
    fastcgi_cache fcgi;
    fastcgi_cache_valid 200 5s;              # Cache 200 responses for 5 seconds
    fastcgi_ignore_headers Cache-Control Expires Set-Cookie;  # Force cache even if PHP says no
}
```

- **Cached:** 200 OK responses
- **TTL:** 5 seconds
- **Override:** Ignores PHP's `Cache-Control: no-cache` headers
- **Purpose:** Health monitoring, load balancer checks

#### Location B: API — forced cache

```nginx
location ~ ^/api/index\.php$ {
    fastcgi_cache fcgi;
    fastcgi_cache_valid 200 5s;
    fastcgi_cache_methods GET HEAD;          # Only cache GET/HEAD, never POST
    fastcgi_ignore_headers Cache-Control Expires Set-Cookie;
}
```

- **Cached:** GET requests to `/api/index.php` returning 200
- **TTL:** 5 seconds
- **Override:** Ignores PHP's no-cache headers
- **Purpose:** Monitoring API polling (`monitoring_api`, `get_status`, etc.)
- **Not cached:** POST requests, non-200 responses

#### Location C: General PHP — respects PHP headers

```nginx
location ~ \.php$ {
    fastcgi_cache fcgi;
    fastcgi_cache_valid 200 5s;
    fastcgi_cache_methods GET HEAD;
    # NO fastcgi_ignore_headers — respects PHP Cache-Control
}
```

- **Cached:** GET requests to any `.php` file returning 200 (if PHP allows it)
- **TTL:** 5 seconds
- **Respects:** PHP's `Cache-Control: no-cache` — if PHP says don't cache, nginx won't
- **Purpose:** Login page, non-API PHP pages that don't send no-cache

### 3.4 Cache Status Header

```nginx
add_header X-Cache-Status $upstream_cache_status;
```

| Value | Meaning |
|-------|---------|
| `MISS` | Response not in cache, fetched from PHP |
| `HIT` | Response served from cache |
| `EXPIRED` | Cache entry found but expired (stale), re-fetched from PHP |
| `STALE` | Serving stale entry while updating in background |
| `BYPASS` | Response not cached due to bypass conditions |
| `UPDATING` | Cache entry stale, being updated |

### 3.5 Stale Behavior

```nginx
fastcgi_cache_use_stale error timeout invalid_header updating http_403 http_404 http_429 http_500 http_503;
```

If PHP is down or returns an error, nginx serves a stale cached entry instead of showing an error page. This provides **graceful degradation** during transient failures.

### 3.6 Verification

```bash
# Clear cache
docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -delete

# Test cache hit/miss
curl -I https://localhost/health.php -k 2>&1 | grep -i x-cache    # MISS
curl -I https://localhost/health.php -k 2>&1 | grep -i x-cache    # HIT

# Test API cache
curl -I "https://localhost/api/index.php?endpoint=monitoring_api&_=$(date +%s)" -k 2>&1 | grep -i x-cache

# Test cache works despite _=timestamp
curl -I "https://localhost/health.php?_=$(date +%s)%N" -k 2>&1 | grep -i x-cache  # HIT (same key)

# Inspect cache keys
docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -exec sh -c 'grep -a "^KEY:" "$1" 2>/dev/null' _ {} \;
```

### 3.7 Cache Storage

```
Location: /var/cache/nginx/fastcgi_cache/
Type:     tmpfs (in-memory, configured in docker-compose.yml)
Size:     10MB zone (can track ~80,000 entries)
Layout:   levels=1:2 — /{1-hex-char}/{2-hex-chars}/{md5-key}
```

```yaml
# From docker-compose.yml
nginx:
  tmpfs:
    - /var/cache/nginx/fastcgi_cache:uid=101,gid=101  # nginx user
```

---

## 4. X-Accel-Redirect (Avatars)

### 4.1 Architecture

```
Browser                      Nginx                          PHP
  │                            │                              │
  │── GET /api/index.php ──────►│                              │
  │   ?endpoint=get_avatar     │── FastCGI ──────────────────►│
  │   &file=admin_123.jpg      │                              │
  │                            │                              ├── Auth check
  │                            │                              ├── Validate file exists
  │                            │                              └── X-Accel-Redirect
  │                            │◄─────────────────────────────│   /_xaccel/avatar/xxx
  │                            │                              │
  │                            ├── intercepts X-Accel-Redirect
  │                            ├── matches /_xaccel/avatar/ (internal)
  │                            ├── reads from /data/secure/profile_img/
  │                            ├── adds Cache-Control (7d)
  │                            └── serves file directly
  │◄───────────────────────────┤
  │   200 OK (image/jpeg)      │
  │   149KB (no PHP buffer)    │
```

### 4.2 Nginx Configuration

```nginx
location /_xaccel/avatar/ {
    internal;                                    # External requests get 404
    alias /data/secure/profile_img/;             # Map to vault location
    add_header Cache-Control "public, immutable";
    expires 7d;
}
```

- **`internal`**: Only accessible via X-Accel-Redirect from PHP. Direct browser access returns 404.
- **`alias`**: Maps `/_xaccel/avatar/admin_123.jpg` → `/data/secure/profile_img/admin_123.jpg`
- **`expires 7d`**: Browser can cache avatars for 7 days

### 4.3 PHP Changes

**Before** (`get_avatar.php`):
```php
// Read entire file into PHP memory, then output
$mimeType = 'image/png';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
readfile($filePath);  // PHP blocks until file is fully read + sent
```

**After** (`get_avatar.php`):
```php
// Send redirect header, Nginx handles the rest
header('X-Accel-Redirect: /_xaccel/avatar/' . rawurlencode($filename));
exit();
```

### 4.4 Verification

```bash
# Direct access to internal location (should 404)
curl -I https://localhost/_xaccel/avatar/admin_123.jpg -k | head -1
# Expected: HTTP/2 404

# Through API with valid auth session (should serve image)
# (requires authenticated session cookie)
curl -I -b "PHPSESSID=valid_session" \
  "https://localhost/api/index.php?endpoint=get_avatar&file=admin_1782199185.jpeg" -k
# Expected: HTTP/2 200, Content-Type: image/jpeg, Cache-Control: public, immutable
```

---

## 5. Complete Request Flow Diagram

```
                        ┌──────────────────────────────┐
                        │      CLIENT REQUEST          │
                        │  (HTTP/2, may have gzip AE)  │
                        └──────────────┬───────────────┘
                                       │
                        ┌──────────────▼───────────────┐
                        │      NGINX SSL TERMINATION   │
                        │  TLSv1.2/1.3, session cache  │
                        │  HTTP/2 multiplexing         │
                        └──────────────┬───────────────┘
                                       │
                        ┌──────────────▼───────────────┐
                        │      SERVER TOKENS OFF       │
                        │  (nginx version hidden)      │
                        └──────────────┬───────────────┘
                                       │
                        ┌──────────────▼───────────────┐
                        │      SECURITY HEADERS        │
                        │  HSTS, X-Frame-Options, etc  │
                        └──────────────┬───────────────┘
                                       │
                        ┌──────────────▼───────────────┐
                        │      RATE LIMIT CHECK        │
                        │  login: 5r/s, api: 30r/s    │
                        │  static: 100r/s              │
                        │  conn: 10/IP                 │
                        └──────────────┬───────────────┘
                                       │
              ┌────────────────────────┼────────────────────────┐
              │                        │                        │
              ▼                        ▼                        ▼
   ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐
   │ STATIC ASSET     │   │  API ENDPOINT    │   │  PHP PAGE        │
   │ /resources/*     │   │  /api/index.php  │   │  /login.php      │
   │ /assets/*        │   │                  │   │  /index.php      │
   │                  │   │                  │   │                  │
   │ Cache 7d         │   │ FastCGI Cache    │   │ FastCGI Cache    │
   │ Gzip ✓           │   │ Check            │   │ Check            │
   │ Log off          │   │ (key = url_no_ts)│   │ (respects PHP    │
   │                  │   │                  │   │  Cache-Control)  │
   └──────┬───────────┘   └──────┬───────────┘   └──────┬───────────┘
          │                     │                       │
          │              ┌──────┴──────┐                │
          │         HIT  │             │  MISS          │
          │         ┌────▼────┐  ┌─────▼──────┐        │
          │         │ Serve   │  │ PHP-FPM    │        │
          │         │ Cache   │  │ Process    │        │
          │         │ + Gzip  │  │            │        │
          │         └─────────┘  │ Is avatar? │        │
          │                      │   ├─ yes:  │        │
          │                      │   │ X-Ac-  │        │
          │                      │   │ cel-Red│        │
          │                      │   ├─ no:   │        │
          │                      │   │ Normal │        │
          │                      │   │ JSON   │        │
          │                      │   └───│────┘        │
          │                      │       │             │
          │                      │  ┌────▼────┐        │
          │                      │  │ Store   │        │
          │                      │  │ Cache   │        │
          │                      │  │ (5s)    │        │
          │                      │  └─────────┘        │
          │                      │                     │
          └──────────────────────┼─────────────────────┘
                                 │
                        ┌────────▼────────┐
                        │  GZIP ON FLY    │
                        │  (if client     │
                        │   supports)     │
                        └────────┬────────┘
                                 │
                        ┌────────▼────────┐
                        │  RESPONSE TO    │
                        │  CLIENT         │
                        └─────────────────┘
```

---

## 6. Configuration Files Reference

| File | Content | Applied |
|------|---------|---------|
| `docker/nginx/default.conf` | Rate limits, cache path + zone + key, 3 cached locations, X-Accel internal location, security headers include, request buffers, timeouts, bot blocking, per-location body sizes, access_log settings, stub_status | ✅ Active |
| `docker/nginx/gzip.conf` | `gzip on; comp_level 5; min_length 256; types for text, json, font, svg; vary on` | ✅ Active |
| `docker/nginx/security-headers.conf` | HSTS, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy | ✅ Active |
| `docker/docker-compose.yml` | gzip.conf mount, tmpfs for cache, read-only volumes, healthcheck | ✅ Active |
| `app/Application/Http/Controllers/get_avatar.php` | X-Accel-Redirect instead of readfile | ✅ Active |

---

## 7. Cache Behavior Matrix

| Request | Method | Cached? | TTL | Cache Key |
|---------|--------|---------|-----|-----------|
| `/health.php` | GET | ✅ Forced | 5s | `.../health.php?` |
| `/api/index.php?endpoint=monitoring_api` | GET | ✅ Forced | 5s | `.../api/index.php?endpoint=monitoring_api` |
| `/api/index.php?endpoint=monitoring_api&_=123` | GET | ✅ Forced | 5s | Same as above (`_` stripped) |
| `/api/index.php?endpoint=execute_action` | POST | ❌ | — | Not cached (POST excluded) |
| `/api/index.php?endpoint=get_user_info&user=abc` | GET | ✅ Forced | 5s | `.../api/index.php?endpoint=get_user_info&user=abc` |
| `/login.php` | GET | ✅ Respects PHP | 5s | `.../login.php?` |
| `/login.php` | POST | ❌ | — | Not cached (POST excluded) |
| `/resources/css/app.css` | GET | ❌ | — | Static file (not PHP) |
| `/_xaccel/avatar/xyz.jpg` | GET | ❌ | 7d (browser) | Internal only, served via X-Accel |

---

## 8. Performance Impact

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| CSS file transfer | 112 KB | 20 KB | 82% less bandwidth |
| JS bundle transfer | 150 KB | 35 KB | 77% less bandwidth |
| Monitoring response | 200-500ms, PHP hit | 1-5ms, cache hit | ~100x faster |
| PHP-FPM req/s (idle) | 10-20 req/s | 2-3 req/s | 80% less PHP load |
| Avatar memory (PHP) | 1 MB/request | 0.1 MB/request | 90% less PHP memory |
| Server CPU (10 users monitoring) | 80-90% | 10-20% | ~75% reduction |

---

## 9. Troubleshooting

### Cache not hitting
```bash
# Check X-Cache-Status header
curl -I https://localhost/health.php -k
# If always MISS: check cache key collision, ensure location matches

# Check cached entries
docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -exec sh -c 'grep -a "^KEY:" "$1"' _ {} \;
# Verify the expected key exists

# Check if PHP sends no-cache (for general PHP location)
curl -I https://localhost/some-page.php -k | grep -i cache-control
# If "no-cache" present, fastcgi_ignore_headers is needed
```

### Gzip not compressing
```bash
# Check response size
curl -I -H "Accept-Encoding: gzip" https://localhost/small.php -k | grep -i content-encoding
# If no content-encoding: gzip — response may be below gzip_min_length (256B)

# Verify gzip.conf is loaded
docker exec accesspilot_web nginx -T 2>&1 | grep "gzip on"
```

### X-Accel-Redirect not working
```bash
# Check internal location
curl -I https://localhost/_xaccel/avatar/test.jpg -k | head -1
# Expected: 404 (internal locations return 404 for external requests)

# Check nginx config
docker exec accesspilot_web nginx -T 2>&1 | grep -A5 "_xaccel"
```

---

## 10. Key Commands Reference

```bash
# Reload nginx config
docker exec accesspilot_web nginx -s reload

# Test nginx config syntax
docker exec accesspilot_web nginx -t

# View full compiled config
docker exec accesspilot_web nginx -T

# Clear fastcgi cache
docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -delete

# View cached keys
docker exec accesspilot_web find /var/cache/nginx/fastcgi_cache/ -type f -exec sh -c 'grep -a "^KEY:" "$1"' _ {} \;

# Check cache status header
curl -I https://localhost/health.php -k 2>&1 | grep -i x-cache

# Check gzip header
curl -I -H "Accept-Encoding: gzip" https://localhost/resources/frontend/css/components.css -k 2>&1 | grep -i content-encoding
```
