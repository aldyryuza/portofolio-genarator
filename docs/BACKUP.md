# Backup and restore

A complete backup is three things. Missing any one of them means a partial restore.

| What | Where | Why |
|---|---|---|
| Database | MySQL database from `DB_DATABASE` | All content, owner account, CV/media records, inbox |
| Private files | `storage/app/private/media/` | Uploaded originals and their WebP derivatives (images, CV and certificate PDFs) |
| `APP_KEY` | `.env` | Decrypts sessions and contact-form tokens; keys the stored IP hashes. Keep it in a password manager, never in the backup archive next to the database. |

`storage/app/private/exports/` (generated PDF/PPTX) and `storage/app/private/livewire-tmp/` can be regenerated and do not need backing up.

## Create a backup

Use a `mysqldump` that matches the server's major version. On the Laragon dev machine the binary on `PATH` is MySQL 5.6; use the 8.0 one explicitly:

```bash
# Windows / Laragon (Git Bash)
MYSQL8="/d/SOFTWARE/laragon/bin/mysql/mysql-8.0.30-winx64/bin"
"$MYSQL8/mysqldump.exe" -h 127.0.0.1 -u root --single-transaction --routines --no-tablespaces \
  --default-character-set=utf8mb4 portofolio-generator > portfolio-$(date +%F).sql

# Linux server
mysqldump --single-transaction --routines --no-tablespaces --default-character-set=utf8mb4 \
  -u "$DB_USERNAME" -p "$DB_DATABASE" > portfolio-$(date +%F).sql
```

Then archive the media directory:

```bash
tar -czf portfolio-media-$(date +%F).tar.gz -C storage/app/private media
```

Recommended: daily database dump, daily media archive, keep 14 daily + 8 weekly copies, and store at least one copy off the server. Both archives contain personal data (password hash, contact messages, private CVs) — encrypt them at rest.

## Restore

1. Put the site in maintenance mode: `php artisan down`.
2. Restore the database into an **empty** database:
   ```bash
   mysql -u "$DB_USERNAME" -p --default-character-set=utf8mb4 "$DB_DATABASE" < portfolio-YYYY-MM-DD.sql
   ```
3. Restore files: `tar -xzf portfolio-media-YYYY-MM-DD.tar.gz -C storage/app/private`.
4. Make sure `.env` has the **original** `APP_KEY`.
5. `php artisan migrate --force` (applies migrations newer than the backup, if any), then `php artisan optimize`.
6. `php artisan up`.

## Verify a restore

- `php artisan migrate:status` shows every migration as *Ran*.
- Log in as the owner; open **Media** and check thumbnails load; download the default CV from `/cv`.
- Open a published project page and confirm images and videos render.

## Drill log

| Date | What was tested | Result |
|---|---|---|
| 2026-11-20 | Dumped the dev database with mysqldump 8.0.30 and restored it into the disposable `portofolio-generator_testing` database; compared row counts of every table | All tables matched |
