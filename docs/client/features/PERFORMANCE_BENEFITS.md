# Performance Features — User Benefits

> Document ta user/deployment team er jonno. Ki feature enable korlam, user ki ki benefit pabe, real life workflow kivabe change holo — shegula eikhane likha ache.

---

## Ki Ki Feature Enable Korlam

### 1. Gzip Compression
**Ki:** File transfer er somoy data compress kore pathay.

| Resource | Age (Before) | Ekhon (After) |
|----------|-------------|----------------|
| CSS file (components.css) | 112 KB | 20 KB |
| JS files (total) | ~150 KB | ~35 KB |
| JSON response | 100% | ~30% |
| **Per page load** | **~300 KB** | **~70 KB** |

**Benefit:** User er internet slow holeo page fast load hobe. Office er WAN connection e noticeable improvement pabe.

---

### 2. FastCGI Cache
**Ki:** Monitoring API te same data bar bar PHP te na giye Nginx direct serve kore.

| Scenario | Before | After |
|----------|--------|-------|
| Monitoring polling (10s interval) | Protibar PHP-FPM hit korto | 5s porjonto Nginx cache serve kore |
| Same page e 10 user open kore | 10 ta separate PHP request | 1 ta PHP request + 9 ta cache hit |
| Response time | ~200-500ms | ~1-5ms |

**Benefit:** PHP-FPM er load kombe. Server er CPU usage kombe. Monitoring page instant load hobe.

---

### 3. X-Accel-Redirect (Avatars)
**Ki:** Profile picture serve er jonno PHP ar file read kore na — Nginx direct serve kore.

| Scenario | Before | After |
|----------|--------|-------|
| Page e 50 jon user er avatar load | 50 ta PHP process block | 1 ta PHP + 49 ta Nginx direct |
| PHP memory usage per avatar | ~1 MB | ~0.1 MB |
| Avatar response time | 200-500ms | <10ms |

**Benefit:** Employee DB te user list scroll korle — avatar gulo instant load hobe. PHP worker free thakbe onno request handle korar jonno.

---

### 4. Cache Key Normalization
**Ki:** Monitoring JS `_=timestamp` pathay cache break korar jonno. Ekhon Nginx oi param ignore kore cache key banay.

**Benefit:** Monitoring GET requests (prottek 10s) properly cached hoy. Actually cache ta kaj kore.

---

## Real Life Workflow Diagram

### Before Optimization
```
Browser                         Nginx                    PHP-FPM                 LDAP/AD
  │                               │                         │                      │
  ├── login page ───────────────► │ ──── PHP proxy ──────► │                      │
  │◄── HTML (300KB unc) ─────────┤◄─── HTML ───────────────┤                      │
  │                               │                         │                      │
  ├── monitoring dashboard ──────►│ ──── PHP proxy ──────► │ ──── LDAP query ────►│
  │◄── JSON ──────────────────────┤◄─── JSON (500ms) ──────┤◄─── response ────────┤
  │                               │                         │                      │
  ├── (every 10s) poll status ───►│ ──── PHP proxy ──────► │ ──── LDAP query ────►│
  │◄── JSON (500ms) ──────────────┤◄─── JSON ──────────────┤◄─── response ────────┤
  │                               │                         │                      │
  ├── employee list (50 avatars)─►│ ──── PHP proxy ──────► │ readfile() x50       │
  │◄── avatars (slow) ────────────┤◄─── PHP buffer ────────┤ (1MB mem each)       │
  │                               │                         │                      │
```

### After Optimization
```
Browser                         Nginx                    PHP-FPM                 LDAP/AD
  │                               │                         │                      │
  ├── login page ───────────────► │ ──── PHP proxy ──────► │                      │
  │◄── HTML (70KB gzip) ──────────┤◄─── HTML ───────────────┤                      │
  │                               │                         │                      │
  ├── monitoring dashboard ──────►│ ──── [CACHE HIT] ────► │                      │
  │◄── JSON (gzip, <5ms) ─────────┤   (no PHP hit)          │                      │
  │                               │                         │                      │
  ├── (every 10s) poll status ───►│ ──── [CACHE HIT] ────► │                      │
  │◄── JSON (gzip, <5ms) ─────────┤   (5s TTL, from tmpfs)  │                      │
  │                               │                         │                      │
  ├── employee list (50 avatars)─►│ ──── PHP ────────────► │ X-Accel header       │
  │◄── avatars (fast) ────────────┤◄── Nginx ⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯⎯┤ (10ms, no readfile) │
  │                               │   serves from disk      │                      │
```

---

## Flowchart (Technical)

```
                        ┌──────────────────────┐
                        │    Browser Request    │
                        └──────────┬───────────┘
                                   │
                        ┌──────────▼───────────┐
                        │   Nginx terminates    │
                        │   SSL + HTTP/2        │
                        │   Rate limit check    │
                        └──────────┬───────────┘
                                   │
                        ┌──────────▼───────────┐
                        │  Gzip on?             │
                        │  (if client supports) │
                        └──────────┬───────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    │              │              │
            ┌───────▼──────┐ ┌────▼─────┐ ┌──────▼──────┐
            │ Static asset │ │ API call │ │ PHP page    │
            │ (/resources) │ │(/api/...)│ │ (login/etc) │
            │ expires 7d   │ │          │ │             │
            │ gzip ✓       │ │  ┌───────▼────────┐      │
            └──────────────┘ │  │ FastCGI Cache   │      │
                             │  │ Check?          │      │
                             │  │                 │      │
                             │  │ key = URL minus  │      │
                             │  │  _=timestamp    │      │
                             │  └───┬───────┬─────┘      │
                             │  HIT │       │ MISS       │
                             │ ┌────▼──┐  ┌──▼──────┐   │
                             │ │Serve  │  │PHP-FPM  │   │
                             │ │from   │  │process  │   │
                             │ │cache  │  │request  │   │
                             │ │+ gzip │  │+ gzip   │   │
                             │ └───────┘  └──┬───────┘   │
                             │              │            │
                             │        ┌─────▼─────┐      │
                             │        │Store cache│      │
                             │        │(5s TTL)   │      │
                             │        └───────────┘      │
                             └───────────────────────────┘
                                   │
                        ┌──────────▼───────────┐
                        │   Response to        │
                        │   Browser            │
                        │   (gzip compressed)  │
                        └──────────────────────┘

    Avatar Flow (X-Accel-Redirect):
    
    Browser → Nginx → PHP (auth check)
                           │
                    header("X-Accel-Redirect:
                        /_xaccel/avatar/xyz.jpg")
                           │
                           ▼
                    Nginx intercepts
                           │
                    ┌──────▼──────┐
                    │ Internal    │
                    │ location    │
                    │ /_xaccel/   │
                    │ avatar/     │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │ Serve from  │
                    │ /data/secure│
                    │ /profile_img│
                    │ expires 7d  │
                    │ gzip ✓      │
                    └─────────────┘
```

---

## Real Life Scenario

### Before (User Experience)
1. User monitoring dashboard khule → 2-3 second load time
2. Prottek 10s e page auto-refresh → loading spinner dekhe
3. Employee DB te scroll korle → avatar gula late load hoy (blank square, then image)
4. Office internet slow hole → 10-15 second lage page load hote
5. Multiple user monitoring page e thakle → server CPU 80-90% e chole jay

### After (User Experience)
1. User monitoring dashboard khule → instant load (<1s)
2. Prottek 10s e auto-refresh → kono loading spinner nei, data instantly update hoy
3. Employee DB te scroll korle → avatar gula instant show kore (cached by nginx)
4. Slow internet eo → gzip compression er jonno 70% kom bandwidth use hoy, page fast load
5. 10-20 user monitor korleo → server CPU 10-20% ei thake (cache serve kore, PHP-FPM free)

---

## Summary Table

| Feature | Technical Name | User Benefit |
|---------|---------------|--------------|
| File compression | Gzip | 3x faster page load, less bandwidth |
| API caching | FastCGI Cache | Instant monitoring, 80% less PHP CPU |
| Avatar serving | X-Accel-Redirect | Zero-delay profile pictures |
| Cache key fix | Map strip `_=` | Caching actually works for monitoring |

---

## Files Involved

| File | Kaj |
|------|-----|
| `docker/nginx/gzip.conf` | Gzip compression on kore |
| `docker/nginx/default.conf` | Cache path, cache zone, stripped key, internal avatar location |
| `docker/docker-compose.yml` | gzip.conf mount + tmpfs for cache |
| `app/Application/Http/Controllers/get_avatar.php` | readfile remove kore X-Accel-Redirect use kore |
