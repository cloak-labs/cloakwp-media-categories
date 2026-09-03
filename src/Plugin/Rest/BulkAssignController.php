<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin\Rest;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\TermAssigner;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint for bulk add/remove of media taxonomy terms.
 */
final class BulkAssignController
{
  /**
   * @param array<string, TermAssigner> $termAssigners
   */
  public function __construct(
    private readonly Config $config,
    private readonly array $termAssigners,
  ) {
  }

  public function register(): void
  {
    add_action('rest_api_init', [$this, 'registerRoutes']);
  }

  public function registerRoutes(): void
  {
    register_rest_route('media-taxonomies/v1', '/bulk-assign', [
      'methods' => 'POST',
      'callback' => [$this, 'handle'],
      'permission_callback' => [$this, 'canAssign'],
      'args' => [
        'attachment_ids' => [
          'required' => true,
          'type' => 'array',
          'items' => ['type' => 'integer'],
        ],
        'taxonomy' => [
          'required' => true,
          'type' => 'string',
        ],
        'term_ids' => [
          'required' => true,
          'type' => 'array',
          'items' => ['type' => 'integer'],
        ],
        'append' => [
          'required' => false,
          'type' => 'boolean',
          'default' => false,
        ],
        'replace' => [
          'required' => false,
          'type' => 'boolean',
          'default' => false,
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
    $slug = sanitize_key((string) $request->get_param('taxonomy'));
    $taxonomy = $this->config->taxonomy($slug);
    $assigner = $this->termAssigners[$slug] ?? null;
    if ($taxonomy === null || $assigner === null) {
      return new WP_Error(
        'media_taxonomies_invalid_taxonomy',
        sprintf('Unknown media taxonomy: %s', $slug),
        ['status' => 400],
      );
    }

    $attachmentIds = array_map('intval', (array) $request->get_param('attachment_ids'));
    $termIds = array_map('intval', (array) $request->get_param('term_ids'));
    $append = (bool) $request->get_param('append');
    $replace = (bool) $request->get_param('replace');

    if ($append && !$replace && $termIds === []) {
      return new WP_Error(
        'media_taxonomies_empty_terms',
        sprintf('Select at least one %s to add.', strtolower($taxonomy->singularLabel)),
        ['status' => 400],
      );
    }

    foreach ($termIds as $termId) {
      $term = get_term($termId, $taxonomy->slug);
      if (!$term || is_wp_error($term)) {
        return new WP_Error(
          'media_taxonomies_invalid_term',
          sprintf('Invalid %s term: %d', strtolower($taxonomy->singularLabel), $termId),
          ['status' => 400],
        );
      }
    }

    $result = $replace
      ? $assigner->bulkReplace($attachmentIds, $termIds)
      : $assigner->bulkAssign($attachmentIds, $termIds, $append);

    return new WP_REST_Response($result, 200);
  }
}
