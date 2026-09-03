<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin\Cli;

use CloakWP\MediaTaxonomies\Core\Support\SplitHierarchy;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI commands for media taxonomies.
 */
final class SplitHierarchyCommand extends WP_CLI_Command
{
  /**
   * Split a hierarchical media taxonomy into several taxonomies in place.
   *
   * Term IDs and attachment relationships are preserved. Empty container
   * parents are deleted after their children are reassigned.
   *
   * ## OPTIONS
   *
   * [--dry-run]
   * : Print the plan without writing.
   *
   * [--source=<taxonomy>]
   * : Source taxonomy slug.
   * ---
   * default: category_media
   * ---
   *
   * @when after_wp_load
   * @subcommand split-hierarchy
   *
   * @param list<string> $args
   * @param array<string, mixed> $assocArgs
   */
  public function split_hierarchy(array $args, array $assocArgs): void
  {
    $source = sanitize_key((string) ($assocArgs['source'] ?? 'category_media'));
    $dryRun = isset($assocArgs['dry-run']);

    $terms = get_terms([
      'taxonomy' => $source,
      'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
      WP_CLI::error($terms->get_error_message());
    }

    if (!is_array($terms) || $terms === []) {
      WP_CLI::warning(sprintf('No terms found in taxonomy "%s".', $source));
      return;
    }

    $plan = SplitHierarchy::plan($terms);

    if ($plan['unmapped'] !== []) {
      foreach ($plan['unmapped'] as $row) {
        WP_CLI::warning(sprintf(
          'Unmapped term #%d %s (%s) parent=%d',
          $row['term_id'],
          $row['name'],
          $row['slug'],
          $row['parent'],
        ));
      }
      WP_CLI::error('Aborting: unmapped terms remain. Extend SplitHierarchy::ROOTS / CONTAINERS.');
    }

    WP_CLI::log(sprintf('Moves: %d', count($plan['moves'])));
    foreach ($plan['moves'] as $move) {
      WP_CLI::log(sprintf(
        '  #%d %s → %s parent=%d',
        $move['term_id'],
        $move['slug'],
        $move['taxonomy'],
        $move['parent'],
      ));
    }

    WP_CLI::log(sprintf('Deletes: %d', count($plan['deletes'])));
    foreach ($plan['deletes'] as $delete) {
      WP_CLI::log(sprintf('  #%d %s', $delete['term_id'], $delete['slug']));
    }

    WP_CLI::log(sprintf('Creates: %d', count($plan['creates'])));
    foreach ($plan['creates'] as $create) {
      WP_CLI::log(sprintf('  %s (%s) in %s', $create['name'], $create['slug'], $create['taxonomy']));
    }

    if ($dryRun) {
      WP_CLI::success('Dry run only. No database writes.');
      return;
    }

    global $wpdb;

    foreach ($plan['moves'] as $move) {
      $updated = $wpdb->update(
        $wpdb->term_taxonomy,
        [
          'taxonomy' => $move['taxonomy'],
          'parent' => $move['parent'],
        ],
        [
          'term_id' => $move['term_id'],
          'taxonomy' => $source,
        ],
        ['%s', '%d'],
        ['%d', '%s'],
      );
      if ($updated === false) {
        WP_CLI::error(sprintf('Failed to move term #%d (%s).', $move['term_id'], $move['slug']));
      }
    }

    foreach ($plan['creates'] as $create) {
      $existing = get_term_by('slug', $create['slug'], $create['taxonomy']);
      if ($existing) {
        WP_CLI::log(sprintf('Term %s already exists in %s.', $create['slug'], $create['taxonomy']));
        continue;
      }
      $inserted = wp_insert_term($create['name'], $create['taxonomy'], ['slug' => $create['slug']]);
      if (is_wp_error($inserted)) {
        WP_CLI::error($inserted->get_error_message());
      }
    }

    foreach ($plan['deletes'] as $delete) {
      $result = wp_delete_term($delete['term_id'], $source);
      if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
      }
    }

    $taxonomies = array_unique(array_column($plan['moves'], 'taxonomy'));
    foreach ($plan['creates'] as $create) {
      $taxonomies[] = $create['taxonomy'];
    }
    foreach (array_unique($taxonomies) as $taxonomy) {
      $ttIds = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'fields' => 'tt_ids',
      ]);
      if (is_wp_error($ttIds) || $ttIds === []) {
        continue;
      }
      wp_update_term_count_now(array_map('intval', $ttIds), $taxonomy);
    }

    $remaining = get_terms([
      'taxonomy' => $source,
      'hide_empty' => false,
      'fields' => 'ids',
    ]);
    if (!is_wp_error($remaining) && $remaining !== []) {
      WP_CLI::error(sprintf(
        'Source taxonomy "%s" still has %d terms after the split.',
        $source,
        count($remaining),
      ));
    }

    clean_taxonomy_cache($source);
    WP_CLI::success('Hierarchy split complete.');
  }
}
