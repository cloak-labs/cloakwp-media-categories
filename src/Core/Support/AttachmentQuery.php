<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core\Support;

use CloakWP\MediaCategories\Core\Config;

/**
 * Builds tax_query fragments for media library filtering.
 */
final class AttachmentQuery
{
  public function __construct(
    private readonly Config $config,
  ) {
  }

  /**
   * Apply a taxonomy filter value to a WP_Query / ajax args array.
   *
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  public function applyToArgs(array $args, string|int|null $value): array
  {
    if ($value === null || $value === '') {
      return $args;
    }

    $value = (string) $value;

    if ($value === Config::UNCATEGORIZED_QUERY) {
      $args['tax_query'] = $this->mergeTaxQuery($args['tax_query'] ?? [], $this->uncategorizedClause());
      return $args;
    }

    if (!is_numeric($value)) {
      return $args;
    }

    $args['tax_query'] = $this->mergeTaxQuery($args['tax_query'] ?? [], [
      'taxonomy' => $this->config->slug,
      'field' => 'term_id',
      'terms' => [(int) $value],
    ]);

    return $args;
  }

  /**
   * @return array<string, mixed>
   */
  public function uncategorizedClause(): array
  {
    return [
      'taxonomy' => $this->config->slug,
      'operator' => 'NOT EXISTS',
    ];
  }

  /**
   * @param array<int|string, mixed> $existing
   * @param array<string, mixed> $clause
   * @return array<int|string, mixed>
   */
  private function mergeTaxQuery(array $existing, array $clause): array
  {
    if ($existing === []) {
      return [$clause];
    }

    // Ensure relation is AND when combining.
    if (!isset($existing['relation'])) {
      $existing = array_merge(['relation' => 'AND'], array_is_list($existing) ? $existing : array_values($existing));
    }

    $existing[] = $clause;

    return $existing;
  }
}
