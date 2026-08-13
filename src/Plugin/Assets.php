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
    add_action('admin_enqueue_scripts', [$this, 'registerHandles']);
    add_action('wp_enqueue_media', [$this, 'enqueueForMedia']);
  }

  public function registerHandles(): void
  {
    $version = defined('CLOAKWP_MEDIA_CATEGORIES_VERSION')
      ? CLOAKWP_MEDIA_CATEGORIES_VERSION
      : '0.1.0';

    $jsPath = $this->path('resources/js/media-library.js');
    $cssPath = $this->path('resources/css/admin.css');

    if (is_readable($jsPath)) {
      $version = $version . '.' . (string) filemtime($jsPath);
      wp_register_script(
        self::SCRIPT_HANDLE,
        $this->url('resources/js/media-library.js'),
        ['jquery', 'media-views', 'wp-api-fetch', 'wp-i18n'],
        $version,
        true,
      );
    }

    if (is_readable($cssPath)) {
      wp_register_style(
        self::STYLE_HANDLE,
        $this->url('resources/css/admin.css'),
        [],
        $version . '.' . (string) filemtime($cssPath),
      );
    }
  }

  /**
   * Enqueue on media screens / whenever wp_enqueue_media runs.
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

    wp_enqueue_script(self::SCRIPT_HANDLE);
    wp_enqueue_style(self::STYLE_HANDLE);
    $this->localize();
  }

  /**
   * Enqueue on upload.php (list + grid) and attachment edit screens.
   */
  public function enqueueOnUploadScreen(string $hookSuffix): void
  {
    if (!in_array($hookSuffix, ['upload.php', 'post.php', 'media-upload.php'], true)) {
      return;
    }

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
      'uncategorized' => Config::UNCATEGORIZED_QUERY,
      'manageCap' => current_user_can(Config::MANAGE_CAP),
      'assignCap' => current_user_can(Config::ASSIGN_CAP),
      'terms' => $termList,
      'labels' => [
        'singular' => $singular,
        'plural' => $plural,
        'all' => $taxLabels?->all_items ?? sprintf('All %s', $plural),
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
    return plugins_url($relative, $this->pluginFile);
  }

  private function path(string $relative): string
  {
    return dirname($this->pluginFile) . '/' . ltrim($relative, '/');
  }
}
