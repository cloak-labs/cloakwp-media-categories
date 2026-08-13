<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\LegacyImport;
use CloakWP\MediaCategories\Core\Taxonomy\Registrar;
use WP_Term;

/**
 * One-time per-site work after switching from MCM (or first boot):
 * copy assignments from MCM's configured taxonomy, then recount terms so
 * unattached media is included in filter counts.
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

    (new Registrar($this->config))->forceGenericCountCallback();
    $this->importFromLegacyTaxonomies();
    $this->recount();

    update_option(self::OPTION_KEY, self::SCHEMA_VERSION, false);
  }

  private function importFromLegacyTaxonomies(): void
  {
    $mcmOptions = get_option(LegacyImport::MCM_OPTION_KEY);
    $sources = LegacyImport::sourceTaxonomies($mcmOptions, $this->config->slug);

    foreach (LegacyImport::fallbackTaxonomies($this->config->slug) as $fallback) {
      if ($this->taxonomyHasAttachmentAssignments($fallback)) {
        $sources[] = $fallback;
      }
    }

    foreach (array_unique($sources) as $source) {
      $this->copyAttachmentAssignments($source);
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

  /**
   * Append-only copy of attachment term relationships onto our taxonomy.
   * Never replaces existing assignments.
   */
  private function copyAttachmentAssignments(string $sourceTaxonomy): void
  {
    global $wpdb;

    if ($sourceTaxonomy === '' || $sourceTaxonomy === $this->config->slug) {
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

    $destTerms = $this->destinationTerms();
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

      $destTerm = $this->ensureDestinationTerm($sourceTerm, $destTerms, $parentDestId);
      if ($destTerm === null) {
        continue;
      }

      $parentMap[(int) $sourceTerm->term_id] = (int) $destTerm->term_id;
      $destTerms[] = $destTerm;

      foreach ($objectIds as $objectId) {
        wp_set_object_terms((int) $objectId, [(int) $destTerm->term_id], $this->config->slug, true);
      }
    }
  }

  /**
   * @return list<object>
   */
  private function destinationTerms(): array
  {
    $terms = get_terms([
      'taxonomy' => $this->config->slug,
      'hide_empty' => false,
    ]);

    return is_wp_error($terms) ? [] : array_values($terms);
  }

  /**
   * @param list<object> $destinationTerms
   */
  private function ensureDestinationTerm(object $sourceTerm, array $destinationTerms, int $parentId): ?WP_Term
  {
    $matched = LegacyImport::matchTerm($sourceTerm, $destinationTerms);
    if ($matched instanceof WP_Term) {
      return $matched;
    }
    if (is_object($matched) && isset($matched->term_id)) {
      $existing = get_term((int) $matched->term_id, $this->config->slug);
      return $existing instanceof WP_Term ? $existing : null;
    }

    $args = [];
    if ($parentId > 0) {
      $args['parent'] = $parentId;
    }
    if ((string) $sourceTerm->slug !== '') {
      $args['slug'] = (string) $sourceTerm->slug;
    }

    $created = wp_insert_term((string) $sourceTerm->name, $this->config->slug, $args);
    if (is_wp_error($created)) {
      $existing = get_term_by('slug', (string) $sourceTerm->slug, $this->config->slug);
      return $existing instanceof WP_Term ? $existing : null;
    }

    $term = get_term((int) $created['term_id'], $this->config->slug);

    return $term instanceof WP_Term ? $term : null;
  }

  private function recount(): void
  {
    $ttIds = get_terms([
      'taxonomy' => $this->config->slug,
      'hide_empty' => false,
      'fields' => 'tt_ids',
    ]);

    if (is_wp_error($ttIds) || $ttIds === []) {
      return;
    }

    wp_update_term_count_now(array_map('intval', $ttIds), $this->config->slug);
  }
}
