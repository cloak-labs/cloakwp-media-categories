# Media Categories

Core-like media categories for WordPress — PHP-configured, no settings UI, no admin notices, no premium upsells.

Organize attachments with a hierarchical **Media Categories** taxonomy (`category_media` by default). Configure everything in PHP; the only admin UI is the Media Library itself (filters, checklists, bulk assign, add-new from the attachment sidebar).

## Install paths

### 1. Composer (Bedrock / CloakWP) — recommended

```bash
composer require cloakwp/media-categories
```

Package type is `wordpress-muplugin`. Composer Installers places it at:

```
wp-content/mu-plugins/media-categories/   # or public/app/mu-plugins/ on Bedrock
```

**Bedrock Autoloader** loads subdirectory must-use plugins automatically. The package appears under **Plugins → Must-Use**, not the regular (toggleable) Plugins list.

Optional fluent config in your theme `functions.php` (runs before the deferred default boot):

```php
use CloakWP\MediaCategories\MediaCategories;

MediaCategories::make()
  ->manageRoles(['administrator', 'editor'])
  ->assignRoles(['administrator', 'editor', 'author'])
  ->register();
```

If you never call `register()`, the plugin bootstrap starts with defaults on `init` priority 1.

### 2. Traditional plugin zip

Drop the package folder into `wp-content/plugins/media-categories/` and activate it like any plugin. Same defaults; override via fluent `register()` or the config filter (below).

### 3. Composer without Bedrock Autoloader

Installer still puts files in `mu-plugins/media-categories/`, but WordPress core does not load subdirectory mu-plugins. Add a one-line loader:

```php
<?php
// wp-content/mu-plugins/media-categories-loader.php
require WPMU_PLUGIN_DIR . '/media-categories/media-categories.php';
```

## Fluent API

```php
MediaCategories::make()
  ->slug('category_media')              // keep default to preserve existing MCM data
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
- Grid / media modal: taxonomy filter
- Attachment sidebar: checklist + **Add New Media Category** (REST `POST /wp/v2/media-categories`)
- Grid **Bulk select**: Add to selected / Remove from selected via `POST /wp-json/media-categories/v1/bulk-assign`

## Data model

Uses core taxonomy tables only (`wp_terms`, `wp_term_taxonomy`, `wp_term_relationships`) via `wp_set_object_terms()`. No custom tables, no options UI, no Freemius.

Default slug `category_media` matches the old **WP Media Category Management** plugin so existing terms keep working.

## Migrating from WP Media Category Management

1. `composer remove wpackagist-plugin/wp-media-category-management`
2. `composer require cloakwp/media-categories`
3. Keep slug `category_media` (the default)
4. Delete leftover MCM options if you want (`wp-media-category-management-options`, Freemius entries) — optional

## Architecture (Core + Plugin)

```
src/Core/      # Config, taxonomy registration, caps, term/query helpers (extractable library)
src/Plugin/    # Admin UI, REST, asset enqueue (WordPress integration)
MediaCategories.php  # Fluent facade wiring Core + Plugin
```

Asset-serving CloakWP packages should use `"type": "wordpress-muplugin"` so `plugin_dir_url()` works. Pure PHP libraries can stay `"type": "library"` in `vendor/`.

## Development

```bash
composer install
composer test
```

## License

LGPL-3.0-only
