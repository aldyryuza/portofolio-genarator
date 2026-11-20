# Architecture and conventions

Single Laravel monolith: `routes/web.php` public + authenticated owner route group; Livewire 4 views/components; Tailwind/Vite. Follow actual starter kit layout instead of forcing directories.

- Domain: Profile, Experience, Education, Certification, Skill, Project, Taxonomy, Media, Resume, Contact, Export, Settings.
- Eloquent models and relationships. FormRequests/Livewire validation for user input. Policies/authorization on every mutation and unpublished access.
- Business logic such as exports and media processing in discrete actions/services, only where complexity warrants.
- File storage: `public` disk for explicitly public sanitized derivative images; private/local or S3 private for private CV/certificates and source media. Never expose arbitrary path download.
- Export: `PortfolioExportService` composes a DTO/view model; PDF renderer via vetted HTML→PDF tool compatible with runtime; PPTX via `phpoffice/phppresentation` **only after checking current maintenance and compatibility**. Optionally use separate renderer service if packages conflict. Feature tests should inspect output signatures and contents.
- Administration should not accidentally leak unpublished project data in search, sitemap, feeds, exports, or OG cards.
- Integrations: only external links initially; no third-party API keys required.
- Avoid storing raw HTML from admin. Render Markdown via safe sanitizer if rich text required.
- Design: premium, restrained, typographic, no random gradients, responsive mobile-first. Keep design tokens centrally; public visual style may differ from admin but remain coherent. Use actual portfolio assets once supplied.
