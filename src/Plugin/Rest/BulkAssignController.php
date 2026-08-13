<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Rest;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint for bulk add/remove of media categories.
 */
final class BulkAssignController
{
  public function __construct(
    private readonly Config $config,
    private readonly TermAssigner $termAssigner,
  ) {
  }

  public function register(): void
  {
    add_action('rest_api_init', [$this, 'registerRoutes']);
  }

  public function registerRoutes(): void
  {
    register_rest_route('media-categories/v1', '/bulk-assign', [
      'methods' => 'POST',
      'callback' => [$this, 'handle'],
      'permission_callback' => [$this, 'canAssign'],
      'args' => [
        'attachment_ids' => [
          'required' => true,
          'type' => 'array',
          'items' => ['type' => 'integer'],
        ],
        'term_ids' => [
          'required' => true,
          'type' => 'array',
          'items' => ['type' => 'integer'],
        ],
        'append' => [
          'required' => true,
          'type' => 'boolean',
        ],
      ],
    ]);
  }

  public function canAssign(): bool
  {
    return current_user_can(Config::ASSIGN_CAP);
  }

  public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
  {
    $attachmentIds = array_map('intval', (array) $request->get_param('attachment_ids'));
    $termIds = array_map('intval', (array) $request->get_param('term_ids'));
    $append = (bool) $request->get_param('append');

    if ($append && $termIds === []) {
      return new WP_Error(
        'media_categories_empty_terms',
        'Select at least one category to add.',
        ['status' => 400],
      );
    }

    // Validate terms belong to our taxonomy when provided.
    foreach ($termIds as $termId) {
      $term = get_term($termId, $this->config->slug);
      if (!$term || is_wp_error($term)) {
        return new WP_Error(
          'media_categories_invalid_term',
          sprintf('Invalid media category term: %d', $termId),
          ['status' => 400],
        );
      }
    }

    $result = $this->termAssigner->bulkAssign($attachmentIds, $termIds, $append);

    return new WP_REST_Response($result, 200);
  }
}
