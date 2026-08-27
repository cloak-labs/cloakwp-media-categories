<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Plugin\Assets;
use PHPUnit\Framework\TestCase;

final class AssetsEnqueueTest extends TestCase
{
  protected function setUp(): void
  {
    WpStubs::reset();
  }

  public function testRegisterHooksAcfInputAndMediaEnqueue(): void
  {
    $assets = new Assets(Config::defaults(), '/tmp/media-categories.php');
    $assets->register();

    $hooks = array_column(WpStubs::$actions, 'hook');

    $this->assertContains('wp_enqueue_media', $hooks);
    $this->assertContains(
      'acf/input/admin_enqueue_scripts',
      $hooks,
      'ACF Image/Gallery/File pickers live on screens that never hit upload.php.',
    );
  }
}
