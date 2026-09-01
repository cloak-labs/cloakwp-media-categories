<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin;

use CloakWP\Core\Media\LibraryFilter;
use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use CloakWP\MediaCategories\Core\Support\TermTree;
use CloakWP\MediaCategories\Plugin\Admin\AttachmentDetails;
use CloakWP\MediaCategories\Plugin\Admin\ListTable;
use CloakWP\MediaCategories\Plugin\Rest\BulkAssignController;

/**
 * WordPress integration layer (hooks, admin UI, REST, assets).
 */
final class Plugin
{
  public function __construct(
    private readonly Config $config,
    private readonly TermAssigner $termAssigner,
    private readonly AttachmentQuery $attachmentQuery,
    private readonly string $pluginFile,
  ) {
  }

  public function boot(): void
  {
    $assets = new Assets($this->config, $this->pluginFile);
    $assets->register();

    $listTable = new ListTable($this->config);
    $listTable->register();

    $this->registerLibraryFilter($listTable);

    (new AttachmentDetails($this->config, $this->termAssigner, $assets))->register();
    (new BulkAssignController($this->config, $this->termAssigner))->register();
    (new Maintenance($this->config))->register();

    if ($this->config->defaultTerm !== null) {
      add_action('add_attachment', [$this->termAssigner, 'assignDefaultIfConfigured']);
    }

    // Flush rewrites once when rewrite slug changes (not on every request).
    add_action('init', [$this, 'maybeFlushRewrites'], 99);
  }

  public function maybeFlushRewrites(): void
  {
    if ($this->config->rewrite === false) {
      return;
    }

    $optionKey = 'cloakwp_media_categories_rewrite';
    $current = (string) get_option($optionKey, '');
    $desired = $this->config->slug . '|' . $this->config->rewrite;

    if ($current === $desired) {
      return;
    }

    flush_rewrite_rules(false);
    update_option($optionKey, $desired, false);
  }

  private function registerLibraryFilter(ListTable $listTable): void
  {
    $config = $this->config;
    $attachmentQuery = $this->attachmentQuery;

    $labels = $config->taxonomyLabels();

    LibraryFilter::make('media_category')
      ->queryVar(ListTable::FILTER_ARG)
      ->label((string) ($labels['filter_by_item'] ?? sprintf('Filter by %s', $config->singularLabel)))
      ->allLabel((string) ($labels['all_items'] ?? sprintf('All %s', strtolower($config->pluralLabel))))
      ->grid(LibraryFilter::GRID_CUSTOM)
      ->priority(-74)
      ->modelKeys([$config->slug, ListTable::FILTER_ARG])
      ->supportsExclude(true)
      ->schemaOptions(static function () use ($config): array {
        if (function_exists('taxonomy_exists') && !taxonomy_exists($config->slug)) {
          return [];
        }

        $terms = get_terms([
          'taxonomy' => $config->slug,
          'hide_empty' => false,
        ]);
        if (!is_array($terms) || (function_exists('is_wp_error') && is_wp_error($terms))) {
          return [];
        }

        $out = [];
        foreach (TermTree::flatten($terms) as $row) {
          $out[] = [
            'value' => (string) $row['id'],
            'label' => $row['name'],
            'parent' => $row['parent'] > 0 ? (string) $row['parent'] : null,
            'slug' => $row['slug'],
          ];
        }

        return $out;
      })
      ->listRenderer(static function () use ($listTable): void {
        $listTable->renderFilter('attachment', 'bar');
      })
      ->resolveValue(static function (array $args) use ($listTable): ?string {
        $value = $listTable->resolveFilterValue($args);

        return $value === '' ? null : $value;
      })
      ->query(static function (array $args, string $value) use ($attachmentQuery, $config): array {
        unset($args[$config->slug], $args[ListTable::FILTER_ARG]);

        return $attachmentQuery->applyToArgs($args, $value);
      })
      ->register();
  }
}
