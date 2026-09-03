<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Tests;

use CloakWP\MediaTaxonomies\Core\Support\LegacyImport;
use CloakWP\MediaTaxonomies\Core\Taxonomy\Registrar;
use PHPUnit\Framework\TestCase;

final class LegacyImportTest extends TestCase
{
  public function testSameSlugDoesNotProduceASource(): void
  {
    $sources = LegacyImport::sourceTaxonomies([
      'wp_mcm_media_taxonomy_to_use' => 'category_media',
      'wp_mcm_use_post_taxonomy' => 0,
    ], 'category_media');

    $this->assertSame([], $sources);
  }

  public function testCopiesFromMcmConfiguredTaxonomy(): void
  {
    $sources = LegacyImport::sourceTaxonomies([
      'wp_mcm_media_taxonomy_to_use' => 'category',
    ], 'category_media');

    $this->assertSame(['category'], $sources);
  }

  public function testUsePostTaxonomyAddsCategory(): void
  {
    $sources = LegacyImport::sourceTaxonomies([
      'wp_mcm_media_taxonomy_to_use' => 'category_media',
      'wp_mcm_use_post_taxonomy' => '1',
    ], 'category_media');

    $this->assertSame(['category'], $sources);
  }

  public function testMissingOptionsAreEmpty(): void
  {
    $this->assertSame([], LegacyImport::sourceTaxonomies(false, 'category_media'));
    $this->assertSame([], LegacyImport::sourceTaxonomies(null, 'category_media'));
  }

  public function testFallbackOmitsDestinationSlug(): void
  {
    $fallbacks = LegacyImport::fallbackTaxonomies('category_media');

    $this->assertNotContains('category_media', $fallbacks);
    $this->assertContains('category', $fallbacks);
    $this->assertContains('media_category', $fallbacks);
  }

  public function testMatchTermPrefersSlugThenName(): void
  {
    $dest = [
      (object) ['term_id' => 1, 'slug' => 'team', 'name' => 'Team'],
      (object) ['term_id' => 2, 'slug' => 'pools', 'name' => 'Pools'],
    ];

    $bySlug = LegacyImport::matchTerm((object) ['slug' => 'team', 'name' => 'Staff'], $dest);
    $this->assertSame(1, $bySlug?->term_id);

    $byName = LegacyImport::matchTerm((object) ['slug' => 'staff', 'name' => 'Team'], $dest);
    $this->assertSame(1, $byName?->term_id);

    $this->assertNull(LegacyImport::matchTerm((object) ['slug' => 'x', 'name' => 'Y'], $dest));
  }

  public function testCountCallbackCountsUnattachedMedia(): void
  {
    $this->assertSame('_update_generic_term_count', Registrar::COUNT_CALLBACK);
  }
}
