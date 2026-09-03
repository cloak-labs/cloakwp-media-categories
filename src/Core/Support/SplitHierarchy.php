<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Core\Support;

/**
 * Plans an in-place split of a hierarchical media taxonomy into several taxonomies.
 *
 * Term IDs and term_taxonomy_ids are preserved; callers UPDATE taxonomy/parent
 * columns rather than recreating terms.
 */
final class SplitHierarchy
{
  /**
   * Empty container parents that become taxonomies themselves.
   *
   * @var array<string, string> parent slug => destination taxonomy
   */
  public const CONTAINERS = [
    'landscaping' => 'landscape_type',
    'outdoor-living' => 'outdoor_living_type',
  ];

  /**
   * Root terms (parent 0) that move as-is.
   *
   * @var array<string, string> slug => destination taxonomy
   */
  public const ROOTS = [
    'commercial' => 'client_type',
    'stratas' => 'client_type',
    'customers' => 'client_type',
    'aerials' => 'photo_type',
    'before-photos' => 'photo_type',
    'best-work' => 'photo_type',
    'close-ups' => 'photo_type',
    'design-renderings' => 'photo_type',
    'in-progress' => 'photo_type',
    'team' => 'photo_type',
  ];

  /**
   * @var list<array{name: string, slug: string, taxonomy: string}>
   */
  public const CREATE = [
    [
      'name' => 'Residential',
      'slug' => 'residential',
      'taxonomy' => 'client_type',
    ],
  ];

  /**
   * @param list<object|array<string, mixed>> $terms
   * @return array{
   *   moves: list<array{term_id: int, slug: string, name: string, taxonomy: string, parent: int}>,
   *   deletes: list<array{term_id: int, slug: string, name: string}>,
   *   creates: list<array{name: string, slug: string, taxonomy: string}>,
   *   unmapped: list<array{term_id: int, slug: string, name: string, parent: int}>
   * }
   */
  public static function plan(array $terms): array
  {
    $byId = [];
    foreach ($terms as $term) {
      $row = self::normalize($term);
      if ($row['term_id'] > 0) {
        $byId[$row['term_id']] = $row;
      }
    }

    $moves = [];
    $deletes = [];
    $unmapped = [];

    foreach ($byId as $row) {
      if (isset(self::CONTAINERS[$row['slug']])) {
        $deletes[] = [
          'term_id' => $row['term_id'],
          'slug' => $row['slug'],
          'name' => $row['name'],
        ];
        continue;
      }

      $taxonomy = self::destination($row, $byId);
      if ($taxonomy === null) {
        $unmapped[] = [
          'term_id' => $row['term_id'],
          'slug' => $row['slug'],
          'name' => $row['name'],
          'parent' => $row['parent'],
        ];
        continue;
      }

      $moves[] = [
        'term_id' => $row['term_id'],
        'slug' => $row['slug'],
        'name' => $row['name'],
        'taxonomy' => $taxonomy,
        'parent' => self::newParent($row, $byId, $taxonomy),
      ];
    }

    return [
      'moves' => $moves,
      'deletes' => $deletes,
      'creates' => self::CREATE,
      'unmapped' => $unmapped,
    ];
  }

  /**
   * @param array<int, array{term_id: int, slug: string, name: string, parent: int, count: int}> $byId
   */
  public static function destination(array $row, array $byId): ?string
  {
    $current = $row;
    $guard = 0;
    while ($current && $guard++ < 50) {
      if (isset(self::CONTAINERS[$current['slug']])) {
        return self::CONTAINERS[$current['slug']];
      }
      if (isset(self::ROOTS[$current['slug']])) {
        return self::ROOTS[$current['slug']];
      }
      if ($current['parent'] <= 0) {
        return null;
      }
      $current = $byId[$current['parent']] ?? null;
      if ($current === null) {
        return null;
      }
    }

    return null;
  }

  /**
   * @param array<int, array{term_id: int, slug: string, name: string, parent: int, count: int}> $byId
   */
  public static function newParent(array $row, array $byId, string $taxonomy): int
  {
    $parentId = $row['parent'];
    if ($parentId <= 0) {
      return 0;
    }

    $parent = $byId[$parentId] ?? null;
    if ($parent === null) {
      return 0;
    }

    if (isset(self::CONTAINERS[$parent['slug']])) {
      return 0;
    }

    $parentDest = self::destination($parent, $byId);
    if ($parentDest !== $taxonomy) {
      return 0;
    }

    return $parentId;
  }

  /**
   * @param object|array<string, mixed> $term
   * @return array{term_id: int, slug: string, name: string, parent: int, count: int}
   */
  private static function normalize(object|array $term): array
  {
    if (is_array($term)) {
      return [
        'term_id' => (int) ($term['term_id'] ?? $term['id'] ?? 0),
        'slug' => (string) ($term['slug'] ?? ''),
        'name' => (string) ($term['name'] ?? ''),
        'parent' => (int) ($term['parent'] ?? 0),
        'count' => (int) ($term['count'] ?? 0),
      ];
    }

    return [
      'term_id' => (int) ($term->term_id ?? $term->id ?? 0),
      'slug' => (string) ($term->slug ?? ''),
      'name' => (string) ($term->name ?? ''),
      'parent' => (int) ($term->parent ?? 0),
      'count' => (int) ($term->count ?? 0),
    ];
  }
}
