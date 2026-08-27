<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use PHPUnit\Framework\TestCase;

final class AttachmentQueryTest extends TestCase
{
  private AttachmentQuery $query;

  protected function setUp(): void
  {
    $this->query = new AttachmentQuery(Config::defaults());
  }

  public function testEmptyValueLeavesArgsUntouched(): void
  {
    $args = ['post_type' => 'attachment'];
    $this->assertSame($args, $this->query->applyToArgs($args, null));
    $this->assertSame($args, $this->query->applyToArgs($args, ''));
  }

  public function testTermIdBuildsTaxQuery(): void
  {
    $args = $this->query->applyToArgs([], 12);

    $this->assertSame([
      [
        'taxonomy' => 'category_media',
        'field' => 'term_id',
        'terms' => [12],
        'operator' => 'IN',
        'include_children' => true,
      ],
    ], $args['tax_query']);
  }

  public function testUncategorizedUsesNotExists(): void
  {
    $args = $this->query->applyToArgs([], Config::UNCATEGORIZED_QUERY);

    $this->assertSame([
      [
        'taxonomy' => 'category_media',
        'operator' => 'NOT EXISTS',
      ],
    ], $args['tax_query']);
  }

  public function testMergesWithExistingTaxQuery(): void
  {
    $args = [
      'tax_query' => [
        [
          'taxonomy' => 'post_tag',
          'field' => 'slug',
          'terms' => ['news'],
        ],
      ],
    ];

    $result = $this->query->applyToArgs($args, 5);

    $this->assertSame('AND', $result['tax_query']['relation']);
    $this->assertCount(3, $result['tax_query']); // relation + 2 clauses
  }

  public function testUncategorizedClause(): void
  {
    $clause = $this->query->uncategorizedClause();
    $this->assertSame('category_media', $clause['taxonomy']);
    $this->assertSame('NOT EXISTS', $clause['operator']);
  }

  public function testMultipleTermIdsUseIn(): void
  {
    $args = $this->query->applyToArgs([], '12,15,18');

    $this->assertSame([12, 15, 18], $args['tax_query'][0]['terms']);
    $this->assertSame('IN', $args['tax_query'][0]['operator']);
  }

  public function testNotPrefixExcludesTerms(): void
  {
    $args = $this->query->applyToArgs([], 'not:12,15');

    $this->assertSame([12, 15], $args['tax_query'][0]['terms']);
    $this->assertSame('NOT IN', $args['tax_query'][0]['operator']);
  }

  public function testNotUncategorizedUsesExists(): void
  {
    $args = $this->query->applyToArgs([], 'not:uncategorized');

    $this->assertSame([
      [
        'taxonomy' => 'category_media',
        'operator' => 'EXISTS',
      ],
    ], $args['tax_query']);
  }

  public function testParseAndEncodeRoundTrip(): void
  {
    $this->assertSame('12,15', AttachmentQuery::encode('in', [12, 15]));
    $this->assertSame('not:12,15', AttachmentQuery::encode('not', [12, 15]));
    $this->assertSame('uncategorized', AttachmentQuery::encode('in', [], true));
    $this->assertSame('not:uncategorized', AttachmentQuery::encode('not', [], true));
    $this->assertSame('', AttachmentQuery::encode('in', []));

    $parsed = AttachmentQuery::parse('not:4,8');
    $this->assertSame(AttachmentQuery::MODE_NOT, $parsed['mode']);
    $this->assertSame([4, 8], $parsed['termIds']);
    $this->assertFalse($parsed['empty']);
  }
}
