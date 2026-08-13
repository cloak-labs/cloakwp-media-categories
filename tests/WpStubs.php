<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

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

  public static function reset(): void
  {
    self::$postTypes = [];
    self::$caps = [];
    self::$setCalls = [];
    self::$removeCalls = [];
  }
}
