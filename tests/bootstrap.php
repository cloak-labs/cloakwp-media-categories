<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($autoload)) {
  require $autoload;
} else {
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
}

$coreSrc = dirname(__DIR__, 2) . '/cloakwp-core/src';
if (is_dir($coreSrc)) {
  spl_autoload_register(static function (string $class) use ($coreSrc): void {
    $prefix = 'CloakWP\\Core\\';
    if (!str_starts_with($class, $prefix)) {
      return;
    }
    $relative = substr($class, strlen($prefix));
    $path = $coreSrc . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) {
      require_once $path;
    }
  });
}

require_once __DIR__ . '/WpStubs.php';

if (!function_exists('sanitize_text_field')) {
  function sanitize_text_field($str): string
  {
    return trim((string) $str);
  }
}

if (!function_exists('wp_unslash')) {
  function wp_unslash($value)
  {
    return $value;
  }
}

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
    \CloakWP\MediaCategories\Tests\WpStubs::$actions[] = [
      'hook' => $hook,
      'callback' => $callback,
      'priority' => $priority,
    ];
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
      'terms' => array_values((array) $terms),
      'taxonomy' => (string) $taxonomy,
      'append' => (bool) $append,
    ];
    return ['term_ids' => (array) $terms];
  }
}

if (!function_exists('get_taxonomy')) {
  function get_taxonomy($slug)
  {
    return \CloakWP\MediaCategories\Tests\WpStubs::$taxonomies[(string) $slug] ?? false;
  }
}

if (!function_exists('get_terms')) {
  function get_terms($args = [])
  {
    return \CloakWP\MediaCategories\Tests\WpStubs::$terms;
  }
}

if (!function_exists('wp_get_object_terms')) {
  function wp_get_object_terms($object_id, $taxonomy, $args = [])
  {
    $terms = \CloakWP\MediaCategories\Tests\WpStubs::$objectTerms[(int) $object_id] ?? [];
    if (($args['fields'] ?? '') === 'ids') {
      return array_map(static fn($term) => (int) $term->term_id, $terms);
    }
    return $terms;
  }
}

if (!function_exists('wp_terms_checklist')) {
  function wp_terms_checklist($object_id, $args = []): void
  {
    echo \CloakWP\MediaCategories\Tests\WpStubs::$checklistHtml;
  }
}

if (!function_exists('esc_attr')) {
  function esc_attr($text): string
  {
    return htmlspecialchars((string) $text, ENT_QUOTES);
  }
}

if (!function_exists('esc_html')) {
  function esc_html($text): string
  {
    return htmlspecialchars((string) $text, ENT_QUOTES);
  }
}

if (!function_exists('esc_html__')) {
  function esc_html__(string $text, string $domain = 'default'): string
  {
    return $text;
  }
}

if (!class_exists('WP_Post')) {
  class WP_Post
  {
    public int $ID = 0;

    public function __construct(int $id = 0)
    {
      $this->ID = $id;
    }
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
