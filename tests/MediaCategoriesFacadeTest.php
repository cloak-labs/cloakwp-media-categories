<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\MediaCategories;
use PHPUnit\Framework\TestCase;

final class MediaCategoriesFacadeTest extends TestCase
{
  public function testFluentConfigBuildsExpectedValues(): void
  {
    $instance = MediaCategories::make()
      ->slug('media_cat')
      ->restBase('media-cats')
      ->labels('Cat', 'Cats')
      ->hierarchical(false)
      ->rewrite(false)
      ->showInRest(false)
      ->manageRoles(['administrator', 'editor'])
      ->assignRoles(['author'])
      ->defaultTerm('general');

    $config = $instance->config();

    $this->assertSame('media_cat', $config->slug);
    $this->assertSame('media-cats', $config->restBase);
    $this->assertSame('Cat', $config->singularLabel);
    $this->assertSame('Cats', $config->pluralLabel);
    $this->assertFalse($config->hierarchical);
    $this->assertFalse($config->rewrite);
    $this->assertFalse($config->showInRest);
    $this->assertSame(['administrator', 'editor'], $config->manageRoles);
    $this->assertSame(['author'], $config->assignRoles);
    $this->assertSame('general', $config->defaultTerm);
    $this->assertFalse(MediaCategories::booted());
  }
}
