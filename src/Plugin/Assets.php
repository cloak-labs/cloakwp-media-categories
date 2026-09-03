<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin;

use CloakWP\Core\Media\LibraryFilters;
use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\TermTree;

/**
 * Registers and enqueues admin JS/CSS via plugin_dir_url().
 */
final class Assets
{
  public const SCRIPT_HANDLE = 'media-taxonomies-admin';
  public const STYLE_HANDLE = 'media-taxonomies-admin';

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
    add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueueForAcf'], 20);
  }

  public function registerHandles(): void
  {
    LibraryFilters::registerAssets();

    $version = defined('CLOAKWP_MEDIA_TAXONOMIES_VERSION')
      ? CLOAKWP_MEDIA_TAXONOMIES_VERSION
      : '0.1.0';

    $jsPath = $this->path('resources/js/media-library.js');
    $cssPath = $this->path('resources/css/admin.css');

    if (is_readable($jsPath)) {
      $jsVersion = $version . '.' . (string) filemtime($jsPath);
      $jsUrl = $this->url('resources/js/media-library.js');

      $deps = ['jquery', 'media-views', 'wp-api-fetch', LibraryFilters::SCRIPT_HANDLE];
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
        $styles->registered[self::STYLE_HANDLE]->deps = ['dashicons'];
        $styles->registered[self::STYLE_HANDLE]->ver = $cssVersion;
      } else {
        wp_register_style(self::STYLE_HANDLE, $cssUrl, ['dashicons'], $cssVersion);
      }
    }
  }

  public function enqueueForMedia(): void
  {
    $this->enqueue();
  }

  public function enqueueForAcf(): void
  {
    if (function_exists('wp_enqueue_media')) {
      wp_enqueue_media();
    }
    $this->enqueue();
  }

  public function enqueue(): void
  {
    LibraryFilters::enqueue();

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

  public function enqueueOnUploadScreen(string $hookSuffix): void
  {
    if (!in_array($hookSuffix, ['upload.php', 'post.php', 'post-new.php', 'media-upload.php', 'attachment'], true)) {
      return;
    }

    $this->registerHandles();
    $this->enqueue();
  }

  private function localize(): void
  {
    if ($this->localized || !wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }

    $taxonomies = [];
    foreach ($this->config->taxonomies as $taxonomy) {
      $wpTax = get_taxonomy($taxonomy->slug);
      $taxLabels = ($wpTax && isset($wpTax->labels)) ? $wpTax->labels : null;
      $singular = $taxLabels?->singular_name ?? $taxonomy->singularLabel;
      $plural = $taxLabels?->name ?? $taxonomy->pluralLabel;

      $taxonomies[] = [
        'slug' => $taxonomy->slug,
        'restBase' => $taxonomy->restBase,
        'filterArg' => $taxonomy->listFilterArg(),
        'hierarchical' => $taxonomy->hierarchical,
        'terms' => $wpTax ? TermTree::fromTaxonomy($taxonomy->slug) : [],
        'labels' => [
          'singular' => $singular,
          'plural' => $plural,
          'all' => $taxLabels?->all_items ?? sprintf('All %s', strtolower($plural)),
          'filterBy' => $taxLabels?->filter_by_item ?? sprintf('Filter by %s', $singular),
          'uncategorized' => 'Uncategorized',
          'categorized' => 'Categorized',
          'include' => 'In',
          'exclude' => 'Not in',
          'selectedCount' => '%d selected',
          'bulkEdit' => sprintf('Edit %s', strtolower($plural)),
          'addNew' => $taxLabels?->add_new_item ?? sprintf('Add New %s', $singular),
          'newName' => $taxLabels?->new_item_name ?? sprintf('New %s Name', $singular),
          'parent' => $taxLabels?->parent_item ?? sprintf('Parent %s', $singular),
          'none' => '— None —',
        ],
      ];
    }

    wp_localize_script(self::SCRIPT_HANDLE, 'mediaTaxonomiesAdmin', [
      'taxonomies' => $taxonomies,
      'uncategorized' => Config::UNCATEGORIZED_QUERY,
      'manageCap' => current_user_can(Config::MANAGE_CAP),
      'assignCap' => current_user_can(Config::ASSIGN_CAP),
      'labels' => [
        'addToSelected' => 'Add to selected',
        'removeFromSelected' => 'Remove from selected',
        'cancel' => 'Cancel',
        'apply' => 'Apply',
        'bulkEdit' => 'Edit media taxonomies',
        'bulkSuccess' => 'Taxonomies updated.',
        'bulkError' => 'Could not update taxonomies.',
        'refresh' => 'Refresh terms',
        'refreshError' => 'Could not refresh terms.',
      ],
      'restUrl' => esc_url_raw(rest_url('media-taxonomies/v1/bulk-assign')),
      'termsUrl' => esc_url_raw(rest_url('media-taxonomies/v1/terms')),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);

    $this->localized = true;
  }

  private function url(string $relative): string
  {
    $relative = ltrim($relative, '/');
    $base = plugins_url('', $this->pluginFile);

    if (defined('WPMU_PLUGIN_URL') && (str_contains($base, '/var/www/') || str_contains($base, '/plugins/var/'))) {
      $base = trailingslashit(WPMU_PLUGIN_URL) . 'media-taxonomies';
    }

    return trailingslashit($base) . $relative;
  }

  private function path(string $relative): string
  {
    $dir = defined('CLOAKWP_MEDIA_TAXONOMIES_DIR')
      ? CLOAKWP_MEDIA_TAXONOMIES_DIR
      : dirname($this->pluginFile);

    $real = realpath($dir);
    if ($real !== false) {
      $dir = $real;
    }

    return rtrim($dir, '/\\') . '/' . ltrim($relative, '/');
  }
}
