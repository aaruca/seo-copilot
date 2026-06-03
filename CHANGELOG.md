# Changelog

All notable changes to **SEO Copilot** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-06-03

### Added
- **OpenAI Batch API dispatch** in the Bulk Wizard — submit large jobs (e.g. 50k+ products) to OpenAI's Batch API for **50% lower token cost** and no per-minute rate-limit ceiling. Results return within 24h and apply automatically as they download. Pick "OpenAI Batch API" in Step 4 → Processing mode.
- New `seocp_openai_batches` table tracking per-chunk lifecycle (build → submit → poll → apply).
- `BatchDispatcher` service orchestrates the OpenAI Files + Batches APIs end-to-end.
- Browser-driven `/bulk/tick` now also nudges the batch dispatcher so progress advances without waiting for WP-Cron.
- Filters: `seocp_openai_batch_chunk_size` (default 5000), `seocp_batch_apply_size` (default 500).

### Changed
- Settings → Connection: raised the **Requests per minute** cap from 600 to 10,000 so users on higher OpenAI tiers can configure realistic limits. Sub-text now points to Batch mode for 500+ post runs.
- Queue table gained `dispatch`, `openai_custom_id`, and `payload_response` columns (Schema v1.2.0, auto-upgraded on plugin load).
- Bulk Wizard's "Recent batches" panel surfaces a `batch` badge for Batch-API runs.

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

[1.1.0]: https://github.com/aaruca/seo-copilot/releases/tag/v1.1.0
[1.0.0]: https://github.com/aaruca/seo-copilot/releases/tag/v1.0.0
