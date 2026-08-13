<?php

/**
 * Plugin Name:       Media Categories
 * Plugin URI:        https://github.com/cloak-labs/cloakwp-media-categories
 * Description:       Organize media with categories. Feels like WordPress core — configured in PHP, no settings UI.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Cloak Labs
 * Author URI:        https://github.com/cloak-labs
 * License:           LGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/lgpl-3.0.html
 * Text Domain:       media-categories
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

define('CLOAKWP_MEDIA_CATEGORIES_FILE', __FILE__);
define('CLOAKWP_MEDIA_CATEGORIES_DIR', __DIR__);
define('CLOAKWP_MEDIA_CATEGORIES_VERSION', '0.1.0');

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
} elseif (!class_exists(\CloakWP\MediaCategories\MediaCategories::class, false)) {
  spl_autoload_register(static function (string $class): void {
    $prefix = 'CloakWP\\MediaCategories\\';
    if (!str_starts_with($class, $prefix)) {
      return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($path)) {
      require_once $path;
    }
  });
}

use CloakWP\MediaCategories\MediaCategories;

/**
 * Deferred default boot: theme/mu-plugin fluent config can call register()
 * before init. If nothing has booted by init priority 1, start with defaults.
 */
add_action('init', static function (): void {
  if (!MediaCategories::booted()) {
    MediaCategories::make()->register();
  }
}, 1);
