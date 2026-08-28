<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Plugin\Admin\ListTable;
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
    $config = Config::defaults();
    $this->table = new ListTable($config);
  }

  public function testRenderFilterOutputsInMediaLibraryBar(): void
  {
    ob_start();
    $this->table->renderFilter('attachment', 'bar');
    $html = (string) ob_get_clean();

    $this->assertStringContainsString('id="media-categories-filter"', $html);
    $this->assertStringContainsString('name="media_category"', $html);
  }

  public function testRenderFilterSkipsTopAndBottomTablenav(): void
  {
    ob_start();
    $this->table->renderFilter('attachment', 'top');
    $this->table->renderFilter('attachment', 'bottom');
    $html = (string) ob_get_clean();

    $this->assertSame('', $html);
  }
}
