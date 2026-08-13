<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Admin;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;

/**
 * Media Library grid / modal: taxonomy query filtering for AJAX attachment queries.
 * (Toolbar UI is injected by resources/js/media-library.js)
 */
final class Grid
{
  public function __construct(
    private readonly Config $config,
    private readonly AttachmentQuery $attachmentQuery,
  ) {
  }

  public function register(): void
  {
    add_filter('ajax_query_attachments_args', [$this, 'filterAjaxQuery']);
  }

  /**
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  public function filterAjaxQuery(array $args): array
  {
    $value = null;

    if (isset($_REQUEST[$this->config->slug])) {
      $value = sanitize_text_field(wp_unslash((string) $_REQUEST[$this->config->slug]));
    } elseif (isset($args[$this->config->slug])) {
      $value = $args[$this->config->slug];
    }

    if ($value === null || $value === '') {
      return $args;
    }

    // Avoid leaking the raw query var into WP_Query (query_var expects a slug).
    unset($args[$this->config->slug]);

    return $this->attachmentQuery->applyToArgs($args, $value);
  }
}
