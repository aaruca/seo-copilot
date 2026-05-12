=== SEO Copilot ===
Contributors: alearuca
Tags: seo, ai, openai, content, woocommerce, bricks
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

AI-powered SEO content for any post type. Per-field, per-template control. Fluent 2 UI.

== Description ==

SEO Copilot generates SEO titles, meta descriptions, focus keywords, image alt text, and long-form copy for any registered public post type. The merchant picks exactly which fields the AI is allowed to write per post type and per template, so a run can never overwrite content the user wants to keep.

* Works with WooCommerce, Rank Math, Yoast, AIOSEO (all optional).
* Reads Bricks Builder content into the prompt context when present.
* Hand-authored Fluent 2 design system. No npm, no React, no build pipeline.
* OpenAI (gpt-4.1 / gpt-4o family) via JSON mode.

== Changelog ==

= 1.0.6 =
* Bulk Wizard: "Recent batches" panel shows past runs with completed/failed/pending counts, so you can revisit a batch after navigating away.
* Bulk Wizard: explicit messaging that bulk runs auto-apply changes — no separate review/apply step. Smart Optimizer remains the review-and-apply flow.
* Bulk progress card: clear completion summary with link to Logs.

= 1.0.5 =
* Templates: every template can now be deleted (including default ones). Deleted defaults stay deleted across upgrades — they won't auto-resurrect.
* Templates list: inline Delete buttons and a "Restore defaults" toolbar action.

= 1.0.4 =
* Bug fix: bulk batches stuck at 0 because the WP-Cron schedule was registered after wp_schedule_event ran. Filter is now registered first.
* In-page worker: progress poller drains the queue synchronously via /bulk/tick so batches advance even when WP-Cron is disabled or the site has no traffic.
* Diagnostics: surfaces DISABLE_WP_CRON and the next scheduled cron tick on the Logs page.

= 1.0.3 =
* Bulk Wizard: paginated post picker (wp-list-table style) with per-page control, page navigation and total count.
* Bulk Wizard: "Select all NN,NNN matching" option enqueues from a filter spec — works on 60,000-product catalogs without shipping IDs over the wire.
* Server-side bulk enqueue uses chunked SQL inserts (500 rows / batch) to keep memory and request time reasonable.

= 1.0.2 =
* Multi-keyword: every focus-keyword field now produces 3–5 keywords; writers split correctly into Rank Math / Yoast / AIOSEO formats.
* Local SEO baked into every template by default; new Connection settings for default geo (city, region, country).
* Senior-strategist system prompts: search-intent classification, SERP features, E-E-A-T signals, explicit Rank Math score targets.
* Smart Optimizer: clear post-apply success state, per-field "Applied" badges, current values refresh inline, fields lock.
* Buttons: white text on primary/danger forced inside the plugin shell.

= 1.0.1 =
* Products: SEO-meta only — never writes product titles or descriptions.
* Light mode UI by default with WCAG-AA contrast; dark theme opt-in.
* Smart Optimizer and Bulk Wizard rebuilt as step-by-step wizards with Google SERP preview, character-budget counters, and per-batch cost estimate.
* Default templates rewritten with comprehensive SEO playbook; refresh automatically on upgrade.

= 1.0.0 =
* Initial release.
