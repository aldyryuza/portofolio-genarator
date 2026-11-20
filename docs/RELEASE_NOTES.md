# Release notes — v1.0.0 (2026-11-20)

First complete release of the Personal Portfolio CMS: a single-owner admin, a public portfolio site, and PDF/PPTX exports.

**Stack:** Laravel 13.43 · PHP 8.4 · Livewire 4.4 · Tailwind CSS 4.3 · MySQL 8.0 · Pest 5.3.
**Added packages:** `barryvdh/laravel-dompdf` 3.1 (PDF), `phpoffice/phppresentation` 1.2 (PPTX). Everything else is the Laravel Livewire starter kit.

## What's included

| Milestone | Highlights |
|---|---|
| M0 | Audit, working frontend build, seeder without a known default password |
| M1 | Owner-only login, logout and password reset; no registration; `portfolio:create-owner`; dashboard; profile and account settings; audit log |
| M2 | Projects (draft/published, featured, ordering, soft delete/restore, unique slugs), categories, tags, skill groups, skills |
| M3 | Media library with re-encoded WebP derivatives, private storage served through an authorizing controller, project cover/gallery, YouTube/Vimeo embeds |
| M4 | Public site (home, about, work, project case studies, experience, education & certifications), signed draft previews, 301 slug redirects, SEO metadata, sitemap, robots, custom error pages; admin for experience, education, certifications and social links |
| M5 | Versioned CVs with gated downloads; contact form with honeypot, time-trap and throttling; admin inbox with mail notification |
| M6 | PDF and PPTX portfolio exports with presets, themes, English/Indonesian headings, owner-only downloads |
| M7 | Nonce-based CSP and HSTS, accessibility fixes, scheduled storage pruning, MySQL CI job, deployment and backup docs |
| M7.1 | Light/dark theme (follows the OS, toggle remembered per browser) on the public site, admin and login; self-hosted WOFF2 fonts; stateless `robots.txt`/`sitemap.xml`; Docker images and Compose stack (`docs/DOCKER.md`), `TRUSTED_PROXIES` support |

## Acceptance criteria (`docs/ACCEPTANCE.md`)

| Criterion | Status | Evidence |
|---|---|---|
| Anonymous users cannot reach `/admin` or unpublished projects via any URL, sitemap or export | ✅ | Access, project page, sitemap and export tests |
| No public registration; owner login, logout and reset work safely | ✅ | Auth tests (rate limiting, no account enumeration) |
| Owner CRUD, preview, publish/unpublish, delete, taxonomy, reorder, featured | ✅ | Project, taxonomy and project media tests |
| Unique slugs, 404 for unknown/invalid category or slug, draft preview needs auth or a signed URL | ✅ | Project page tests |
| Uploads check content type, extension, bytes and dimensions; SVG/scripts blocked; private files not directly reachable | ✅ | Uploader, media library and serving tests |
| Videos from allowlisted providers only; no raw iframe HTML | ✅ | Video URL unit tests (local video upload is intentionally not supported) |
| Mobile responsive, keyboard accessible, alt text, canonical/meta, sitemap only published | ✅ / ⚠️ | Lighthouse (Chrome 412 px mobile emulation and desktop) plus screenshots at 768 and 1440 px in both themes; automated structure/contrast checks. **Admin screens and keyboard-only navigation were not checked by hand** |
| Experience, education and certifications sort correctly and handle ongoing dates | ✅ | Career tests |
| CV can be replaced/versioned; only public CVs or the owner can download | ✅ | CV tests |
| PDF opens with selected projects, page breaks and Unicode | ✅ / ⚠️ | Structure and page counts tested; text incl. Latin-extended and Cyrillic verified with `pdftotext`; **visual layout not reviewed** |
| PPTX opens in PowerPoint/LibreOffice with projects, images and readable layout | ✅ | Structure, well-formed XML and read-back tested; opened successfully by the owner |
| Exports handle zero/one/many projects, large images, missing cover, unauthorized access | ✅ | Export tests |
| Contact form throttled, honeypot, validated; no credentials exposed | ✅ | Contact tests; CSRF confirmed on a live server (419 without token) |
| Tests pass; formatter/linter run; `.env`, uploads and tokens absent from git | ✅ / ⚠️ | 418 tests on SQLite and MySQL, Pint, PHPStan level 7; ignore rules in place, but **the project is not a git repository yet** |

## Lighthouse 12.8 (local, demo content, 2026-11-20)

| Page | Mobile perf | Desktop perf | Accessibility | Best practices | SEO |
|---|---|---|---|---|---|
| Home | 98 | 93–100 | 100 | 100 | 66* |
| Projects | 99 | 100 | 100 | 100 | 66* |
| Project detail | 97 | 100 | 100 | 100 | 66* |
| Experience | 97 | 100 | 100 | 100 | 66* |
| Contact | 99 | 100 | 100 | 100 | 66* |
| Login | 99 | 100 | 100 | 100 | 54* |

\* Only `is-crawlable` fails, because non-production `robots.txt` deliberately disallows everything (login is also `noindex`). In production `robots.txt` allows crawling. Measured on PHP's single-threaded dev server without images; re-run against the production host after deploy.

## Known limitations

- Admin UI text is English; only export headings have Indonesian translations (`lang/id.json`).
- CJK scripts are not covered by the bundled PDF font (DejaVu).
- Exports and mail run synchronously; very large exports may need a queue later.
- No full-page caching; pages are light and N+1 queries are blocked in development by `preventLazyLoading`.
- Certificate PDFs are not downloadable by visitors (only the verification link and public images are shown).
- Contact messages are kept until deleted; there is no automatic retention policy.
- The CI workflow has not run yet because the repository has not been pushed.

## Upgrade / first install

See `docs/DEPLOYMENT.md`. Back up first (`docs/BACKUP.md`).
