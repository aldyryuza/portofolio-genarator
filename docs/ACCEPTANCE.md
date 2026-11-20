# Acceptance criteria

- [ ] Anonymous user cannot access `/admin` or unpublished projects through any URL/API/export.
- [ ] Public registration disabled; owner login/logout/password reset work safely.
- [ ] Owner can create/edit/preview/publish/unpublish/delete projects, assign taxonomy, reorder and mark featured.
- [ ] Slugs unique, invalid category/slug returns appropriate 404, draft preview requires auth or scoped signed URL.
- [ ] Image upload checks content MIME, extension, bytes, dimensions; scripts/SVG blocked by default; nonpublic uploads inaccessible directly.
- [ ] Videos use allowlisted providers or carefully limited uploads; no raw iframe HTML injection.
- [ ] Public pages mobile responsive, keyboard accessible, alt labels, canonical/meta, sitemap only published.
- [ ] Experience, education and certifications sort correctly and handle ongoing dates.
- [ ] CV can be replaced/versioned; download only public CV or authorized owner.
- [ ] PDF opens and contains selected public/owner-authorized projects with correct page breaks and Unicode.
- [ ] PPTX opens in PowerPoint/LibreOffice and contains selected projects, images and readable layouts.
- [ ] Exports handle zero/one/many projects, large images, missing cover, unauthorized access.
- [ ] Contact form throttled/honeypot, safely validated, sends/stores without exposing email credentials.
- [ ] Automated tests pass; formatter/linter run; `.env`, upload originals, tokens absent from git.
