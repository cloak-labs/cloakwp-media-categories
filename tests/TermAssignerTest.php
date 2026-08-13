<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use PHPUnit\Framework\TestCase;

final class TermAssignerTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
    WpStubs::$postTypes = [
      1 => 'attachment',
      2 => 'attachment',
      3 => 'post',
    ];
    WpStubs::$caps = [
      'assign_media_categories' => true,
      'edit_post:1' => true,
      'edit_post:2' => false,
    ];
  }

  public function testBulkAssignAppendsOnlyEditableAttachments(): void
  {
    $assigner = new TermAssigner(Config::defaults());
    $result = $assigner->bulkAssign([1, 2, 3, 0], [10, 11], true);

    $this->assertSame([1], $result['updated']);
    $this->assertContains(2, $result['skipped']);
    $this->assertContains(3, $result['skipped']);
    $this->assertContains(0, $result['skipped']);
    $this->assertCount(1, WpStubs::$setCalls);
    $this->assertTrue(WpStubs::$setCalls[0]['append']);
    $this->assertSame([10, 11], WpStubs::$setCalls[0]['terms']);
  }

  public function testBulkAssignRemove(): void
  {
    $assigner = new TermAssigner(Config::defaults());
    $result = $assigner->bulkAssign([1], [10], false);

    $this->assertSame([1], $result['updated']);
    $this->assertCount(1, WpStubs::$removeCalls);
    $this->assertSame([10], WpStubs::$removeCalls[0]['terms']);
  }

  public function testBulkClearUsesSetWithEmptyTerms(): void
  {
    $assigner = new TermAssigner(Config::defaults());
    $result = $assigner->bulkAssign([1], [], false);

    $this->assertSame([1], $result['updated']);
    $this->assertCount(1, WpStubs::$setCalls);
    $this->assertSame([], WpStubs::$setCalls[0]['terms']);
    $this->assertFalse(WpStubs::$setCalls[0]['append']);
  }

  public function testBulkReplaceSetsTermsOnEditableAttachments(): void
  {
    $assigner = new TermAssigner(Config::defaults());
    $result = $assigner->bulkReplace([1, 2], [10, 11]);

    $this->assertSame([1], $result['updated']);
    $this->assertContains(2, $result['skipped']);
    $this->assertCount(1, WpStubs::$setCalls);
    $this->assertSame([10, 11], WpStubs::$setCalls[0]['terms']);
    $this->assertFalse(WpStubs::$setCalls[0]['append']);
  }
}
