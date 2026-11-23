# Personal Portfolio CMS

Situs portofolio pribadi dengan CMS khusus satu pemilik (owner): halaman publik, admin untuk project/karier/media/CV, inbox kontak, serta export portofolio ke PDF dan PPTX.

Laravel 13 · PHP 8.4 · Livewire 4 · Tailwind CSS 4 · MySQL 8. Rincian fitur dan status ada di [`docs/RELEASE_NOTES.md`](docs/RELEASE_NOTES.md).

## Menjalankan secara lokal

```bash
composer install
npm install && npm run build
cp .env.example .env          # lalu isi DB_* untuk database development lokal
php artisan key:generate
php artisan migrate           # periksa dulu bahwa DB_* menunjuk ke database lokal
php artisan portfolio:create-owner
php artisan serve             # admin: /login
```

## Perintah penting

| Perintah | Fungsi |
|---|---|
| `php artisan portfolio:create-owner` | Membuat akun owner (password diminta, tidak ada default) |
| `php artisan portfolio:prune [--dry-run]` | Menghapus file media yang sudah dihapus >30 hari, export lama, dan data analytics >13 bulan (dijadwalkan harian) |
| `composer test` | Pint, PHPStan, lalu seluruh test |
| `DB_CONNECTION=mysql DB_DATABASE=<db_test> php artisan test` | Menjalankan test di MySQL (pakai database khusus test, **bukan** database dev) |

## Dokumentasi

- [`docs/DOCKER.md`](docs/DOCKER.md) — deploy dengan Docker Compose (sudah diuji)
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — checklist production
- [`docs/BACKUP.md`](docs/BACKUP.md) — backup & restore (sudah diuji)
- [`docs/RELEASE_NOTES.md`](docs/RELEASE_NOTES.md) — fitur, status acceptance, batasan
- `docs/PRD.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/ACCEPTANCE.md` — spesifikasi awal

## Prinsip penting
- Public front-end bebas diakses, admin panel hanya akun owner (single owner), tidak ada registrasi publik.
- Semua data contoh harus diberi label demo, tidak boleh mengarang pengalaman nyata.
- PHP/Laravel/Livewire/Tailwind mengikuti versi terinstal dan dukungan paket aktual.
- Jangan menaruh CV pribadi atau credentials di repo publik.
- PDF/PPTX adalah server-side generated exports dan harus diuji untuk bahasa, Unicode, layout, dan file corrupt.

## Asal proyek

Dibangun bertahap (M0–M7) dari starter pack spesifikasi di `docs/` dan `prompts/`, dengan aturan agent di `AGENTS.md` dan `CLAUDE.md`. Untuk menambah fitur, ikuti alur yang sama: audit → rencana singkat → perubahan kecil dengan test.
