<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Admin;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use CloakWP\MediaCategories\Plugin\Assets;

/**
 * Media Library grid / modal: taxonomy filter + bulk-select assign UI (JS).
 */
final class Grid
{
  public function __construct(
    private readonly Config $config,
    private readonly AttachmentQuery $attachmentQuery,
    private readonly Assets $assets,
  ) {
  }

  public function register(): void
  {
    add_filter('ajax_query_attachments_args', [$this, 'filterAjaxQuery']);
    add_action('admin_enqueue_scripts', [$this->assets, 'enqueueOnUploadScreen']);
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
      unset($args[$this->config->slug]);
    }

    if ($value === null || $value === '') {
      return $args;
    }

    // Avoid leaking the raw query var into WP_Query.
    unset($args[$this->config->slug]);

    return $this->attachmentQuery->applyToArgs($args, $value);
  }
}
