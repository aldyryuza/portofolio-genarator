# Running with Docker

The repository ships a production setup: `Dockerfile` (multi-stage) and `compose.yaml`.

| Service | Image | Role |
|---|---|---|
| `web` | nginx (target `web`) | Serves `public/` and passes PHP requests to `app`. Listens on `127.0.0.1:8080` by default. |
| `app` | PHP 8.4-FPM (target `app`) | The application. Runs migrations on start (`RUN_MIGRATIONS=true`). |
| `scheduler` | same as `app` | `php artisan schedule:work` (daily `portfolio:prune`). |
| `db` | `mysql:8.0` | Database, data in the `mysql` volume. |

Uploads, CVs and exports live in the `storage` volume. HTTPS is expected to be terminated by a reverse proxy on the host (nginx, Caddy, Traefik, Cloudflare Tunnel, …) that forwards to `127.0.0.1:8080`.

## 1. Prepare `.env` on the server

Start from `.env.example` and set at least:

```dotenv
APP_NAME="Your Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=                      # see step 2
LOG_LEVEL=warning

DB_DATABASE=portfolio
DB_USERNAME=portfolio
DB_PASSWORD=<long random password>
DB_ROOT_PASSWORD=<another long random password>

SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp              # plus MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS
```

`DB_HOST`, `DB_CONNECTION`, `LOG_CHANNEL` and `TRUSTED_PROXIES` are set by `compose.yaml`. Optional: `WEB_PORT` (default 8080), `WEB_BIND` (default 127.0.0.1), `IMAGE_TAG`.

Keep `.env` readable only by you (`chmod 600 .env`). It is never copied into the image (`.dockerignore`).

## 2. Build, generate the key, start

```bash
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
# paste the printed value into APP_KEY in .env, then:
docker compose up -d
docker compose ps            # web should become "healthy"
```

## 3. Create the owner account

```bash
docker compose exec app php artisan portfolio:create-owner
```

Production requires a strong password (12+ characters, upper and lower case, a number and a symbol) and checks it against known breaches online (haveibeenpwned range API), so the server needs outbound HTTPS for this step.

## 4. Reverse proxy (HTTPS)

Point your proxy at `http://127.0.0.1:8080` and forward the usual headers. Example for nginx on the host:

```nginx
server {
    server_name your-domain.com;
    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
    }
    # listen 443 ssl; + certificates (e.g. certbot --nginx)
}
```

Caddy equivalent: `your-domain.com { reverse_proxy 127.0.0.1:8080 }`.

## Updating

```bash
git pull
docker compose build
docker compose up -d          # app migrates on start
```

Take a backup first.

## Backups

```bash
# Database
docker compose exec -T db sh -c 'mysqldump --single-transaction --routines --no-tablespaces -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > portfolio-$(date +%F).sql
# Uploaded files
docker compose exec -T app tar -czf - -C storage/app/private media > portfolio-media-$(date +%F).tar.gz
```

Restore: `docker compose exec -T db sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < portfolio-YYYY-MM-DD.sql` and
`docker compose exec -T app tar -xzf - -C storage/app/private < portfolio-media-YYYY-MM-DD.tar.gz`. Keep `APP_KEY` with your backups' secrets (see `docs/BACKUP.md`).

## Useful commands

| Task | Command |
|---|---|
| Logs | `docker compose logs -f app web` |
| Artisan | `docker compose exec app php artisan <command>` |
| Clean old files now | `docker compose exec app php artisan portfolio:prune --dry-run` |
| Stop | `docker compose down` (data stays in volumes; `down -v` **deletes** them) |

## Using an existing MySQL server

Remove the `db` service and the `depends_on: db` blocks from `compose.yaml`, remove the `DB_HOST: db` lines, and set `DB_HOST`/`DB_PORT` in `.env`.

## Verified

Built and run locally on 2026-11-20 (Docker 29.8): all services healthy, migrations ran on start, owner created with the production password policy, image upload re-encoded to WebP, PDF/PPTX exports generated (Indonesian headings), public pages, sitemap and robots served, `.env` and non-entry PHP files blocked by nginx, HSTS/CSP/secure cookies behind a simulated HTTPS proxy, data persisted across container restarts.
