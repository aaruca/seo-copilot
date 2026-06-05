# Changelog

All notable changes to **SEO Copilot** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.4] — 2026-06-04

### Fixed
- **Applied changes now flush the SEO plugin's own cache so they show up immediately.** After writing, `apply()` calls `rank_math_clear_cache()` (and fires a new `seocp_after_apply` action). On object-cache hosts like Kinsta (Redis), Rank Math serves derived/rendered meta from cache, so a raw postmeta write could stay hidden in the editor and front-end until the cache expired — the exact "the data is generated but the product shows nothing" symptom. This matches the cache-clear step a known-working reference plugin performs.

### Added
- `seocp_after_apply` action (`$post_id`, `$written_field_ids`) for flushing page caches / CDNs after a write.

## [1.1.3] — 2026-06-04

### Fixed
- **Bulk writes silently dropped under cron because there was no current user.** Individual writes from Smart Optimizer / Pending Review worked because they run inside an authenticated REST request, but bulk processing runs from WP-Cron with no logged-in user — so the `auth_callback` registered by Rank Math / Yoast on their meta keys rejected the write. `update_post_meta()` returned truthy, the Logs read `applied`, and the product stayed empty. The Bulk Runner and OpenAI Batch dispatcher now restore the originating user before each `apply()` call (with a safe fallback to any admin who can edit the post if the originating user was deleted).
- Schema v1.4.0 adds `queue.created_by` (auto-upgraded on plugin load) so cron knows which user enqueued the batch.

## [1.1.2] — 2026-06-04

### Fixed
- **Bulk apply now verifies each write actually persisted instead of trusting `update_post_meta()`'s return value.** On large catalogs with a persistent object cache (Redis/Memcached), a DB read-replica, or a plugin that filters postmeta, `update_post_meta()` can return success while the value never becomes visible — so the Logs showed `applied` but the product stayed empty. `apply()` now flushes the post cache, re-reads each field from the database, counts only verified writes (so a silent failure shows as `noop`, not a false `applied`), and logs the exact field/post that failed with a diagnostic hint. The cache flush also fixes the case where the write *did* persist but a stale cache was hiding it from the editor/front-end.
- New filter `seocp_verify_writes` (default `true`) to disable the read-back if needed.

## [1.1.1] — 2026-06-04

### Fixed
- **"Apply entire batch" in Pending Review now applies the whole batch.** It was capped at ~500 segments per click (`list_pending` limit), so large review batches needed hundreds of clicks. The button now drains the batch in chunks until nothing is pending, with live progress, and auto-resolves segments that can't be written so the loop always terminates.
- **"Missing focus keyword" preset now actually filters.** It used `relation => OR` across the Rank Math / Yoast / AIOSEO keys, so on a single-plugin site the two inactive plugins' keys (always absent) matched every post — the filter returned the whole catalog, including products that already had a focus keyword. Both "missing" presets now use AND across only the **active** SEO plugins' keys (with empty-string checks), so they return just the posts genuinely missing the field. This is what was causing bulk runs to spend tokens on already-optimized products.
- **OpenAI Batch mode no longer fails most chunks on large catalogs.** Previously every chunk was submitted near-simultaneously (cron + browser poll), blowing past OpenAI's per-model *enqueued-token* limit so all but the first chunk failed `0/N`. Submissions are now serialized behind a concurrency gate (`seocp_openai_batch_concurrency`, default 1): a new chunk is only submitted once earlier chunks free up batch-queue capacity.
- **Transient batch failures are retried instead of permanently killing a whole chunk.** Token/rate-limit failures and 24h expiries reset the chunk to `draft` and re-enqueue its rows (up to `seocp_openai_batch_max_retries`, default 5). Validation errors in our own request file are still failed fast.
- Partially-completed batches now also ingest OpenAI's **error file** and resolve any items missing from both files, so a batch can't hang below 100% forever.
- Bulk Wizard chunk pills now show a `retry N` state and surface the OpenAI failure reason on hover.

### Changed
- `seocp_openai_batches` gained an `attempts` column (Schema v1.3.0, auto-upgraded on plugin load).

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

[1.1.1]: https://github.com/aaruca/seo-copilot/releases/tag/v1.1.1
[1.1.0]: https://github.com/aaruca/seo-copilot/releases/tag/v1.1.0
[1.0.0]: https://github.com/aaruca/seo-copilot/releases/tag/v1.0.0
