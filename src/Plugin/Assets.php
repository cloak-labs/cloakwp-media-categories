<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin;

use CloakWP\MediaCategories\Core\Config;

/**
 * Registers and enqueues admin JS/CSS via plugin_dir_url().
 */
final class Assets
{
  public const SCRIPT_HANDLE = 'media-categories-admin';
  public const STYLE_HANDLE = 'media-categories-admin';

  private bool $localized = false;

  public function __construct(
    private readonly Config $config,
    private readonly string $pluginFile,
  ) {
  }

  public function register(): void
  {
    add_action('admin_enqueue_scripts', [$this, 'registerHandles'], 1);
    add_action('admin_enqueue_scripts', [$this, 'enqueueOnUploadScreen'], 20);
    add_action('wp_enqueue_media', [$this, 'enqueueForMedia'], 20);
  }

  public function registerHandles(): void
  {
    $version = defined('CLOAKWP_MEDIA_CATEGORIES_VERSION')
      ? CLOAKWP_MEDIA_CATEGORIES_VERSION
      : '0.1.0';

    $jsPath = $this->path('resources/js/media-library.js');
    $cssPath = $this->path('resources/css/admin.css');

    if (is_readable($jsPath)) {
      $jsVersion = $version . '.' . (string) filemtime($jsPath);
      $jsUrl = $this->url('resources/js/media-library.js');

      // Patch AttachmentsBrowser before wp-admin `media` creates the Manage frame on ready.
      // We intentionally do NOT depend on `media` — that would place us after its ready handler
      // registration; instead we load after media-views/media-grid and patch in our IIFE.
      $deps = ['jquery', 'media-views', 'wp-api-fetch'];
      if (wp_script_is('media-grid', 'registered') || wp_script_is('media-grid', 'enqueued')) {
        $deps[] = 'media-grid';
      }

      if (wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
        $scripts = wp_scripts();
        $scripts->registered[self::SCRIPT_HANDLE]->src = $jsUrl;
        $scripts->registered[self::SCRIPT_HANDLE]->deps = $deps;
        $scripts->registered[self::SCRIPT_HANDLE]->ver = $jsVersion;
      } else {
        wp_register_script(self::SCRIPT_HANDLE, $jsUrl, $deps, $jsVersion, true);
      }
    }

    if (is_readable($cssPath)) {
      $cssUrl = $this->url('resources/css/admin.css');
      $cssVersion = $version . '.' . (string) filemtime($cssPath);

      if (wp_style_is(self::STYLE_HANDLE, 'registered')) {
        $styles = wp_styles();
        $styles->registered[self::STYLE_HANDLE]->src = $cssUrl;
        $styles->registered[self::STYLE_HANDLE]->ver = $cssVersion;
      } else {
        wp_register_style(self::STYLE_HANDLE, $cssUrl, [], $cssVersion);
      }
    }
  }

  /**
   * Enqueue whenever wp_enqueue_media runs (grid library + editor modal).
   */
  public function enqueueForMedia(): void
  {
    $this->enqueue();
  }

  public function enqueue(): void
  {
    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      $this->registerHandles();
    }

    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    wp_enqueue_script(self::SCRIPT_HANDLE);
    wp_enqueue_style(self::STYLE_HANDLE);
    $this->localize();
  }

  /**
   * Enqueue on upload.php (list + grid) and attachment edit screens.
   */
  public function enqueueOnUploadScreen(string $hookSuffix): void
  {
    if (!in_array($hookSuffix, ['upload.php', 'post.php', 'media-upload.php', 'attachment'], true)) {
      return;
    }

    // Re-register so media-grid can be added to deps when it was registered in upload.php.
    $this->registerHandles();
    $this->enqueue();
  }

  private function localize(): void
  {
    if ($this->localized || !wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    $taxonomy = get_taxonomy($this->config->slug);
    $taxLabels = ($taxonomy && isset($taxonomy->labels)) ? $taxonomy->labels : null;
    $singular = $taxLabels?->singular_name ?? $this->config->singularLabel;
    $plural = $taxLabels?->name ?? $this->config->pluralLabel;

    $termList = [];
    if ($taxonomy) {
      $terms = get_terms([
        'taxonomy' => $this->config->slug,
        'hide_empty' => false,
      ]);
      if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
          $termList[] = [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
            'count' => (int) $term->count,
          ];
        }
      }
    }

    wp_localize_script(self::SCRIPT_HANDLE, 'mediaCategoriesAdmin', [
      'taxonomy' => $this->config->slug,
      'restBase' => $this->config->restBase,
      'filterArg' => Admin\ListTable::FILTER_ARG,
      'uncategorized' => Config::UNCATEGORIZED_QUERY,
      'manageCap' => current_user_can(Config::MANAGE_CAP),
      'assignCap' => current_user_can(Config::ASSIGN_CAP),
      'terms' => $termList,
      'labels' => [
        'singular' => $singular,
        'plural' => $plural,
        'all' => $taxLabels?->all_items ?? sprintf('All %s', strtolower($plural)),
        'filterBy' => $taxLabels?->filter_by_item ?? sprintf('Filter by %s', $singular),
        'uncategorized' => 'Uncategorized',
        'bulkEdit' => sprintf('Edit %s', strtolower($plural)),
        'addToSelected' => 'Add to selected',
        'removeFromSelected' => 'Remove from selected',
        'addNew' => $taxLabels?->add_new_item ?? sprintf('Add New %s', $singular),
        'newName' => $taxLabels?->new_item_name ?? sprintf('New %s Name', $singular),
        'parent' => $taxLabels?->parent_item ?? sprintf('Parent %s', $singular),
        'none' => '— None —',
        'cancel' => 'Cancel',
        'apply' => 'Apply',
        'bulkSuccess' => 'Categories updated.',
        'bulkError' => 'Could not update categories.',
      ],
      'restUrl' => esc_url_raw(rest_url('media-categories/v1/bulk-assign')),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);

    $this->localized = true;
  }

  private function url(string $relative): string
  {
    $relative = ltrim($relative, '/');
    $base = plugins_url('', $this->pluginFile);

    // Symlink safety net: plugins_url() can emit /app/plugins/var/www/... when
    // the package realpath sits outside wp-content.
    if (defined('WPMU_PLUGIN_URL') && (str_contains($base, '/var/www/') || str_contains($base, '/plugins/var/'))) {
      $base = trailingslashit(WPMU_PLUGIN_URL) . 'media-categories';
    }

    return trailingslashit($base) . $relative;
  }

  private function path(string $relative): string
  {
    // Read from the real package dir (symlink target) so filemtime/is_readable work.
    $dir = defined('CLOAKWP_MEDIA_CATEGORIES_DIR')
      ? CLOAKWP_MEDIA_CATEGORIES_DIR
      : dirname($this->pluginFile);

    // Prefer realpath for filesystem ops when the public dir is a symlink.
    $real = realpath($dir);
    if ($real !== false) {
      $dir = $real;
    }

    return rtrim($dir, '/\\') . '/' . ltrim($relative, '/');
  }
}
