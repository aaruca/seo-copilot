# Changelog

All notable changes to **SEO Copilot** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] — 2026-05-11

### Added
- **Smart Optimizer** — single-post wizard with Google SERP preview, character-budget counters, and side-by-side diff of current vs. proposed values.
- **Bulk Wizard** — paginated post picker with "Select all matching" for large catalogs, per-batch cost estimate, and WP-Cron background processing.
- **Templates** — reusable prompt blueprints with per-field, per-post-type toggles. Create, edit, delete, and restore defaults.
- **Pending Review** — review and apply AI proposals before they touch your content.
- **Multi-keyword** — generates 3–5 focus keywords per field, split into Rank Math / Yoast / SEOPress / AIOSEO formats.
- **Local SEO** — baked into every default template with configurable city, region, and country.
- **Bricks Builder** — reads Bricks content into the prompt context when present.
- **Fluent 2 UI** — hand-authored design system with WCAG-AA contrast. Light mode by default, dark mode opt-in.
- **OpenAI integration** — gpt-4.1 / gpt-4o family via JSON mode.
- **Field providers** — Core, WooCommerce, Rank Math, Yoast, SEOPress, AIOSEO.
- **REST API** — full `seocp/v1` namespace for all operations.
- **Logging and diagnostics** — run history, per-segment audit trail, WP-Cron diagnostics.

[1.0.0]: https://github.com/aaruca/seo-copilot/releases/tag/v1.0.0
