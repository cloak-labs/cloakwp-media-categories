<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core\Taxonomy;

use CloakWP\MediaCategories\Core\Config;

/**
 * Registers the media category taxonomy on attachments.
 */
final class Registrar
{
  /**
   * Count every attachment relationship, including unattached `inherit` media.
   * Core's default `_update_post_term_count` skips those, so library filters
   * show 0 even when hundreds of files are tagged.
   */
  public const COUNT_CALLBACK = '_update_generic_term_count';

  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    add_action('init', [$this, 'registerTaxonomy'], 4);
  }

  public function registerTaxonomy(): void
  {
    if (!taxonomy_exists($this->config->slug)) {
      $rewrite = false;
      if ($this->config->rewrite !== false) {
        $rewrite = [
          'slug' => $this->config->rewrite,
          'with_front' => false,
        ];
      }

      register_taxonomy($this->config->slug, ['attachment'], [
        'labels' => $this->config->taxonomyLabels(),
        'hierarchical' => $this->config->hierarchical,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => false,
        'show_admin_column' => true,
        'show_in_rest' => $this->config->showInRest,
        'rest_base' => $this->config->restBase,
        'query_var' => true,
        'rewrite' => $rewrite,
        'update_count_callback' => self::COUNT_CALLBACK,
        'capabilities' => [
          'manage_terms' => Config::MANAGE_CAP,
          'edit_terms' => Config::MANAGE_CAP,
          'delete_terms' => Config::MANAGE_CAP,
          'assign_terms' => Config::ASSIGN_CAP,
        ],
      ]);
    }

    $this->forceGenericCountCallback();
  }

  /**
   * Win even if another plugin registered this slug first (e.g. MCM still active).
   */
  public function forceGenericCountCallback(): void
  {
    global $wp_taxonomies;

    $slug = $this->config->slug;
    if (isset($wp_taxonomies[$slug]) && is_object($wp_taxonomies[$slug])) {
      $wp_taxonomies[$slug]->update_count_callback = self::COUNT_CALLBACK;
    }
  }
}
