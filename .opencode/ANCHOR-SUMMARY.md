## Goal
- Build a PHP/MySQL invoice app inspired by EasyInvoicePDF, ship open-source on GitHub, and deploy to a free cloud host with a shareable link.

## Constraints & Preferences
- MySQL DB: `invoice`, user `echo`, password `echo` (local Laragon)
- PHP runs on Laragon (Apache + MySQL) at `C:\laragon\www\invoice\` (files at project root)
- App URL (local): `http://localhost/invoice/` (no `/php` or `/index` in URL)
- Split-screen: 33% editor / 67% live preview
- Two templates: Classic (light header/footer) and Modern (gradient dark header)
- Discount: % or fixed, applied before tax; QR Code toggle for PDF
- A4 size: 210mm × 297mm; single-page print; footer pinned to bottom; amount in words; payment summary
- Clean URLs (no `.php`) via Apache `mod_rewrite`
- Open source: MIT, repo `https://github.com/codesmithsys/invoice.git` (HTTPS remote)
- Deployment target: **InfinityFree** (free PHP+MySQL shared hosting) — succeeded at `https://invoice.great-site.net`

## Progress
### Done
- Built full app: editor, dashboard, view, share, duplicate, delete
- Two A4 templates (Classic + Modern) with reduced padding for single-page print
- Logo upload, logo height slider, editable footer, amount-in-words, share links
- Clean URLs via `.htaccess`; `DirectoryIndex index.php`
- Open-sourced: `LICENSE` (MIT), `README.md`, `.gitignore`, `config.example.php`
- Fixed `config.php` redeclare error: wrap constants in `if (!defined(...))` and `getDB()` in `if (!function_exists(...))`
- Deployed to **InfinityFree** at `invoice.great-site.net` (GreatSite.net parent of InfinityFree)
  - User already had an InfinityFree account
  - DB: `if0_42097928_invoice` / user `if0_42097928` / pass `LX0dxb8zdlIhM` / port `3306`
  - `config.php` updated locally with these creds (gitignored)
  - Reverted `setup.sql` back to MySQL syntax (was briefly converted to PostgreSQL for Render)
  - Reverted `config.php` to MySQL-only (removed PostgreSQL branch)
  - Live dashboard verified rendering correctly with no errors
- Git history: HEAD `34c58b2` ("revert: switch back to MySQL for InfinityFree deployment")
- Earlier attempts (now abandoned): Railway (Trial maxed out), Render (cold-start/health-check issues)

### In Progress
- (None — fully deployed and working)

### Blocked
- (None — deployment is live)

## Key Decisions
- **Chose InfinityFree over Render/Railway** — no credit card, no trial limits, simple cPanel file upload
- Reverted PostgreSQL → MySQL (InfinityFree only offers MySQL)
- `config.php` keeps env var support as fallback layer (env vars if set, hardcoded creds otherwise) for portability
- Did not delete `Dockerfile` / `render.yaml` / `railway.json` from git history — left for future use, ignored on InfinityFree
- `setup.sql` uses `IF NOT EXISTS` for idempotency (safe to re-import)

## Next Steps
- User to import `setup.sql` via InfinityFree cPanel phpMyAdmin
- Test create → view → print preview → share link flow on `invoice.great-site.net`
- After verification, tag v1.0.0 release on GitHub
- (Optional) Delete the abandoned Render service to clean up dashboard

## Critical Context
- SSH push fails on this Windows: OpenSSH lacks `sntrup761x25519-sha512@openssh.com` KEX — use HTTPS remote
- MySQL binary: `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` (use `--password=echo` not `-pecho`)
- MySQL 8.4: no `ADD COLUMN IF NOT EXISTS`
- PHP binary: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- **Live URL**: `https://invoice.great-site.net` (InfinityFree free subdomain)
- **DB host** on InfinityFree: hostname value not explicitly confirmed — likely `localhost` (newer accounts 2023+) or `sqlXXX.infinityfree.com` (older) — current `config.php` falls back to InfinityFree creds when no env vars are set, DB_HOST itself is still picked from `getenv('DB_HOST')` or `config.example.php`. The current `config.example.php` has `DB_HOST = 'localhost'` which is the modern default.
- `calcTotals()`: `discount = net * (discountValue/100) || discountValue`; `tax = (net - discount) * (taxRate/100)`; `gross = net - discount + tax`
- Logo: FileReader data URL → `#logoPreview`; X button sets `remove_logo=1`; server clears `logo_path`
- `printPreview()` opens new window, sets `@page{size:A4 portrait}`, `page-break-inside:avoid` on `.invoice-card *`
- `view-public.php` uses token, shows "Shared Invoice" badge bar, no nav
- `.htaccess` rewrites: `^index$`, `^create$`, `^view$`, `^delete$`, `^duplicate$`, `^share$`, `^view-public$`
- Navbar dashboard links use `href="./"` to avoid `/index` in URL
- Git remote: `https://github.com/codesmithsys/invoice.git` (HTTPS)
- Local URL: `http://localhost/invoice/`
- Favicon: custom blue shield SVG, NOT derived from EasyInvoicePDF
- EasyInvoicePDF repo: `https://github.com/VladSez/easy-invoice-pdf` by Vlad Sazonau
- InfinityFree's parent brand is **GreatSite.net** (that's why the subdomain uses `great-site.net`)
- InfinityFree quirks: no shell access (must use phpMyAdmin to import SQL), free subdomains are like `xxx.infinityfreeapp.com` or `xxx.great-site.net` (legacy), env vars support is limited
- Files NOT to upload to InfinityFree: `.git/`, `Dockerfile`, `docker-entrypoint.sh`, `nginx.conf`, `render.yaml`, `railway.json`, `.dockerignore`, `README.md` (optional), `LICENSE` (optional)
- Files TO upload: all `*.php`, `style.css`, `favicon.svg`, `setup.sql`, `.htaccess`, `config.php` (with InfinityFree creds), `uploads/.gitkeep`
- Current GitHub HEAD: `34c58b2` on `main` branch

## Relevant Files
- `C:\laragon\www\invoice\.htaccess` — Apache clean-URL rewrites + `DirectoryIndex index.php` + `<FilesMatch>` block for sensitive files
- `C:\laragon\www\invoice\.gitignore` — Excludes `config.php`, `uploads/*`, OS/IDE junk
- `C:\laragon\www\invoice\.dockerignore` — Docker-only excludes (not used on InfinityFree)
- `C:\laragon\www\invoice\Dockerfile` — Docker PHP-FPM + nginx setup (not used on InfinityFree, kept for future)
- `C:\laragon\www\invoice\docker-entrypoint.sh` — Docker entrypoint (not used on InfinityFree)
- `C:\laragon\www\invoice\nginx.conf` — Nginx vhost (not used on InfinityFree, Apache is used)
- `C:\laragon\www\invoice\railway.json` — Railway config (not used, kept for history)
- `C:\laragon\www\invoice\render.yaml` — Render config (not used, kept for history)
- `C:\laragon\www\invoice\LICENSE` — MIT license
- `C:\laragon\www\invoice\README.md` — Full docs (should add InfinityFree deployment section)
- `C:\laragon\www\invoice\config.php` — **LOCAL + InfinityFree creds** (gitignored): DB_NAME=`if0_42097928_invoice`, DB_USER=`if0_42097928`, DB_PASS=`LX0dxb8zdlIhM`, DB_PORT=`3306`
- `C:\laragon\www\invoice\config.example.php` — DB config template with `if (!defined/!function_exists)` guards, default `DB_HOST = 'localhost'`
- `C:\laragon\www\invoice\setup.sql` — MySQL schema: `invoices` (with `share_token` UNIQUE, `footer_text`, `qr_show` TINYINT) + `invoice_items` (with FK CASCADE)
- `C:\laragon\www\invoice\index.php` — Dashboard with stats, table, clickable invoice numbers
- `C:\laragon\www\invoice\create.php` — Split-screen editor, two JS templates, live preview
- `C:\laragon\www\invoice\view.php` — Invoice view + Share modal
- `C:\laragon\www\invoice\view-public.php` — Public token-based invoice view
- `C:\laragon\www\invoice\share.php` — Share link API (generate/revoke)
- `C:\laragon\www\invoice\duplicate.php` — Invoice clone handler
- `C:\laragon\www\invoice\delete.php` — Invoice delete handler
- `C:\laragon\www\invoice\style.css` — Design system, `.nav-links a{color:#ffffff}`
- `C:\laragon\www\invoice\favicon.svg` — Custom blue shield icon
- `C:\laragon\www\invoice\uploads\.gitkeep` — Keeps uploads dir in git
