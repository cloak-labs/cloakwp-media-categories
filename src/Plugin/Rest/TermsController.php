<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin\Rest;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\TermTree;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint that returns flattened term trees for registered media taxonomies.
 */
final class TermsController
{
  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    add_action('rest_api_init', [$this, 'registerRoutes']);
  }

  public function registerRoutes(): void
  {
    register_rest_route('media-taxonomies/v1', '/terms', [
      'methods' => 'GET',
      'callback' => [$this, 'handle'],
      'permission_callback' => [$this, 'canView'],
    ]);
  }

  public function canView(): bool
  {
    return current_user_can(Config::ASSIGN_CAP);
  }

  public function handle(WP_REST_Request $request): WP_REST_Response
  {
    $only = sanitize_key((string) $request->get_param('taxonomy'));
    $payload = [];

    foreach ($this->config->taxonomies as $taxonomy) {
      if ($only !== '' && $taxonomy->slug !== $only) {
        continue;
      }
      $payload[] = [
        'slug' => $taxonomy->slug,
        'terms' => TermTree::fromTaxonomy($taxonomy->slug),
      ];
    }

    return new WP_REST_Response($payload, 200);
  }
}
