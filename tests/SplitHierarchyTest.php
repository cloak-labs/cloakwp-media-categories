<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Tests;

use CloakWP\MediaTaxonomies\Core\Support\SplitHierarchy;
use PHPUnit\Framework\TestCase;

/**
 * Fixture mirrors the live hyland02 `category_media` tree (blog 33).
 * Term IDs match staging so Inspiration exclude IDs stay valid after the move.
 */
final class SplitHierarchyTest extends TestCase
{
  public function testPlansHyland02SplitWithoutRecreatingTerms(): void
  {
    $plan = SplitHierarchy::plan(self::hyland02Tree());

    $this->assertSame([], $plan['unmapped']);
    $this->assertSame(
      [
        ['name' => 'Residential', 'slug' => 'residential', 'taxonomy' => 'client_type'],
      ],
      $plan['creates'],
    );

    $deletes = array_column($plan['deletes'], 'slug');
    sort($deletes);
    $this->assertSame(['landscaping', 'outdoor-living'], $deletes);
    $this->assertSame([36, 18], array_column($plan['deletes'], 'term_id'));

    $bySlug = [];
    foreach ($plan['moves'] as $move) {
      $bySlug[$move['slug']] = $move;
    }

    $this->assertSame('landscape_type', $bySlug['hardscapes']['taxonomy']);
    $this->assertSame(0, $bySlug['hardscapes']['parent']);
    $this->assertSame('landscape_type', $bySlug['driveways']['taxonomy']);
    $this->assertSame($bySlug['hardscapes']['term_id'], $bySlug['driveways']['parent']);
    $this->assertSame('landscape_type', $bySlug['softscapes']['taxonomy']);
    $this->assertSame(0, $bySlug['softscapes']['parent']);
    $this->assertSame($bySlug['softscapes']['term_id'], $bySlug['planting']['parent']);

    $this->assertSame('outdoor_living_type', $bySlug['pools']['taxonomy']);
    $this->assertSame(0, $bySlug['pools']['parent']);
    $this->assertSame(0, $bySlug['pergolas']['parent']);

    $this->assertSame('client_type', $bySlug['customers']['taxonomy']);
    $this->assertSame(17, $bySlug['customers']['term_id']);
    $this->assertSame(0, $bySlug['commercial']['parent']);

    $this->assertSame('photo_type', $bySlug['best-work']['taxonomy']);
    $this->assertSame(21, $bySlug['best-work']['term_id']);
    $this->assertSame('photo_type', $bySlug['team']['taxonomy']);
    $this->assertSame(4, $bySlug['team']['term_id']);
    $this->assertSame(0, $bySlug['team']['parent']);
    $this->assertSame('photo_type', $bySlug['headshots']['taxonomy']);
    $this->assertSame(16, $bySlug['headshots']['term_id']);
    $this->assertSame(4, $bySlug['headshots']['parent']);

    $this->assertCount(32, $plan['moves']);
    $this->assertSame(
      array_column($plan['moves'], 'term_id'),
      array_unique(array_column($plan['moves'], 'term_id')),
    );
  }

  public function testUnmappedRootAbortsThePlan(): void
  {
    $plan = SplitHierarchy::plan([
      ['term_id' => 99, 'slug' => 'mystery', 'name' => 'Mystery', 'parent' => 0],
    ]);

    $this->assertCount(1, $plan['unmapped']);
    $this->assertSame('mystery', $plan['unmapped'][0]['slug']);
    $this->assertSame([], $plan['moves']);
  }

  /**
   * @return list<array{term_id: int, slug: string, name: string, parent: int, count: int}>
   */
  private static function hyland02Tree(): array
  {
    $landscaping = 36;
    $outdoorLiving = 18;
    $hardscapes = 40;
    $softscapes = 41;
    $team = 4;

    $row = static fn(int $id, string $slug, string $name, int $parent, int $count = 0): array => [
      'term_id' => $id,
      'slug' => $slug,
      'name' => $name,
      'parent' => $parent,
      'count' => $count,
    ];

    return [
      $row($landscaping, 'landscaping', 'Landscaping', 0),
      $row($outdoorLiving, 'outdoor-living', 'Outdoor Living', 0),
      $row($hardscapes, 'hardscapes', 'Hardscapes', $landscaping, 0),
      $row($softscapes, 'softscapes', 'Softscapes', $landscaping, 15),
      $row(42, 'driveways', 'Driveways', $hardscapes),
      $row(43, 'fencing', 'Fencing', $hardscapes),
      $row(44, 'outdoor-stairs', 'Outdoor Stairs', $hardscapes),
      $row(45, 'retaining-walls', 'Retaining Walls', $hardscapes),
      $row(46, 'stone-features', 'Stone Features', $hardscapes),
      $row(47, 'walkways-pavers', 'Walkways & Pavers', $hardscapes),
      $row(48, 'water-features', 'Water Features', $hardscapes),
      $row(49, 'lawns', 'Lawns', $softscapes),
      $row(50, 'planting', 'Planting', $softscapes),
      $row(51, 'decks-patios', 'Decks & Patios', $outdoorLiving),
      $row(52, 'firepits', 'Firepits', $outdoorLiving),
      $row(53, 'hot-tubs', 'Hot Tubs', $outdoorLiving),
      $row(54, 'leisure-areas', 'Leisure Areas', $outdoorLiving),
      $row(55, 'outdoor-kitchens', 'Outdoor Kitchens', $outdoorLiving),
      $row(56, 'outdoor-showers', 'Outdoor Showers', $outdoorLiving),
      $row(57, 'pergolas', 'Pergolas', $outdoorLiving),
      $row(58, 'play-areas', 'Play Areas', $outdoorLiving),
      $row(59, 'pools', 'Pools', $outdoorLiving),
      $row(60, 'saunas', 'Saunas', $outdoorLiving),
      $row(61, 'commercial', 'Commercial', 0),
      $row(62, 'stratas', 'Stratas', 0),
      $row(17, 'customers', 'Customers', 0, 19),
      $row(26, 'aerials', 'Aerials', 0),
      $row(29, 'before-photos', 'Before Photos', 0),
      $row(21, 'best-work', 'Best Work', 0),
      $row(22, 'close-ups', 'Close-ups', 0),
      $row(5, 'design-renderings', 'Design Renderings', 0),
      $row(28, 'in-progress', 'In-Progress', 0),
      $row($team, 'team', 'Team', 0),
      $row(16, 'headshots', 'Headshots', $team),
    ];
  }
}
