<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Core\Support;

use CloakWP\Core\Media\QueryArgs;
use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;

/**
 * Builds tax_query fragments for media library filtering.
 *
 * Filter values are a compact string so they survive wp_ajax_query_attachments()'s
 * taxonomy-query_var allowlist (one key, the taxonomy slug):
 *
 * - `` (empty) — no filter
 * - `uncategorized` — no terms assigned
 * - `not:uncategorized` — at least one term assigned
 * - `12` / `12,15` — in any of these terms (children included)
 * - `not:12,15` — in none of these terms (children included)
 */
final class AttachmentQuery
{
  public const MODE_IN = 'in';
  public const MODE_NOT = 'not';
  public const NOT_PREFIX = 'not:';

  public function __construct(
    private readonly TaxonomyConfig $taxonomy,
  ) {
  }

  /**
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  public function applyToArgs(array $args, string|int|null $value): array
  {
    $parsed = self::parse($value);
    if ($parsed['empty']) {
      return $args;
    }

    if ($parsed['uncategorized']) {
      $clause = $parsed['mode'] === self::MODE_NOT
        ? $this->categorizedClause()
        : $this->uncategorizedClause();
      $args['tax_query'] = QueryArgs::mergeTaxQuery($args['tax_query'] ?? [], $clause);
      return $args;
    }

    if ($parsed['termIds'] === []) {
      return $args;
    }

    $args['tax_query'] = QueryArgs::mergeTaxQuery($args['tax_query'] ?? [], [
      'taxonomy' => $this->taxonomy->slug,
      'field' => 'term_id',
      'terms' => $parsed['termIds'],
      'operator' => $parsed['mode'] === self::MODE_NOT ? 'NOT IN' : 'IN',
      'include_children' => true,
    ]);

    return $args;
  }

  /**
   * @return array{mode: string, uncategorized: bool, termIds: list<int>, empty: bool}
   */
  public static function parse(string|int|null $value): array
  {
    $empty = [
      'mode' => self::MODE_IN,
      'uncategorized' => false,
      'termIds' => [],
      'empty' => true,
    ];

    if ($value === null) {
      return $empty;
    }

    $raw = trim((string) $value);
    if ($raw === '' || $raw === '0') {
      return $empty;
    }

    $mode = self::MODE_IN;
    if (str_starts_with($raw, self::NOT_PREFIX)) {
      $mode = self::MODE_NOT;
      $raw = substr($raw, strlen(self::NOT_PREFIX));
    }

    $raw = trim($raw);
    if ($raw === '' || $raw === '0') {
      return $empty;
    }

    if ($raw === Config::UNCATEGORIZED_QUERY) {
      return [
        'mode' => $mode,
        'uncategorized' => true,
        'termIds' => [],
        'empty' => false,
      ];
    }

    $termIds = [];
    foreach (explode(',', $raw) as $part) {
      $part = trim($part);
      if ($part === '' || !is_numeric($part)) {
        continue;
      }
      $id = (int) $part;
      if ($id > 0) {
        $termIds[] = $id;
      }
    }
    $termIds = array_values(array_unique($termIds));

    if ($termIds === []) {
      return $empty;
    }

    return [
      'mode' => $mode,
      'uncategorized' => false,
      'termIds' => $termIds,
      'empty' => false,
    ];
  }

  /**
   * @param list<int> $termIds
   */
  public static function encode(string $mode, array $termIds = [], bool $uncategorized = false): string
  {
    $mode = $mode === self::MODE_NOT ? self::MODE_NOT : self::MODE_IN;

    if ($uncategorized) {
      return $mode === self::MODE_NOT
        ? self::NOT_PREFIX . Config::UNCATEGORIZED_QUERY
        : Config::UNCATEGORIZED_QUERY;
    }

    $termIds = array_values(array_unique(array_filter(array_map('intval', $termIds), static fn(int $id): bool => $id > 0)));
    if ($termIds === []) {
      return '';
    }

    $joined = implode(',', $termIds);
    return $mode === self::MODE_NOT ? self::NOT_PREFIX . $joined : $joined;
  }

  /**
   * @return array<string, mixed>
   */
  public function uncategorizedClause(): array
  {
    return [
      'taxonomy' => $this->taxonomy->slug,
      'operator' => 'NOT EXISTS',
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function categorizedClause(): array
  {
    return [
      'taxonomy' => $this->taxonomy->slug,
      'operator' => 'EXISTS',
    ];
  }
}
