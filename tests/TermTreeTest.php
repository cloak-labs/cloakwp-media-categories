<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Support\TermTree;
use PHPUnit\Framework\TestCase;

final class TermTreeTest extends TestCase
{
  public function testFlattensParentThenChildrenWithDepth(): void
  {
    $flat = TermTree::flatten([
      ['id' => 2, 'name' => 'Zebra', 'parent' => 0, 'count' => 1],
      ['id' => 3, 'name' => 'Child', 'parent' => 1, 'count' => 4],
      ['id' => 1, 'name' => 'Alpha', 'parent' => 0, 'count' => 2],
      ['id' => 4, 'name' => 'Grand', 'parent' => 3, 'count' => 0],
    ]);

    $ids = array_column($flat, 'id');
    $this->assertSame([1, 3, 4, 2], $ids);
    $this->assertSame(0, $flat[0]['depth']);
    $this->assertSame(1, $flat[1]['depth']);
    $this->assertSame(2, $flat[2]['depth']);
    $this->assertSame(0, $flat[3]['depth']);
  }

  public function testPrefixRepeatsDashSpace(): void
  {
    $this->assertSame('', TermTree::prefix(0));
    $this->assertSame('- ', TermTree::prefix(1));
    $this->assertSame('- - ', TermTree::prefix(2));
  }

  public function testOrphanedChildrenBecomeRoots(): void
  {
    $flat = TermTree::flatten([
      ['id' => 9, 'name' => 'Lost', 'parent' => 99, 'count' => 1],
    ]);

    $this->assertCount(1, $flat);
    $this->assertSame(0, $flat[0]['depth']);
    $this->assertSame(9, $flat[0]['id']);
  }

  public function testDecodesHtmlEntitiesInNames(): void
  {
    $flat = TermTree::flatten([
      ['id' => 1, 'name' => 'Pools &amp; Spas', 'parent' => 0, 'count' => 0],
      ['id' => 2, 'name' => 'Food & Drink', 'parent' => 0, 'count' => 0],
    ]);

    $byId = [];
    foreach ($flat as $row) {
      $byId[$row['id']] = $row['name'];
    }

    $this->assertSame('Pools & Spas', $byId[1]);
    $this->assertSame('Food & Drink', $byId[2]);
  }

  public function testFromTaxonomyFlattensGetTerms(): void
  {
    WpStubs::reset();
    WpStubs::$terms = [
      (object) [
        'term_id' => 1,
        'name' => 'Alpha',
        'slug' => 'alpha',
        'parent' => 0,
        'count' => 2,
      ],
      (object) [
        'term_id' => 2,
        'name' => 'Child',
        'slug' => 'child',
        'parent' => 1,
        'count' => 0,
      ],
    ];

    $flat = TermTree::fromTaxonomy('category_media');

    $this->assertSame([1, 2], array_column($flat, 'id'));
    $this->assertSame(0, $flat[0]['depth']);
    $this->assertSame(1, $flat[1]['depth']);
  }

  public function testFromTaxonomyReturnsEmptyForBlankSlug(): void
  {
    $this->assertSame([], TermTree::fromTaxonomy(''));
  }
}
