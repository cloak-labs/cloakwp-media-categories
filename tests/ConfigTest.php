<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
  public function testDefaults(): void
  {
    $config = Config::defaults();

    $this->assertSame('category_media', $config->slug);
    $this->assertSame('media-categories', $config->restBase);
    $this->assertSame('Media Category', $config->singularLabel);
    $this->assertSame('Media Categories', $config->pluralLabel);
    $this->assertTrue($config->hierarchical);
    $this->assertSame('media-category', $config->rewrite);
    $this->assertTrue($config->showInRest);
    $this->assertSame(['administrator'], $config->manageRoles);
    $this->assertSame(['administrator', 'editor', 'author'], $config->assignRoles);
    $this->assertNull($config->defaultTerm);
  }

  public function testWithersAreImmutable(): void
  {
    $base = Config::defaults();
    $next = $base
      ->withSlug('media_cat')
      ->withManageRoles(['administrator', 'editor'])
      ->withAssignRoles(['editor'])
      ->withLabels('Tag', 'Tags')
      ->withRewrite(false)
      ->withShowInRest(false)
      ->withDefaultTerm('general');

    $this->assertSame('category_media', $base->slug);
    $this->assertSame('media_cat', $next->slug);
    $this->assertSame(['administrator', 'editor'], $next->manageRoles);
    $this->assertSame(['editor'], $next->assignRoles);
    $this->assertSame('Tag', $next->singularLabel);
    $this->assertSame('Tags', $next->pluralLabel);
    $this->assertFalse($next->rewrite);
    $this->assertFalse($next->showInRest);
    $this->assertSame('general', $next->defaultTerm);
  }

  public function testDefaultTermCanBeCleared(): void
  {
    $config = Config::defaults()->withDefaultTerm('hero')->withDefaultTerm(null);
    $this->assertNull($config->defaultTerm);
  }

  public function testTaxonomyLabelsUseConfiguredNames(): void
  {
    $labels = Config::defaults()->taxonomyLabels();

    $this->assertSame('Media Categories', $labels['name']);
    $this->assertSame('Media Category', $labels['singular_name']);
    $this->assertSame('All Media Categories', $labels['all_items']);
    $this->assertSame('Filter by Media Category', $labels['filter_by_item']);
    $this->assertSame('Add New Media Category', $labels['add_new_item']);
    $this->assertStringNotContainsString('MCM', $labels['name']);
  }

  public function testCapabilityConstants(): void
  {
    $this->assertSame('manage_media_categories', Config::MANAGE_CAP);
    $this->assertSame('assign_media_categories', Config::ASSIGN_CAP);
    $this->assertSame('uncategorized', Config::UNCATEGORIZED_QUERY);
  }
}
