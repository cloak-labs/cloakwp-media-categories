<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Core\Taxonomy;

use CloakWP\MediaTaxonomies\Core\Config;

/**
 * Registers media taxonomies on attachments.
 */
final class Registrar
{
  public const COUNT_CALLBACK = '_update_generic_term_count';

  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    add_action('init', [$this, 'registerTaxonomies'], 4);
  }

  public function registerTaxonomies(): void
  {
    foreach ($this->config->taxonomies as $taxonomy) {
      if (!taxonomy_exists($taxonomy->slug)) {
        $rewrite = false;
        if ($taxonomy->rewrite !== false) {
          $rewrite = [
            'slug' => $taxonomy->rewrite,
            'with_front' => false,
          ];
        }

        register_taxonomy($taxonomy->slug, ['attachment'], [
          'labels' => $taxonomy->taxonomyLabels(),
          'hierarchical' => $taxonomy->hierarchical,
          'public' => true,
          'publicly_queryable' => true,
          'show_ui' => true,
          'show_in_menu' => true,
          'show_in_nav_menus' => false,
          'show_admin_column' => true,
          'show_in_rest' => $taxonomy->showInRest,
          'rest_base' => $taxonomy->restBase,
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

      $this->forceGenericCountCallback($taxonomy->slug);
    }
  }

  public function forceGenericCountCallback(string $slug): void
  {
    global $wp_taxonomies;

    if (isset($wp_taxonomies[$slug]) && is_object($wp_taxonomies[$slug])) {
      $wp_taxonomies[$slug]->update_count_callback = self::COUNT_CALLBACK;
    }
  }
}
