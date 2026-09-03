<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\LegacyImport;
use CloakWP\MediaTaxonomies\Core\Taxonomy\Registrar;
use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;
use WP_Term;

/**
 * One-time per-site work after switching from MCM (or first boot):
 * copy assignments from MCM's configured taxonomy onto the default
 * Media Categories taxonomy, then recount terms so unattached media
 * is included in filter counts.
 */
final class Maintenance
{
  public const OPTION_KEY = 'cloakwp_media_categories_schema';
  public const SCHEMA_VERSION = 1;

  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    add_action('init', [$this, 'maybeMigrate'], 20);
  }

  public function maybeMigrate(): void
  {
    if ((int) get_option(self::OPTION_KEY, 0) >= self::SCHEMA_VERSION) {
      return;
    }

    $registrar = new Registrar($this->config);
    foreach ($this->config->taxonomies as $taxonomy) {
      $registrar->forceGenericCountCallback($taxonomy->slug);
    }

    $default = $this->legacyDestination();
    if ($default !== null) {
      $this->importFromLegacyTaxonomies($default);
    }

    $this->recount();

    update_option(self::OPTION_KEY, self::SCHEMA_VERSION, false);
  }

  private function legacyDestination(): ?TaxonomyConfig
  {
    foreach ($this->config->taxonomies as $taxonomy) {
      if ($taxonomy->slug === 'category_media') {
        return $taxonomy;
      }
    }

    return null;
  }

  private function importFromLegacyTaxonomies(TaxonomyConfig $destination): void
  {
    $mcmOptions = get_option(LegacyImport::MCM_OPTION_KEY);
    $sources = LegacyImport::sourceTaxonomies($mcmOptions, $destination->slug);

    foreach (LegacyImport::fallbackTaxonomies($destination->slug) as $fallback) {
      if ($this->taxonomyHasAttachmentAssignments($fallback)) {
        $sources[] = $fallback;
      }
    }

    foreach (array_unique($sources) as $source) {
      $this->copyAttachmentAssignments($source, $destination);
    }
  }

  private function taxonomyHasAttachmentAssignments(string $taxonomy): bool
  {
    global $wpdb;

    $count = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
       INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
       INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'attachment'
       WHERE tt.taxonomy = %s",
      $taxonomy
    ));

    return $count > 0;
  }

  private function copyAttachmentAssignments(string $sourceTaxonomy, TaxonomyConfig $destination): void
  {
    global $wpdb;

    if ($sourceTaxonomy === '' || $sourceTaxonomy === $destination->slug) {
      return;
    }

    $sourceTerms = $wpdb->get_results($wpdb->prepare(
      "SELECT t.term_id, t.slug, t.name, tt.term_taxonomy_id, tt.parent
       FROM {$wpdb->term_taxonomy} tt
       INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
       WHERE tt.taxonomy = %s
       ORDER BY tt.parent ASC, t.name ASC",
      $sourceTaxonomy
    ));

    if (!is_array($sourceTerms) || $sourceTerms === []) {
      return;
    }

    $destTerms = $this->destinationTerms($destination->slug);
    $parentMap = [];

    foreach ($sourceTerms as $sourceTerm) {
      $objectIds = $wpdb->get_col($wpdb->prepare(
        "SELECT tr.object_id FROM {$wpdb->term_relationships} tr
         INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'attachment'
         WHERE tr.term_taxonomy_id = %d",
        (int) $sourceTerm->term_taxonomy_id
      ));

      if ($objectIds === []) {
        continue;
      }

      $parentDestId = 0;
      $sourceParent = (int) $sourceTerm->parent;
      if ($sourceParent > 0 && isset($parentMap[$sourceParent])) {
        $parentDestId = $parentMap[$sourceParent];
      }

      $destTerm = $this->ensureDestinationTerm($sourceTerm, $destTerms, $parentDestId, $destination->slug);
      if ($destTerm === null) {
        continue;
      }

      $parentMap[(int) $sourceTerm->term_id] = (int) $destTerm->term_id;
      $destTerms[] = $destTerm;

      foreach ($objectIds as $objectId) {
        wp_set_object_terms((int) $objectId, [(int) $destTerm->term_id], $destination->slug, true);
      }
    }
  }

  /**
   * @return list<object>
   */
  private function destinationTerms(string $slug): array
  {
    $terms = get_terms([
      'taxonomy' => $slug,
      'hide_empty' => false,
    ]);

    return is_wp_error($terms) ? [] : array_values($terms);
  }

  /**
   * @param list<object> $destinationTerms
   */
  private function ensureDestinationTerm(object $sourceTerm, array $destinationTerms, int $parentId, string $slug): ?WP_Term
  {
    $matched = LegacyImport::matchTerm($sourceTerm, $destinationTerms);
    if ($matched instanceof WP_Term) {
      return $matched;
    }
    if (is_object($matched) && isset($matched->term_id)) {
      $existing = get_term((int) $matched->term_id, $slug);
      return $existing instanceof WP_Term ? $existing : null;
    }

    $args = [];
    if ($parentId > 0) {
      $args['parent'] = $parentId;
    }
    if ((string) $sourceTerm->slug !== '') {
      $args['slug'] = (string) $sourceTerm->slug;
    }

    $created = wp_insert_term((string) $sourceTerm->name, $slug, $args);
    if (is_wp_error($created)) {
      $existing = get_term_by('slug', (string) $sourceTerm->slug, $slug);
      return $existing instanceof WP_Term ? $existing : null;
    }

    $term = get_term((int) $created['term_id'], $slug);

    return $term instanceof WP_Term ? $term : null;
  }

  private function recount(): void
  {
    foreach ($this->config->taxonomies as $taxonomy) {
      $ttIds = get_terms([
        'taxonomy' => $taxonomy->slug,
        'hide_empty' => false,
        'fields' => 'tt_ids',
      ]);

      if (is_wp_error($ttIds) || $ttIds === []) {
        continue;
      }

      wp_update_term_count_now(array_map('intval', $ttIds), $taxonomy->slug);
    }
  }
}
