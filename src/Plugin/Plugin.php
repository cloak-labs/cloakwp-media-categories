<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin;

use CloakWP\Core\Media\LibraryFilter;
use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\AttachmentQuery;
use CloakWP\MediaTaxonomies\Core\Support\TermAssigner;
use CloakWP\MediaTaxonomies\Core\Support\TermTree;
use CloakWP\MediaTaxonomies\Plugin\Admin\AttachmentDetails;
use CloakWP\MediaTaxonomies\Plugin\Admin\ListTable;
use CloakWP\MediaTaxonomies\Plugin\Cli\SplitHierarchyCommand;
use CloakWP\MediaTaxonomies\Plugin\Rest\BulkAssignController;
use CloakWP\MediaTaxonomies\Plugin\Rest\TermsController;

/**
 * WordPress integration layer (hooks, admin UI, REST, assets).
 */
final class Plugin
{
  /**
   * @param array<string, TermAssigner> $termAssigners
   * @param array<string, AttachmentQuery> $attachmentQueries
   */
  public function __construct(
    private readonly Config $config,
    private readonly array $termAssigners,
    private readonly array $attachmentQueries,
    private readonly string $pluginFile,
  ) {
  }

  public function boot(): void
  {
    $assets = new Assets($this->config, $this->pluginFile);
    $assets->register();

    $listTable = new ListTable($this->config);
    $listTable->register();

    $this->registerLibraryFilters($listTable);

    (new AttachmentDetails($this->config, $this->termAssigners, $assets))->register();
    (new BulkAssignController($this->config, $this->termAssigners))->register();
    (new TermsController($this->config))->register();
    (new Maintenance($this->config))->register();

    foreach ($this->config->taxonomies as $taxonomy) {
      if ($taxonomy->defaultTerm === null) {
        continue;
      }
      $assigner = $this->termAssigners[$taxonomy->slug] ?? null;
      if ($assigner !== null) {
        add_action('add_attachment', [$assigner, 'assignDefaultIfConfigured']);
      }
    }

    add_action('init', [$this, 'maybeFlushRewrites'], 99);

    if (defined('WP_CLI') && WP_CLI) {
      \WP_CLI::add_command('media-taxonomies', SplitHierarchyCommand::class);
    }
  }

  public function maybeFlushRewrites(): void
  {
    $parts = [];
    foreach ($this->config->taxonomies as $taxonomy) {
      if ($taxonomy->rewrite === false) {
        continue;
      }
      $parts[] = $taxonomy->slug . '|' . $taxonomy->rewrite;
    }

    $desired = implode(';', $parts);
    $optionKey = 'cloakwp_media_taxonomies_rewrite';
    $current = (string) get_option($optionKey, '');

    if ($current === $desired) {
      return;
    }

    if ($desired !== '') {
      flush_rewrite_rules(false);
    }
    update_option($optionKey, $desired, false);
  }

  private function registerLibraryFilters(ListTable $listTable): void
  {
    $priority = -74;

    foreach ($this->config->taxonomies as $taxonomy) {
      $attachmentQuery = $this->attachmentQueries[$taxonomy->slug] ?? null;
      if ($attachmentQuery === null) {
        continue;
      }

      $labels = $taxonomy->taxonomyLabels();
      $listFilterArg = $taxonomy->listFilterArg();

      LibraryFilter::make($taxonomy->slug)
        ->queryVar($taxonomy->slug)
        ->label((string) ($labels['filter_by_item'] ?? sprintf('Filter by %s', $taxonomy->singularLabel)))
        ->allLabel((string) ($labels['all_items'] ?? sprintf('All %s', strtolower($taxonomy->pluralLabel))))
        ->grid(LibraryFilter::GRID_CUSTOM)
        ->priority($priority)
        ->modelKeys([$taxonomy->slug, $listFilterArg])
        ->supportsExclude(true)
        ->schemaOptions(static function () use ($taxonomy): array {
          if (function_exists('taxonomy_exists') && !taxonomy_exists($taxonomy->slug)) {
            return [];
          }

          $out = [];
          foreach (TermTree::fromTaxonomy($taxonomy->slug) as $row) {
            $out[] = [
              'value' => (string) $row['id'],
              'label' => $row['name'],
              'parent' => $row['parent'] > 0 ? (string) $row['parent'] : null,
              'slug' => $row['slug'],
            ];
          }

          return $out;
        })
        ->listRenderer(static function () use ($listTable, $taxonomy): void {
          $listTable->renderFilter($taxonomy);
        })
        ->resolveValue(static function (array $args) use ($listTable, $taxonomy): ?string {
          $value = $listTable->resolveFilterValue($taxonomy, $args);

          return $value === '' ? null : $value;
        })
        ->query(static function (array $args, string $value) use ($attachmentQuery, $taxonomy, $listFilterArg): array {
          unset($args[$taxonomy->slug], $args[$listFilterArg]);

          return $attachmentQuery->applyToArgs($args, $value);
        })
        ->register();

      $priority++;
    }
  }
}
