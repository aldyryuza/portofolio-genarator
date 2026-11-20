# Database blueprint (logical; agent must implement migrations)

All tables: `id` bigint unsigned or Laravel defaults; timestamps; only use soft deletes where justified. FKs/indexes/unique constraints required.

| Table | Key columns |
|---|---|
| users | name, email unique, password, is_owner, email_verified_at |
| profiles | user_id unique, display_name, headline, summary, biography, location_public, public_email, avatar_media_id nullable, availability_status |
| social_links | profile_id, platform, label, url, sort_order, is_visible |
| experiences | company, title, employment_type, location, start_date, end_date nullable, is_current, description, achievements_json, sort_order, is_published |
| educations | institution, degree, field_of_study, start_date, end_date nullable, description, sort_order, is_published |
| certifications | name, issuer, issue_date, expiry_date nullable, credential_id nullable, credential_url nullable, file_media_id nullable, is_published |
| skill_groups | name, slug unique, sort_order |
| skills | skill_group_id, name, slug unique, icon nullable, proficiency nullable, sort_order, is_published |
| categories | name, slug unique, description, sort_order, is_active |
| tags | name, slug unique |
| projects | category_id nullable, title, slug unique, excerpt, summary, challenge, solution, outcome, role, start_date nullable, end_date nullable, client_label nullable, cover_media_id nullable, status draft/published, published_at nullable, is_featured, sort_order, meta_title, meta_description, deleted_at nullable |
| project_skill | project_id, skill_id, unique pair |
| project_tag | project_id, tag_id, unique pair |
| project_media | project_id, media_id, role, caption, sort_order |
| project_sections | project_id, title, body, section_type, sort_order |
| media_assets | disk, path, original_filename, mime_type, extension, bytes, width nullable, height nullable, alt_text, caption, visibility, uploaded_by, deleted_at nullable |
| resumes | title, language, version, file_media_id, is_public, is_default, published_at nullable |
| contact_messages | sender_name, sender_email, subject, body, status, read_at nullable, ip_hash nullable |
| export_presets | name, format, configuration_json, template_key, created_by |
| export_jobs | export_preset_id nullable, format, state, output_disk nullable, output_path nullable, failed_reason nullable, requested_by, completed_at nullable |
| site_settings | key unique, value_json |
| activity_logs | actor_id nullable, event, subject_type, subject_id, properties_json, created_at |

**Indexes:** projects(status,published_at), projects(category_id,status), projects(is_featured,sort_order), experiences(start_date), contact_messages(status,created_at), media_assets(visibility), tags/skills slug unique. `projects` should have slug history if URL redirects on rename are needed.

**Integrity:** do not delete physical media referenced by project/CV/certificate; use reference checks and eventual garbage collection. Published resumes require safe download gating. Use DB transaction for multi-table publish operations. Enforce owner bootstrap securely via seeder/command, never default known password.
