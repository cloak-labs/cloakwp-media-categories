<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core\Taxonomy;

use CloakWP\MediaCategories\Core\Config;
use WP_Roles;

/**
 * Maps manage/assign capabilities onto WordPress roles.
 */
final class Capabilities
{
  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    $this->sync();
  }

  /**
   * Ensure configured roles have the correct caps.
   * Does not strip caps from other roles (safe for custom role managers).
   */
  public function sync(): void
  {
    $roles = wp_roles();
    if (!$roles instanceof WP_Roles) {
      return;
    }

    foreach ($this->config->manageRoles as $roleName) {
      $role = $roles->get_role($roleName);
      if ($role) {
        $role->add_cap(Config::MANAGE_CAP);
      }
    }

    foreach ($this->config->assignRoles as $roleName) {
      $role = $roles->get_role($roleName);
      if ($role) {
        $role->add_cap(Config::ASSIGN_CAP);
      }
    }
  }
}
