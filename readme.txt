=== SEO Copilot ===
Contributors: alearuca
Tags: seo, ai, openai, content, woocommerce, bricks
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

AI-powered SEO content for any post type. Per-field, per-template control. Fluent 2 UI.

== Description ==

SEO Copilot generates SEO titles, meta descriptions, focus keywords, image alt text, and long-form copy for any registered public post type. The merchant picks exactly which fields the AI is allowed to write per post type and per template, so a run can never overwrite content the user wants to keep.

* Works with WooCommerce, Rank Math, Yoast, SEOPress, AIOSEO (all optional).
* Reads Bricks Builder content into the prompt context when present.
* Hand-authored Fluent 2 design system. No npm, no React, no build pipeline.
* OpenAI (gpt-4.1 / gpt-4o family) via JSON mode.

== Changelog ==

= 1.1.0 =
* Added OpenAI Batch API dispatch in the Bulk Wizard — 50% cheaper, no rate-limit ceiling, async results within 24h. Recommended for 500+ post jobs.
* Raised the per-minute rate-limit cap from 600 to 10,000 for users on higher OpenAI tiers.

= 1.0.0 =
* Initial release.
