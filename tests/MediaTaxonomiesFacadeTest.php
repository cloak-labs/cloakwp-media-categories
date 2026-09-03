<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Tests;

use CloakWP\MediaTaxonomies\MediaTaxonomies;
use CloakWP\MediaTaxonomies\MediaTaxonomy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MediaTaxonomiesFacadeTest extends TestCase
{
  public function testFluentConfigBuildsExpectedValues(): void
  {
    $instance = MediaTaxonomies::make()
      ->manageRoles(['administrator', 'editor'])
      ->assignRoles(['author'])
      ->taxonomies([
        MediaTaxonomy::make('media_cat')
          ->restBase('media-cats')
          ->labels('Cat', 'Cats')
          ->hierarchical(false)
          ->rewrite(false)
          ->showInRest(false)
          ->defaultTerm('general'),
      ]);

    $config = $instance->config();
    $taxonomy = $config->taxonomies[0];

    $this->assertSame('media_cat', $taxonomy->slug);
    $this->assertSame('media-cats', $taxonomy->restBase);
    $this->assertSame('Cat', $taxonomy->singularLabel);
    $this->assertSame('Cats', $taxonomy->pluralLabel);
    $this->assertFalse($taxonomy->hierarchical);
    $this->assertFalse($taxonomy->rewrite);
    $this->assertFalse($taxonomy->showInRest);
    $this->assertSame(['administrator', 'editor'], $config->manageRoles);
    $this->assertSame(['author'], $config->assignRoles);
    $this->assertSame('general', $taxonomy->defaultTerm);
    $this->assertFalse(MediaTaxonomies::booted());
  }

  public function testAllReturnsDefaultBeforeBoot(): void
  {
    $all = MediaTaxonomies::all();

    $this->assertCount(1, $all);
    $this->assertSame('category_media', $all[0]->slug);
  }

  public function testTaxonomiesRejectsEmptyList(): void
  {
    $this->expectException(InvalidArgumentException::class);
    MediaTaxonomies::make()->taxonomies([]);
  }
}
