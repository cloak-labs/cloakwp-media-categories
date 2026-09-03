<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Tests;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Plugin\Admin\ListTable;
use PHPUnit\Framework\TestCase;

final class ListTableFilterTest extends TestCase
{
  private ListTable $table;

  protected function setUp(): void
  {
    WpStubs::reset();
    WpStubs::$taxonomies['category_media'] = (object) [
      'labels' => (object) [
        'filter_by_item' => 'Filter by Media Category',
        'all_items' => 'All media categories',
      ],
    ];
    $this->table = new ListTable(Config::defaults());
  }

  public function testRenderFilterOutputsPrefixedListArg(): void
  {
    ob_start();
    $this->table->renderFilter(Config::defaults()->taxonomies[0]);
    $html = (string) ob_get_clean();

    $this->assertStringContainsString('id="media-taxonomies-filter-category_media"', $html);
    $this->assertStringContainsString('name="filter_category_media"', $html);
    $this->assertStringContainsString('data-taxonomy="category_media"', $html);
    $this->assertStringContainsString('media-taxonomies-filter-select', $html);
  }

  public function testRenderFilterSkipsUnknownTaxonomy(): void
  {
    WpStubs::$taxonomies = [];

    ob_start();
    $this->table->renderFilter(Config::defaults()->taxonomies[0]);
    $html = (string) ob_get_clean();

    $this->assertSame('', $html);
  }
}
