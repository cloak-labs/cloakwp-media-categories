# Media Categories

Core-like media categories for WordPress — PHP-configured, no settings UI, no admin notices, no premium upsells.

Organize attachments with a hierarchical **Media Categories** taxonomy (`category_media` by default). Configure everything in PHP; the only admin UI is the Media Library itself (filters, checklists, bulk assign, add-new from the attachment sidebar).

## Install paths

### 1. Composer (must-use plugin) — recommended

```bash
composer require cloakwp/media-categories
```

Package type is `wordpress-muplugin`. With [`composer/installers`](https://github.com/composer/installers) configured, that installs to:

```
wp-content/mu-plugins/media-categories/
```

(Your project may map that path differently — e.g. Bedrock uses `public/app/mu-plugins/`.)

**Important:** WordPress core only auto-loads PHP files directly in `mu-plugins/`. It does **not** load plugins nested in subdirectories like `mu-plugins/media-categories/media-categories.php`. You need an autoloader (or a tiny stub) for subdirectory must-use plugins.

**Recommended:** [Roots Bedrock Autoloader](https://github.com/roots/bedrock-autoloader) — it scans `mu-plugins/*/*.php` for plugin headers and includes them. Ships with [Bedrock](https://roots.io/bedrock/); usable in any WordPress project as `roots/bedrock-autoloader`. Once loaded, this package shows under **Plugins → Must-Use** (not the toggleable Plugins list).

**Without an autoloader**, add a one-line stub at the mu-plugins root:

```php
<?php
// wp-content/mu-plugins/media-categories-loader.php
require WPMU_PLUGIN_DIR . '/media-categories/media-categories.php';
```

Optional fluent config in your theme `functions.php` (runs before the deferred default boot):

```php
use CloakWP\MediaCategories\MediaCategories;

MediaCategories::make()
  ->manageRoles(['administrator', 'editor'])
  ->assignRoles(['administrator', 'editor', 'author'])
  ->register();
```

If you never call `register()`, the plugin bootstrap starts with defaults on `init` priority 1.

### 2. Traditional plugin install (download as a zip)

For sites that don’t use Composer — install it like any other WordPress plugin:

1. Open the [GitHub repository page](https://github.com/cloak-labs/cloakwp-media-categories).
2. Click the green **Code** button, then **Download ZIP**.
3. Unzip the file. You’ll get a folder named something like `cloakwp-media-categories-main`.
4. Rename that folder to `media-categories` (optional but keeps the Plugins list tidy).
5. Install it in either way:
   - **WordPress admin:** Plugins → Add New → Upload Plugin → choose the zip (re-zip the renamed folder if you renamed it) → Install Now → Activate, **or**
   - **Manually:** upload the `media-categories` folder into `wp-content/plugins/` on your server (via FTP/SFTP or your host’s file manager), then go to Plugins and click **Activate**.

Same defaults as the Composer path. Developers can still override config via fluent `register()` or the config filter (below). No mu-plugin autoloader needed.

## Fluent API

```php
MediaCategories::make()
  ->slug('category_media')              // default
  ->restBase('media-categories')
  ->labels('Media Category', 'Media Categories')
  ->hierarchical()                      // default true
  ->rewrite('media-category')           // or ->rewrite(false)
  ->showInRest()                        // default true
  ->manageRoles(['administrator'])      // create / edit / delete terms
  ->assignRoles(['administrator', 'editor', 'author']) // attach terms to media
  ->defaultTerm(null)                   // optional auto-assign slug on upload
  ->register();
```

### `manageRoles` vs `assignRoles`

| Method | Capability | Who can… |
|--------|------------|----------|
| `manageRoles` | `manage_media_categories` | Create, rename, parent, delete categories (incl. “Add New” in the sidebar) |
| `assignRoles` | `assign_media_categories` | Check categories on attachments, filter, bulk add/remove |

Assignment also requires `edit_post` on the attachment. Same split as WordPress `manage_categories` / `assign_categories`.

### Config filter

```php
add_filter('cloakwp/media-categories/config', function ($config) {
  return $config->withManageRoles(['administrator', 'editor']);
});
```

## Admin features

- List view: filter dropdown (incl. Uncategorized), taxonomy column, bulk “Edit media categories…”
- Grid / media modal: taxonomy filter (including ACF Image, Gallery, and File field pickers)
- Attachment sidebar: checklist + **Add New Media Category** (REST `POST /wp/v2/media-categories`)
- Grid **Bulk select**: Add to selected / Remove from selected via `POST /wp-json/media-categories/v1/bulk-assign`

## Data model

Uses core taxonomy tables only (`wp_terms`, `wp_term_taxonomy`, `wp_term_relationships`) via `wp_set_object_terms()`. No custom tables, no options UI that saves config to the database (configure via PHP).

Default taxonomy slug: `category_media`.

**Compatibility note:** If you currently use [WP Media Category Management](https://wordpress.org/plugins/wp-media-category-management/), deactivate that plugin and activate this one. Assignments live in core term tables:

- Same taxonomy slug (`category_media` by default on both) — terms and assignments stay as-is.
- MCM was set to a different taxonomy (`category`, a custom slug, etc.) — a one-time per-site import copies attachment assignments onto this plugin’s taxonomy (append-only; existing terms are not replaced).
- Term counts are recalculated with `_update_generic_term_count` so unattached media is included in library filter counts (WordPress’s default post-count callback skips those).

## Architecture (Core + Plugin)

```
src/Core/      # Config, taxonomy registration, caps, term/query helpers
src/Plugin/    # Admin UI, REST, asset enqueue (WordPress integration)
MediaCategories.php  # Fluent facade wiring Core + Plugin
```

## Development

```bash
composer install
composer test
```

## License

LGPL-3.0-only
