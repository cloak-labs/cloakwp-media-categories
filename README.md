# Media Taxonomies

Core-like media taxonomies for WordPress — PHP-configured, no settings UI, no admin notices, no premium upsells.

Organize attachments with one or more hierarchical taxonomies. The default is a single **Media Categories** taxonomy (`category_media`). Configure everything in PHP; the only admin UI is the Media Library itself (filters, checklists, bulk assign, add-new from the attachment sidebar).

Public library / frontend filter ids are the **taxonomy slugs**. There is no `media_category` alias.

## Install paths

### 1. Composer (must-use plugin) — recommended

```bash
composer require cloakwp/media-taxonomies
```

This package `replace`s `cloakwp/media-categories`, so existing sites can switch the require line without a dual-install.

Package type is `wordpress-muplugin`. With [`composer/installers`](https://github.com/composer/installers) configured, that installs to:

```
wp-content/mu-plugins/media-taxonomies/
```

(Your project may map that path differently — e.g. Bedrock uses `public/app/mu-plugins/`.)

**Important:** WordPress core only auto-loads PHP files directly in `mu-plugins/`. It does **not** load plugins nested in subdirectories like `mu-plugins/media-taxonomies/media-taxonomies.php`. You need an autoloader (or a tiny stub) for subdirectory must-use plugins.

**Recommended:** [Roots Bedrock Autoloader](https://github.com/roots/bedrock-autoloader) — it scans `mu-plugins/*/*.php` for plugin headers and includes them. Ships with [Bedrock](https://roots.io/bedrock/); usable in any WordPress project as `roots/bedrock-autoloader`. Once loaded, this package shows under **Plugins → Must-Use** (not the toggleable Plugins list).

**Without an autoloader**, add a one-line stub at the mu-plugins root:

```php
<?php
// wp-content/mu-plugins/media-taxonomies-loader.php
require WPMU_PLUGIN_DIR . '/media-taxonomies/media-taxonomies.php';
```

Optional fluent config in your theme `functions.php` (runs before the deferred default boot):

```php
use CloakWP\MediaTaxonomies\MediaTaxonomies;
use CloakWP\MediaTaxonomies\MediaTaxonomy;

MediaTaxonomies::make()
  ->manageRoles(['administrator'])
  ->assignRoles(['administrator', 'editor', 'author'])
  ->taxonomies([
    MediaTaxonomy::make('landscape_type')
      ->labels('Landscape Type', 'Landscape Types')
      ->hierarchical(),
    MediaTaxonomy::make('outdoor_living_type')
      ->labels('Outdoor Living Type', 'Outdoor Living Types'),
    MediaTaxonomy::make('client_type')
      ->labels('Client Type', 'Client Types')
      ->hierarchical(false),
    MediaTaxonomy::make('photo_type')
      ->labels('Photo Type', 'Photo Types')
      ->hierarchical(),
  ])
  ->register();
```

If you never call `register()`, the plugin bootstrap starts with one `category_media` taxonomy on `init` priority 1.

### 2. Traditional plugin install (download as a zip)

For sites that don’t use Composer — install it like any other WordPress plugin:

1. Open the [GitHub repository page](https://github.com/cloak-labs/cloakwp-media-taxonomies).
2. Click the green **Code** button, then **Download ZIP**.
3. Unzip the file. You’ll get a folder named something like `cloakwp-media-taxonomies-main`.
4. Rename that folder to `media-taxonomies` (optional but keeps the Plugins list tidy).
5. Install it in either way:
   - **WordPress admin:** Plugins → Add New → Upload Plugin → choose the zip (re-zip the renamed folder if you renamed it) → Install Now → Activate, **or**
   - **Manually:** upload the `media-taxonomies` folder into `wp-content/plugins/` on your server (via FTP/SFTP or your host’s file manager), then go to Plugins and click **Activate**.

Same defaults as the Composer path. Developers can still override config via fluent `register()` or the config filter (below). No mu-plugin autoloader needed.

## Fluent API

Default boot (no theme code) stays one taxonomy: slug **and** public filter id `category_media`, labels “Media Category/Categories”, `rest_base` matching the slug.

```php
use CloakWP\MediaTaxonomies\MediaTaxonomies;
use CloakWP\MediaTaxonomies\MediaTaxonomy;

MediaTaxonomies::make()
  ->manageRoles(['administrator'])
  ->assignRoles(['administrator', 'editor', 'author'])
  ->taxonomies([
    MediaTaxonomy::make('landscape_type')
      ->labels('Landscape Type', 'Landscape Types')
      ->hierarchical(),
  ])
  ->register();
```

`MediaTaxonomy` owns slug, labels, restBase (default = slug), rewrite, hierarchical, and defaultTerm. The public `LibraryFilter` id / query var **is the slug**. List-view GET uses a prefixed internal arg (`filter_{slug}`) so WordPress core does not treat encoded values like `not:12` as a native taxonomy query.

### `manageRoles` vs `assignRoles`

| Method | Capability | Who can… |
|--------|------------|----------|
| `manageRoles` | `manage_media_categories` | Create, rename, parent, delete terms (incl. “Add New” in the sidebar) |
| `assignRoles` | `assign_media_categories` | Check terms on attachments, filter, bulk add/remove |

Assignment also requires `edit_post` on the attachment. Same split as WordPress `manage_categories` / `assign_categories`. Capability strings are unchanged from the previous Media Categories package so existing roles keep working.

### Config filter

```php
add_filter('cloakwp/media-taxonomies/config', function ($config) {
  return $config->withManageRoles(['administrator', 'editor']);
});
```

`MediaTaxonomies::all()` returns the registered `TaxonomyConfig` list (or the default Media Categories taxonomy before boot).

## Admin features

- List view: one hierarchical filter dropdown per taxonomy (incl. Uncategorized), optional **Not in**, taxonomy columns, bulk “Edit media taxonomies…”
- Grid / media modal: one hierarchical multi-select filter per taxonomy with **In** / **Not in** (including ACF Image, Gallery, and File field pickers)
- Attachment sidebar: one checklist + **Add New** form per taxonomy (`POST /wp/v2/{rest_base}`)
- Grid **Bulk select**: stacked checklists; add/remove via `POST /wp-json/media-taxonomies/v1/bulk-assign` with a `taxonomy` argument
- Term trees: `GET /wp-json/media-taxonomies/v1/terms`

## Data model

Uses core taxonomy tables only (`wp_terms`, `wp_term_taxonomy`, `wp_term_relationships`) via `wp_set_object_terms()`. No custom tables, no options UI that saves config to the database (configure via PHP).

Default taxonomy slug: `category_media`. Do not rename that slug on existing sites — term rows already use it.

**Compatibility note:** If you currently use [WP Media Category Management](https://wordpress.org/plugins/wp-media-category-management/), deactivate that plugin and activate this one. Assignments live in core term tables:

- Same taxonomy slug (`category_media` by default on both) — terms and assignments stay as-is.
- MCM was set to a different taxonomy (`category`, a custom slug, etc.) — a one-time per-site import copies attachment assignments onto this plugin’s default taxonomy (append-only; existing terms are not replaced).
- Term counts are recalculated with `_update_generic_term_count` so unattached media is included in library filter counts (WordPress’s default post-count callback skips those).

## Architecture (Core + Plugin)

```
src/Core/          # Config, taxonomy registration, caps, term/query helpers
src/Plugin/        # Admin UI, REST, CLI, asset enqueue (WordPress integration)
MediaTaxonomies.php
MediaTaxonomy.php
```

Media Library list/grid/modal filtering uses `CloakWP\Core\Media\LibraryFilter` (shared with Media Orientation). This package supplies the custom hierarchical UI and taxonomy query; the primitive owns `restrict_manage_posts`, `pre_get_posts`, `ajax_query_attachments_args`, and AttachmentsBrowser toolbar patching.

## Development

```bash
composer install
composer test
```

## License

LGPL-3.0-only
