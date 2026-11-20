# Deployment checklist

Single Laravel monolith; no queue worker is required (exports and mail run synchronously).

## Server requirements

- PHP 8.4 with `gd` (WebP + JPEG), `zip`, `exif`, `intl`, `mbstring`, `fileinfo`, `pdo_mysql`, `dom`, `xml`
- MySQL 8.x, Composer 2, Node.js 22 (build only)
- `upload_max_filesize` ≥ 12M and `post_max_size` ≥ 16M (Livewire's temporary upload limit is 12 MB)
- `memory_limit` ≥ 256M (image re-encoding and PDF rendering); 512M recommended

## Environment (`.env`)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
- [ ] `APP_KEY` generated once with `php artisan key:generate` and stored safely (see `docs/BACKUP.md`)
- [ ] `DB_*` point at the production database; the DB user has no privileges beyond that database
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Real `MAIL_*` settings so contact-form notifications are delivered (`MAIL_MAILER=log` only writes to the log)
- [ ] `LOG_LEVEL=warning` or higher

## First deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan portfolio:create-owner      # prompts for the password; never use a default one
php artisan optimize
```

## Web server

- Document root is `public/` — never the project root.
- Only `public/` is web-readable. Uploads live in `storage/app/private` and are served by the app with authorization; do **not** run `php artisan storage:link` for them.
- Deny dotfiles (`.env`, `.git`).
- Serve over HTTPS only; HTTP redirects to HTTPS. The app sends HSTS and `upgrade-insecure-requests` in production over HTTPS.
- Nginx: `client_max_body_size 16m;` and `try_files $uri $uri/ /index.php?$query_string;`.

## Scheduler

Add one cron entry so `portfolio:prune` (deleted media files and exports older than 30 days) runs daily:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Security headers (sent by the app)

CSP with per-request nonces (no `unsafe-eval` on public pages; admin/login pages allow it for Alpine), `X-Frame-Options: SAMEORIGIN`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag: noindex` on admin and preview URLs, and HSTS in production. If a CDN or proxy adds its own CSP, make sure it does not loosen these.

## After each deploy

- [ ] `php artisan migrate --force` and `php artisan optimize`
- [ ] `composer audit` and `npm audit --omit=dev` are clean
- [ ] `/robots.txt` lists the sitemap (production only) and `/sitemap.xml` contains only published projects
- [ ] Log in, upload a test image, open a public page, download the CV, send a test contact message
- [ ] Check the browser console on the home page and in the admin for CSP violations

## Updating

`php artisan down` → pull → `composer install --no-dev --optimize-autoloader` → `npm ci && npm run build` → `php artisan migrate --force` → `php artisan optimize` → `php artisan up`. Take a backup first.
