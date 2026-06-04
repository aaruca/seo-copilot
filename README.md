# SEO Copilot

![Version](https://img.shields.io/badge/version-1.1.1-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D7.4-8892BF)
![WordPress](https://img.shields.io/badge/wordpress-%3E%3D6.2-21759B)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green)

AI-powered SEO content for any WordPress post type. Per-field, per-template control. Fluent 2 UI.

---

## Features

- **Any post type** — pages, posts, WooCommerce products, custom post types.
- **Per-field control** — pick exactly which fields the AI is allowed to write per post type and per template. A run can never overwrite content you want to keep.
- **Smart Optimizer** — single-post wizard with Google SERP preview, character-budget counters, and side-by-side diff of current vs. proposed values.
- **Bulk Wizard** — paginated post picker with "Select all matching" for large catalogs, per-batch cost estimate, and background processing via WP-Cron. Supports the **OpenAI Batch API** for 50%-cheaper, no-rate-limit submissions on 500+ post jobs.
- **Templates** — reusable prompt blueprints with field-level toggles. Create, edit, delete, or restore defaults.
- **Pending Review** — review AI proposals before applying them.
- **Multi-keyword** — generates 3–5 focus keywords per field, split correctly for Rank Math / Yoast / SEOPress / AIOSEO.
- **Local SEO** — baked into every template by default with configurable city, region, and country.
- **Bricks Builder** — reads Bricks content into the prompt context when present.
- **Fluent 2 UI** — hand-authored design system. No npm, no React, no build pipeline. WCAG-AA contrast. Light mode by default, dark mode opt-in.
- **OpenAI** — gpt-4.1 / gpt-4o family via JSON mode.

## Integrations

| Plugin | Status |
|---|---|
| WooCommerce | ✅ Supported |
| Rank Math | ✅ Supported |
| Yoast SEO | ✅ Supported |
| SEOPress | ✅ Supported |
| AIOSEO | ✅ Supported |
| Bricks Builder | ✅ Supported |

## Requirements

- WordPress ≥ 6.2
- PHP ≥ 7.4
- OpenAI API key

## Installation

1. Download the latest release ZIP from the [Releases](https://github.com/aaruca/seo-copilot/releases) page.
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and activate.
4. Navigate to **SEO Copilot → Settings → Connection** and enter your OpenAI API key.

## Project Structure

```
seo-copilot/
├── seo-copilot.php          # Main plugin entry point
├── uninstall.php            # Clean removal of all plugin data
├── readme.txt               # WordPress.org-style readme
├── build-zip.ps1            # PowerShell script for release packaging
├── assets/
│   ├── css/                 # Fluent 2 design tokens, components, app layout
│   └── js/                  # Vanilla JS — app core, page controllers, libraries
├── src/                     # PHP source (PSR-4, SeoCopilot\ namespace)
│   ├── Admin/               # Menu, assets, metabox, admin pages
│   ├── Capabilities/        # WordPress capability management
│   ├── Database/            # Schema and migrations
│   ├── Fields/              # Field registry and SEO plugin providers
│   ├── PostTypes/           # Post type discovery and registry
│   ├── Providers/           # AI provider interface and OpenAI implementation
│   ├── Rest/                # REST API controllers
│   ├── Runs/                # Run execution, bulk runner, repositories
│   ├── Support/             # Logger, sanitizer, rate limiter, Bricks extractor
│   └── Templates/           # Template model, repository, default seeds
└── views/                   # PHP view templates
    ├── components/          # Reusable UI components
    ├── layout/              # Page shell and pivot (tabs)
    ├── metabox/             # Post editor metabox
    └── pages/               # Admin page views
```

## REST API

All endpoints are under the `seocp/v1` namespace and require `manage_options` capability + `X-WP-Nonce`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/post-types` | List enabled post types and their fields |
| `GET` | `/fields/{post_type}` | Get field configuration for a post type |
| `GET` | `/posts` | Paginated post search with filters |
| `GET` | `/templates` | List all templates |
| `POST` | `/templates` | Create a template |
| `PUT` | `/templates/{id}` | Update a template |
| `DELETE` | `/templates/{id}` | Delete a template |
| `POST` | `/optimize` | Run AI optimization on a single post |
| `POST` | `/preview` | Preview AI output without applying |
| `GET` | `/runs` | List optimization runs |
| `GET` | `/segments` | List segments for a run |

## Changelog

### v1.1.1
* Fixed: "Missing focus keyword" / "Missing meta description" presets returned the whole catalog on single-plugin sites (relation=OR across always-absent keys). Both presets now AND across only the **active** SEO plugin's keys.
* Fixed: OpenAI Batch mode failed most chunks on large catalogs because submissions weren't gated — every chunk fired at once, exceeding OpenAI's per-model enqueued-token limit. Submissions are now serialized; transient failures retry; OpenAI's error file is ingested on partial completions.
* Fixed: "Apply entire batch" in Pending Review now drains the entire batch in chunks instead of stopping at ~500 segments per click.

### v1.1.0
* **OpenAI Batch API dispatch** in the Bulk Wizard — 50% cheaper, no rate-limit ceiling, async (results return within 24h). Recommended for 500+ post jobs. Pick it in Step 4 → Processing mode.
* Raised the per-minute rate-limit cap from 600 to 10,000 for users on higher OpenAI tiers.

### v1.0.0
* Initial release.

## Author

**Ale Aruca**

---

*Licensed under GPL-2.0-or-later.*
