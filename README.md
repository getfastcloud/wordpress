<img src="img/icon.svg" alt="FastCloudWP" width="150" height="78" />

# FastCloudWP

> **Work in progress.** FastCloudWP is not yet publicly available, and this plugin is pending approval on the WordPress plugin directory. Stay tuned — it's coming.

FastCloudWP is a WordPress plugin that automatically offloads your media library to [FastCloud](https://fastcloudwp.com/) and serves every asset through a global CDN. Upload once, deliver everywhere — without changing how you work in WordPress.

## Why FastCloudWP?

WordPress sites accumulate media. That media lives on your server, slows down your stack, and costs you disk space. FastCloudWP moves it out of the way automatically and rewrites your frontend URLs so visitors get assets from the nearest CDN edge — faster, and with less load on your origin.

**No AWS. No IAM. No S3 buckets. No storage configuration.** Just create a FastCloudWP account, paste your sitekey into the plugin, and you're done. Everything else is handled for you — including a **5 GB free plan, forever**.

## Features

- **Automatic offloading** — New uploads are queued and sent to FastCloudWP immediately after processing.
- **CDN URL rewriting** — Frontend `src` and `srcset` attributes are rewritten on the fly to point to the CDN origin.
- **Batch offload** — Existing media can be offloaded in bulk from the admin dashboard.
- **Optional local deletion** — Free up disk space by removing local copies after offloading.
- **Offload status tracking** — Each attachment is tracked (`queued`, `pending`, `offloaded`) so you always know what's been moved.
- **WP-CLI support** — Offload media and free disk space from the command line, with progress bars.
- **Settings UI** — Clean Vue 3 admin interface to connect, configure, and monitor everything.

## Coming Soon

These features are on the roadmap and will ship after the initial release:

- **Image optimization** _(Pro)_ — Automatic WebP and AVIF conversion on upload. The single biggest PageSpeed Insights win for media-heavy sites.
- **WooCommerce support** — Full compatibility with product images and attachment URL rewriting for WooCommerce stores.
- **Delivery analytics** — Bandwidth served, cache hit rate, and top files. See exactly what your CDN is doing.
- **Agency plan** — Multi-site management, higher limits, and white-label options. [Join the waitlist](https://fastcloudwp.com/) to be notified first.

## WP-CLI

FastCloudWP includes two WP-CLI commands for managing your media from the command line.

**Offload all pending media:**

```bash
wp fastcloud offload
```

Queues all media files that have not yet been offloaded to FastCloudWP. Runs in batches and shows a progress bar. Stops automatically if your storage quota is exceeded.

**Delete local copies to free disk space:**

```bash
wp fastcloud free-space
```

Deletes the local files for all media that has already been offloaded. Requires the "Remove Local Copies After Offload" setting to be enabled. Runs in batches and shows a progress bar.

## Building from Source

The admin interface is built with [Vue 3](https://vuejs.org/), [TypeScript](https://www.typescriptlang.org/), and [Vite](https://vite.dev/). Compiled assets are committed to `assets/` so the plugin works without a build step — but if you want to modify the frontend or verify the output yourself, here's how.

**Prerequisites:** Node.js 24+ and npm.

```bash
# Install dependencies
npm install

# Production build — compiles src/ into assets/
npm run build

# Development server with hot module replacement (port 5175)
npm run dev

# TypeScript type-check only (no emit)
npx vue-tsc -b

# Lint
npm run lint
npm run lint:fix

# Regenerate POT translation file (requires DDEV)
npm run make-pot

# Compile .po files → per-locale JSON for the JS bundle
npm run make-json
```

### Translations

WordPress loads PHP translations from `.mo` files the usual way. The Vue/TypeScript frontend is a compiled JavaScript bundle, so its strings need a separate delivery path: at runtime the plugin injects per-locale `.json` files (Jed 1.x format) into `window.__fastcloudwpI18n` before the app boots.

Full workflow for a new locale (`fr_FR` as example):

1. **Regenerate the POT template** (requires [DDEV](https://ddev.com/)):
   ```bash
   npm run make-pot
   ```
   Extracts strings from PHP and Vue/TypeScript source files → `languages/fastcloud-offload-media.pot`.

2. **Create/update the PO file** — use the POT as source in Poedit or any `.po` editor, save as `languages/fastcloud-offload-media-fr_FR.po`.

3. **Compile to MO** for PHP translations (standard Poedit export or `msgfmt`).

4. **Generate the JSON file** for the JavaScript bundle:
   ```bash
   npm run make-json
   ```
   Reads every `fastcloud-offload-media-{locale}.po` in `languages/` and outputs a matching `fastcloud-offload-media-{locale}-js.json`. The plugin loads this file at runtime so the Vue interface is translated alongside PHP.

## Code Quality

This project follows the [WordPress JavaScript coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/) and the [WordPress PHP coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).

- **PHP** — validated with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) using the `WordPress` ruleset and [PHPCompatibility](https://github.com/PHPCompatibility/PHPCompatibility) for PHP 8.1+.
- **JavaScript / TypeScript** — linted with [ESLint](https://eslint.org/) using [`@wordpress/eslint-plugin`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-eslint-plugin/), which enforces WordPress i18n rules, valid `sprintf` usage, and correct text domain usage across all Vue and TypeScript source files.

Both checks run automatically on every push via GitHub Actions and must pass before any release is deployed.

## Requirements

- WordPress 6.1+
- PHP 8.1+
- A FastCloud account _(not yet publicly available)_

## Status

The plugin is functional and under active development. FastCloudWP itself is not open to the public yet. Once both are ready, installation will be as simple as searching "FastCloudWP" in the WordPress plugin directory.

If you're a developer and want to follow along or contribute, watch this repo.

<img src="img/preview.png" alt="FastCloudWP preview" width="800" />

---

License: [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html)
