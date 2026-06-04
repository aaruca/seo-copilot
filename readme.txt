=== SEO Copilot ===
Contributors: alearuca
Tags: seo, ai, openai, content, woocommerce, bricks
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later

AI-powered SEO content for any post type. Per-field, per-template control. Fluent 2 UI.

== Description ==

SEO Copilot generates SEO titles, meta descriptions, focus keywords, image alt text, and long-form copy for any registered public post type. The merchant picks exactly which fields the AI is allowed to write per post type and per template, so a run can never overwrite content the user wants to keep.

* Works with WooCommerce, Rank Math, Yoast, SEOPress, AIOSEO (all optional).
* Reads Bricks Builder content into the prompt context when present.
* Hand-authored Fluent 2 design system. No npm, no React, no build pipeline.
* OpenAI (gpt-4.1 / gpt-4o family) via JSON mode.

== Changelog ==

= 1.1.3 =
* Fixed: bulk writes were silently dropped under WP-Cron because there was no logged-in user, so Rank Math / Yoast's postmeta auth_callback rejected the write. Bulk runner now restores the originating user before each apply.

= 1.1.2 =
* Fixed: bulk apply now verifies each write actually persisted to the database instead of trusting update_post_meta()'s return value. On sites with a persistent object cache, a DB read-replica, or a postmeta-filtering plugin, writes could report success while the product stayed empty. Apply now flushes the post cache, re-reads each field, counts only verified writes, and logs any silent failures.

= 1.1.1 =
* Fixed: "Missing focus keyword" / "Missing meta description" presets returned the entire catalog on single-plugin sites — they now filter against only the active SEO plugin's keys (AND across them, with empty checks). Re-runs no longer burn tokens on already-optimized products.
* Fixed: OpenAI Batch mode failed most chunks on large catalogs because every chunk was submitted at once and exceeded the per-model enqueued-token limit. Submissions are now serialized behind a concurrency gate, with automatic retry of transient batch failures.
* Fixed: "Apply entire batch" in Pending Review now drains the whole batch instead of stopping at ~500 segments per click. Shows live progress.
* Fixed: Partially-completed batches now ingest OpenAI's error file and resolve missing items so progress can reach 100%.

= 1.1.0 =
* Added OpenAI Batch API dispatch in the Bulk Wizard — 50% cheaper, no rate-limit ceiling, async results within 24h. Recommended for 500+ post jobs.
* Raised the per-minute rate-limit cap from 600 to 10,000 for users on higher OpenAI tiers.

= 1.0.0 =
* Initial release.
