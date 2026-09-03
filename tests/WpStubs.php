<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Tests;

/**
 * Shared mutable stubs for WordPress functions used in unit tests.
 */
final class WpStubs
{
  /** @var array<int, string|false> */
  public static array $postTypes = [];

  /** @var array<string, bool> */
  public static array $caps = [];

  /** @var list<array{id: int, terms: list<int>, taxonomy: string, append: bool}> */
  public static array $setCalls = [];

  /** @var list<array{id: int, terms: list<int>, taxonomy: string}> */
  public static array $removeCalls = [];

  /** @var array<string, object|false> */
  public static array $taxonomies = [];

  /** @var array<int, list<object>> */
  public static array $objectTerms = [];

  public static string $checklistHtml = '';

  /** @var list<string> Term names/slugs core would insert from string (non-int) values. */
  public static array $insertedTermNames = [];

  /** @var array<string, true> Existing term slugs, for core-style wp_set_object_terms simulation. */
  public static array $existingSlugs = [];

  /** @var list<array{hook: mixed, callback: mixed, priority: mixed}> */
  public static array $actions = [];

  /** @var list<object> */
  public static array $terms = [];

  public static function reset(): void
  {
    self::$postTypes = [];
    self::$caps = [];
    self::$setCalls = [];
    self::$removeCalls = [];
    self::$taxonomies = [];
    self::$objectTerms = [];
    self::$checklistHtml = '';
    self::$insertedTermNames = [];
    self::$existingSlugs = [];
    self::$actions = [];
    self::$terms = [];
  }
}
