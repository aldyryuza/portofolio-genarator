# Running with Docker

The repository ships a production setup: `Dockerfile` (multi-stage) and `compose.yaml`.

| Service | Image | Role |
|---|---|---|
| `web` | nginx (target `web`) | Serves `public/` and passes PHP requests to `app`. Listens on `127.0.0.1:8090` by default (`WEB_PORT`). |
| `app` | PHP 8.4-FPM (target `app`) | The application. Runs migrations on start (`RUN_MIGRATIONS=true`). |
| `scheduler` | same as `app` | `php artisan schedule:work` (daily `portfolio:prune`). |
| `db` | `mysql:8.0` | Database `portofolio`, user `root`, data in the `mysql` volume. **No host port**, so it never clashes with another MySQL on the server. |

Uploads, CVs and exports live in the `storage` volume. HTTPS is expected to be terminated by a reverse proxy on the host (nginx, Caddy, Traefik, Cloudflare Tunnel, …) that forwards to `127.0.0.1:8090`.

## 1. Prepare `.env` on the server

```bash
cp .env.example .env
```

`.env.example` is already set up for a Docker server. Fill in the two values marked **REQUIRED**:

- `APP_URL` — your public address, e.g. `https://portfolio.example.com`.
- `APP_KEY` — see step 2.

Optional:

- `WEB_PORT` — host port for nginx (default `8090`). Check it is free: `ss -ltnp | grep :8090`.
- `WEB_BIND` — address nginx is bound to (default `127.0.0.1`, server-only). To test from another machine without a reverse proxy, set the server IP (e.g. a Tailscale IP) or `0.0.0.0`, set `APP_URL` to that `http://IP:PORT`, and leave `SESSION_SECURE_COOKIE` off until HTTPS is in place.
- `DB_FORWARD_PORT` / `DB_FORWARD_BIND` — host port for MySQL (default `127.0.0.1:3307`, so it does not clash with a MySQL on 3306). Only for database tools on the server; the app always uses `db:3306` inside Docker.
- `DOCKER_DB_DATABASE` / `DOCKER_DB_PASSWORD` — database name and root password (defaults `portofolio` / `root`). Use a stronger password if you ever bind the database port beyond `127.0.0.1`. These are applied **only when the `mysql` volume is first created**.
- `MAIL_*` — real SMTP settings so contact-form notifications are delivered.
- `SESSION_SECURE_COOKIE=true` — once the site is served over HTTPS.

The `DB_*` values in `.env` are ignored by Docker (`compose.yaml` sets them). Keep `.env` readable only by you (`chmod 600 .env`). It is never copied into the image (`.dockerignore`).

## 2. Build, generate the key, start

```bash
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
# paste the printed value into APP_KEY in .env, then:
docker compose up -d
docker compose ps            # web should become "healthy"
curl -I http://127.0.0.1:8090/up
```

If `APP_KEY` is still empty, the `app` container stops with a message telling you how to generate it.

### Changing database credentials after the first start

MySQL keeps the credentials it was created with. If you change `DOCKER_DB_*` later (or the first start used other values), the app gets "Access denied" or "Unknown database". On a fresh install **without data** start over with `docker compose down -v && docker compose up -d` (this deletes the database and upload volumes). With real data, change the password inside MySQL instead.

## 3. Create the owner account

```bash
docker compose exec app php artisan portfolio:create-owner
```

Production requires a strong password (12+ characters, upper and lower case, a number and a symbol) and checks it against known breaches online (haveibeenpwned range API), so the server needs outbound HTTPS for this step.

## 4. Reverse proxy (HTTPS)

Point your proxy at `http://127.0.0.1:8090` (or your `WEB_PORT`) and forward the usual headers. Example for nginx on the host:

```nginx
server {
    server_name your-domain.com;
    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
    }
    # listen 443 ssl; + certificates (e.g. certbot --nginx)
}
```

Caddy equivalent: `your-domain.com { reverse_proxy 127.0.0.1:8090 }`.

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

Remove the `db` service, the `depends_on: db` block and the `x-db-credentials` values from `compose.yaml`, then set `DB_*` in `.env`.

## Verified

Built and run locally on 2026-11-20 (Docker 29.8): all services healthy, migrations ran on start, owner created with the production password policy, image upload re-encoded to WebP, PDF/PPTX exports generated (Indonesian headings), public pages, sitemap and robots served, `.env` and non-entry PHP files blocked by nginx, HSTS/CSP/secure cookies behind a simulated HTTPS proxy, data persisted across container restarts.
