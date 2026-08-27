<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core\Support;

/**
 * Flatten a taxonomy term list into parent-then-children order.
 */
final class TermTree
{
  public const CHILD_PREFIX = '- ';

  /**
   * @param list<object|array<string, mixed>> $terms WP_Term objects or id/name/parent arrays.
   * @return list<array{id: int, name: string, slug: string, parent: int, count: int, depth: int}>
   */
  public static function flatten(array $terms): array
  {
    $normalized = [];
    foreach ($terms as $term) {
      $row = self::normalize($term);
      if ($row['id'] > 0) {
        $normalized[$row['id']] = $row;
      }
    }

    $children = [];
    foreach ($normalized as $row) {
      $parent = $row['parent'];
      if ($parent > 0 && !isset($normalized[$parent])) {
        $parent = 0;
      }
      $children[$parent][] = $row['id'];
    }

    foreach ($children as &$ids) {
      usort($ids, static function (int $a, int $b) use ($normalized): int {
        return strcasecmp($normalized[$a]['name'], $normalized[$b]['name']);
      });
    }
    unset($ids);

    $out = [];
    $visited = [];
    $walk = static function (int $parentId, int $depth) use (&$walk, &$out, &$visited, $children, $normalized): void {
      foreach ($children[$parentId] ?? [] as $id) {
        if (isset($visited[$id])) {
          continue;
        }
        $visited[$id] = true;
        $row = $normalized[$id];
        $row['depth'] = $depth;
        $out[] = $row;
        $walk($id, $depth + 1);
      }
    };
    $walk(0, 0);

    return $out;
  }

  public static function prefix(int $depth): string
  {
    if ($depth <= 0) {
      return '';
    }

    return str_repeat(self::CHILD_PREFIX, $depth);
  }

  /**
   * @param object|array<string, mixed> $term
   * @return array{id: int, name: string, slug: string, parent: int, count: int, depth: int}
   */
  private static function normalize(object|array $term): array
  {
    if (is_array($term)) {
      return [
        'id' => (int) ($term['id'] ?? $term['term_id'] ?? 0),
        'name' => (string) ($term['name'] ?? ''),
        'slug' => (string) ($term['slug'] ?? ''),
        'parent' => (int) ($term['parent'] ?? 0),
        'count' => (int) ($term['count'] ?? 0),
        'depth' => 0,
      ];
    }

    return [
      'id' => (int) ($term->term_id ?? $term->id ?? 0),
      'name' => (string) ($term->name ?? ''),
      'slug' => (string) ($term->slug ?? ''),
      'parent' => (int) ($term->parent ?? 0),
      'count' => (int) ($term->count ?? 0),
      'depth' => 0,
    ];
  }
}
