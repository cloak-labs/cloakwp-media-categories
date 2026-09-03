<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Tests;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
  public function testDefaults(): void
  {
    $config = Config::defaults();
    $taxonomy = $config->taxonomies[0];

    $this->assertCount(1, $config->taxonomies);
    $this->assertSame('category_media', $taxonomy->slug);
    $this->assertSame('category_media', $taxonomy->restBase);
    $this->assertSame('filter_category_media', $taxonomy->listFilterArg());
    $this->assertSame('Media Category', $taxonomy->singularLabel);
    $this->assertSame('Media Categories', $taxonomy->pluralLabel);
    $this->assertTrue($taxonomy->hierarchical);
    $this->assertSame('category-media', $taxonomy->rewrite);
    $this->assertTrue($taxonomy->showInRest);
    $this->assertSame(['administrator'], $config->manageRoles);
    $this->assertSame(['administrator', 'editor', 'author'], $config->assignRoles);
    $this->assertNull($taxonomy->defaultTerm);
  }

  public function testWithersAreImmutable(): void
  {
    $base = Config::defaults();
    $next = $base
      ->withManageRoles(['administrator', 'editor'])
      ->withAssignRoles(['editor'])
      ->withTaxonomies([
        TaxonomyConfig::fromSlug('media_cat')
          ->withLabels('Tag', 'Tags')
          ->withRewrite(false)
          ->withShowInRest(false)
          ->withDefaultTerm('general'),
      ]);

    $this->assertSame('category_media', $base->taxonomies[0]->slug);
    $this->assertSame(['administrator'], $base->manageRoles);
    $this->assertSame('media_cat', $next->taxonomies[0]->slug);
    $this->assertSame(['administrator', 'editor'], $next->manageRoles);
    $this->assertSame(['editor'], $next->assignRoles);
    $this->assertSame('Tag', $next->taxonomies[0]->singularLabel);
    $this->assertSame('Tags', $next->taxonomies[0]->pluralLabel);
    $this->assertFalse($next->taxonomies[0]->rewrite);
    $this->assertFalse($next->taxonomies[0]->showInRest);
    $this->assertSame('general', $next->taxonomies[0]->defaultTerm);
  }

  public function testDefaultTermCanBeCleared(): void
  {
    $taxonomy = TaxonomyConfig::mediaCategories()
      ->withDefaultTerm('hero')
      ->withDefaultTerm(null);
    $this->assertNull($taxonomy->defaultTerm);
  }

  public function testTaxonomyLabelsUseConfiguredNames(): void
  {
    $labels = TaxonomyConfig::mediaCategories()->taxonomyLabels();

    $this->assertSame('Media Categories', $labels['name']);
    $this->assertSame('Media Category', $labels['singular_name']);
    $this->assertSame('All media categories', $labels['all_items']);
    $this->assertSame('Filter by Media Category', $labels['filter_by_item']);
    $this->assertSame('Add New Media Category', $labels['add_new_item']);
    $this->assertStringNotContainsString('MCM', $labels['name']);
  }

  public function testLookupBySlug(): void
  {
    $config = Config::defaults()->withTaxonomies([
      TaxonomyConfig::fromSlug('landscape_type')->withLabels('Landscape Type', 'Landscape Types'),
      TaxonomyConfig::fromSlug('photo_type')->withLabels('Photo Type', 'Photo Types'),
    ]);

    $this->assertSame(['landscape_type', 'photo_type'], $config->slugs());
    $this->assertSame('Landscape Type', $config->taxonomy('landscape_type')?->singularLabel);
    $this->assertNull($config->taxonomy('category_media'));
  }

  public function testCapabilityConstants(): void
  {
    $this->assertSame('manage_media_categories', Config::MANAGE_CAP);
    $this->assertSame('assign_media_categories', Config::ASSIGN_CAP);
    $this->assertSame('uncategorized', Config::UNCATEGORIZED_QUERY);
  }
}
