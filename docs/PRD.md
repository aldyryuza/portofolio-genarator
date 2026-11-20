# PRD — Personal Portfolio CMS

## Goal
Create a premium Indonesian/English-capable (optional future locale) personal portfolio website with a private CMS, project showcase, resume, and exportable PDF/PPTX presentation. Primary users: site owner and public visitors/recruiters.

## Roles
- Visitor: view published content, download explicitly published CV, send contact message.
- Owner (admin): login, CRUD, sort, publish/unpublish, preview, manage private assets, generate exports.
- No public registration, no multi-user RBAC in MVP.

## Public pages
Home (hero, featured, about, expertise, CTA), About, Projects index (filter category, skills, search), Project detail (case study, media/gallery), Experience, Education & Certifications, CV, Contact, SEO-friendly error pages. Responsive/mobile-first, accessible, SEO metadata and OpenGraph.

## Admin features
Dashboard stats; profile & social links; work experience; education; certifications; skills; categories & tags; projects (draft/published, featured, order, dates, technologies, responsibilities, impact); nested project sections; media library (image/video/document); CV versions (private/public); contact inbox; export presets; site settings. Soft-delete projects and media where appropriate. Unique slugs and previews must not leak drafts.

## Media
Images: MIME allowlist JPG, PNG, WebP, max configurable, dimensions and safe filename, generate thumbnails. Videos: preferably remote YouTube/Vimeo embed with allowlisted URL, or optional local video upload with size/encoding policy and async processing; never accept arbitrary embeds/scripts. PDFs: certificates and CV only validated MIME. Use private storage for originals requiring access controls; public assets through explicit publishing. Set alt/caption/credit.

## Exports
Owner chooses full/selected projects, categories, locale, template, number of slides, whether to include personal profile/education/experience. PDF uses server-rendered template and tested page breaks; PPTX uses a separate slide-builder template. Stream/download only after authorization. Do not export unapproved private documents in visitor mode.

## Nonfunctional
Sitemap, robots, canonical, lazy image loading, cache public pages, activity logs, request throttling, security headers, backup/restore procedure, queue optional for large exports/media, test coverage on authorization/upload/publishing/export.

## Not MVP
AI-generated fictional content, realtime editing collaboration, multi-tenant subscriptions, payment, public accounts, automated social posts.
