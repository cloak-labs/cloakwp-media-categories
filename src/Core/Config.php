<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Core;

/**
 * Immutable plugin configuration: shared caps plus one or more taxonomies.
 */
final class Config
{
  public const MANAGE_CAP = 'manage_media_categories';
  public const ASSIGN_CAP = 'assign_media_categories';
  public const UNCATEGORIZED_QUERY = 'uncategorized';

  /**
   * @param list<string> $manageRoles
   * @param list<string> $assignRoles
   * @param list<TaxonomyConfig> $taxonomies
   */
  public function __construct(
    public readonly array $manageRoles,
    public readonly array $assignRoles,
    public readonly array $taxonomies,
  ) {
  }

  public static function defaults(): self
  {
    return new self(
      manageRoles: ['administrator'],
      assignRoles: ['administrator', 'editor', 'author'],
      taxonomies: [TaxonomyConfig::mediaCategories()],
    );
  }

  /**
   * @param list<string> $roles
   */
  public function withManageRoles(array $roles): self
  {
    return new self(
      manageRoles: array_values($roles),
      assignRoles: $this->assignRoles,
      taxonomies: $this->taxonomies,
    );
  }

  /**
   * @param list<string> $roles
   */
  public function withAssignRoles(array $roles): self
  {
    return new self(
      manageRoles: $this->manageRoles,
      assignRoles: array_values($roles),
      taxonomies: $this->taxonomies,
    );
  }

  /**
   * @param list<TaxonomyConfig> $taxonomies
   */
  public function withTaxonomies(array $taxonomies): self
  {
    return new self(
      manageRoles: $this->manageRoles,
      assignRoles: $this->assignRoles,
      taxonomies: array_values($taxonomies),
    );
  }

  public function taxonomy(string $slug): ?TaxonomyConfig
  {
    foreach ($this->taxonomies as $taxonomy) {
      if ($taxonomy->slug === $slug) {
        return $taxonomy;
      }
    }

    return null;
  }

  /**
   * @return list<string>
   */
  public function slugs(): array
  {
    return array_map(static fn(TaxonomyConfig $taxonomy): string => $taxonomy->slug, $this->taxonomies);
  }
}
