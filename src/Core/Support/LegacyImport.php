<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core\Support;

/**
 * Detects WP Media Category Management (MCM) taxonomies to copy from.
 *
 * MCM stores assignments in core term tables. When it was configured to use
 * our default slug (`category_media`), no copy is needed. When it used
 * `category` or another attachment taxonomy, those relationships must be
 * copied onto our taxonomy — the terms themselves survive plugin deactivation,
 * but they stay on the old taxonomy slug.
 */
final class LegacyImport
{
  public const MCM_OPTION_KEY = 'wp-media-category-management-options';

  /**
   * Taxonomies MCM (and similar plugins) may have used for media.
   *
   * @var list<string>
   */
  public const KNOWN_MEDIA_TAXONOMIES = [
    'category_media',
    'media_category',
    'mcm-category',
    'mcm_category',
  ];

  /**
   * @param mixed $mcmOptions Serialized MCM options array, or null
   * @return list<string> Taxonomy slugs to copy from (never includes $destinationSlug)
   */
  public static function sourceTaxonomies(mixed $mcmOptions, string $destinationSlug): array
  {
    $sources = [];

    if (is_array($mcmOptions)) {
      $configured = sanitize_key((string) ($mcmOptions['wp_mcm_media_taxonomy_to_use'] ?? ''));
      if ($configured !== '' && $configured !== $destinationSlug) {
        $sources[] = $configured;
      }

      $usePost = $mcmOptions['wp_mcm_use_post_taxonomy'] ?? 0;
      if (self::isTruthy($usePost) && $destinationSlug !== 'category') {
        $sources[] = 'category';
      }
    }

    return array_values(array_unique($sources));
  }

  /**
   * Extra taxonomies to inspect even when MCM options are missing/stale.
   * Callers should skip any slug with no attachment relationships.
   *
   * @return list<string>
   */
  public static function fallbackTaxonomies(string $destinationSlug): array
  {
    $fallbacks = array_merge(self::KNOWN_MEDIA_TAXONOMIES, ['category']);

    return array_values(array_filter(
      array_unique($fallbacks),
      static fn(string $slug): bool => $slug !== $destinationSlug,
    ));
  }

  /**
   * Match a source term to an existing destination term by slug, then name.
   *
   * @param object{slug: string, name: string} $sourceTerm
   * @param list<object{slug: string, name: string}> $destinationTerms
   */
  public static function matchTerm(object $sourceTerm, array $destinationTerms): ?object
  {
    $slug = (string) ($sourceTerm->slug ?? '');
    $name = (string) ($sourceTerm->name ?? '');

    if ($slug !== '') {
      foreach ($destinationTerms as $term) {
        if ((string) ($term->slug ?? '') === $slug) {
          return $term;
        }
      }
    }

    if ($name !== '') {
      foreach ($destinationTerms as $term) {
        if (strcasecmp((string) ($term->name ?? ''), $name) === 0) {
          return $term;
        }
      }
    }

    return null;
  }

  private static function isTruthy(mixed $value): bool
  {
    return $value === true || $value === 1 || $value === '1';
  }
}
