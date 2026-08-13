<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_readable($autoload)) {
  spl_autoload_register(static function (string $class): void {
    $prefix = 'CloakWP\\MediaCategories\\';
    if (!str_starts_with($class, $prefix)) {
      return;
    }
    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
      require_once $path;
    }
  });
} else {
  require $autoload;
}

require_once __DIR__ . '/WpStubs.php';

if (!function_exists('sanitize_key')) {
  function sanitize_key($key): string
  {
    $key = strtolower((string) $key);
    return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
  }
}

if (!function_exists('add_action')) {
  function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
  {
  }
}

if (!function_exists('add_filter')) {
  function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
  {
  }
}

if (!function_exists('apply_filters')) {
  function apply_filters($hook, $value, ...$args)
  {
    return $value;
  }
}

if (!function_exists('__')) {
  function __(string $text, string $domain = 'default'): string
  {
    return $text;
  }
}

if (!function_exists('get_post_type')) {
  function get_post_type($id)
  {
    return \CloakWP\MediaCategories\Tests\WpStubs::$postTypes[(int) $id] ?? false;
  }
}

if (!function_exists('current_user_can')) {
  function current_user_can($cap, $id = null): bool
  {
    $key = $id !== null ? $cap . ':' . $id : $cap;
    return \CloakWP\MediaCategories\Tests\WpStubs::$caps[$key] ?? false;
  }
}

if (!function_exists('wp_set_object_terms')) {
  function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false): array
  {
    \CloakWP\MediaCategories\Tests\WpStubs::$setCalls[] = [
      'id' => (int) $object_id,
      'terms' => array_map('intval', (array) $terms),
      'taxonomy' => (string) $taxonomy,
      'append' => (bool) $append,
    ];
    return ['term_ids' => (array) $terms];
  }
}

if (!function_exists('wp_remove_object_terms')) {
  function wp_remove_object_terms($object_id, $terms, $taxonomy): bool
  {
    \CloakWP\MediaCategories\Tests\WpStubs::$removeCalls[] = [
      'id' => (int) $object_id,
      'terms' => array_map('intval', (array) $terms),
      'taxonomy' => (string) $taxonomy,
    ];
    return true;
  }
}

if (!function_exists('is_wp_error')) {
  function is_wp_error($thing): bool
  {
    return $thing instanceof WP_Error;
  }
}

if (!class_exists('WP_Error')) {
  class WP_Error
  {
    public function get_error_message(): string
    {
      return 'error';
    }
  }
}
