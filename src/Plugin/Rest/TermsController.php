<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Rest;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\TermTree;
use WP_REST_Response;

/**
 * REST endpoint that returns the hierarchical media-category checklist.
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
    register_rest_route('media-categories/v1', '/terms', [
      'methods' => 'GET',
      'callback' => [$this, 'handle'],
      'permission_callback' => [$this, 'canView'],
    ]);
  }

  public function canView(): bool
  {
    return current_user_can(Config::ASSIGN_CAP);
  }

  public function handle(): WP_REST_Response
  {
    return new WP_REST_Response(TermTree::fromTaxonomy($this->config->slug), 200);
  }
}
