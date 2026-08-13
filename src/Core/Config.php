<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core;

/**
 * Immutable configuration for media categories.
 */
final class Config
{
  public const MANAGE_CAP = 'manage_media_categories';
  public const ASSIGN_CAP = 'assign_media_categories';
  public const UNCATEGORIZED_QUERY = 'uncategorized';

  /**
   * @param list<string> $manageRoles
   * @param list<string> $assignRoles
   * @param array<string, string> $labels Extra / override taxonomy labels
   */
  public function __construct(
    public readonly string $slug,
    public readonly string $restBase,
    public readonly string $singularLabel,
    public readonly string $pluralLabel,
    public readonly bool $hierarchical,
    public readonly string|false $rewrite,
    public readonly bool $showInRest,
    public readonly array $manageRoles,
    public readonly array $assignRoles,
    public readonly ?string $defaultTerm,
    public readonly array $labels = [],
  ) {
  }

  public static function defaults(): self
  {
    return new self(
      slug: 'category_media',
      restBase: 'media-categories',
      singularLabel: 'Media Category',
      pluralLabel: 'Media Categories',
      hierarchical: true,
      rewrite: 'media-category',
      showInRest: true,
      manageRoles: ['administrator'],
      assignRoles: ['administrator', 'editor', 'author'],
      defaultTerm: null,
      labels: [],
    );
  }

  /**
   * @param list<string> $roles
   */
  public function withManageRoles(array $roles): self
  {
    return $this->with(manageRoles: array_values($roles));
  }

  /**
   * @param list<string> $roles
   */
  public function withAssignRoles(array $roles): self
  {
    return $this->with(assignRoles: array_values($roles));
  }

  public function withSlug(string $slug): self
  {
    return $this->with(slug: sanitize_key($slug));
  }

  public function withRestBase(string $restBase): self
  {
    return $this->with(restBase: sanitize_key($restBase));
  }

  public function withLabels(string $singular, string $plural): self
  {
    return $this->with(singularLabel: $singular, pluralLabel: $plural);
  }

  public function withHierarchical(bool $hierarchical): self
  {
    return $this->with(hierarchical: $hierarchical);
  }

  public function withRewrite(string|false $rewrite): self
  {
    return $this->with(rewrite: $rewrite);
  }

  public function withShowInRest(bool $showInRest): self
  {
    return $this->with(showInRest: $showInRest);
  }

  public function withDefaultTerm(?string $defaultTerm): self
  {
    return $this->with(defaultTerm: $defaultTerm, defaultTermSet: true);
  }

  /**
   * @param array<string, string> $labels
   */
  public function withExtraLabels(array $labels): self
  {
    return $this->with(labels: array_merge($this->labels, $labels));
  }

  /**
   * Full taxonomy labels array for register_taxonomy().
   *
   * @return array<string, string>
   */
  public function taxonomyLabels(): array
  {
    $singular = $this->singularLabel;
    $plural = $this->pluralLabel;

    $defaults = [
      'name' => $plural,
      'singular_name' => $singular,
      'menu_name' => $plural,
      'all_items' => sprintf('All %s', strtolower($plural)),
      'edit_item' => sprintf('Edit %s', $singular),
      'view_item' => sprintf('View %s', $singular),
      'update_item' => sprintf('Update %s', $singular),
      'add_new_item' => sprintf('Add New %s', $singular),
      'new_item_name' => sprintf('New %s Name', $singular),
      'parent_item' => sprintf('Parent %s', $singular),
      'parent_item_colon' => sprintf('Parent %s:', $singular),
      'search_items' => sprintf('Search %s', $plural),
      'popular_items' => sprintf('Popular %s', $plural),
      'separate_items_with_commas' => sprintf('Separate %s with commas', strtolower($plural)),
      'add_or_remove_items' => sprintf('Add or remove %s', strtolower($plural)),
      'choose_from_most_used' => sprintf('Choose from the most used %s', strtolower($plural)),
      'not_found' => sprintf('No %s found.', strtolower($plural)),
      'no_terms' => sprintf('No %s', strtolower($plural)),
      'filter_by_item' => sprintf('Filter by %s', $singular),
      'items_list_navigation' => sprintf('%s list navigation', $plural),
      'items_list' => sprintf('%s list', $plural),
      'most_used' => 'Most Used',
      'back_to_items' => sprintf('← Back to %s', $plural),
    ];

    return array_merge($defaults, $this->labels);
  }

  private function with(
    ?string $slug = null,
    ?string $restBase = null,
    ?string $singularLabel = null,
    ?string $pluralLabel = null,
    ?bool $hierarchical = null,
    string|false|null $rewrite = null,
    ?bool $showInRest = null,
    ?array $manageRoles = null,
    ?array $assignRoles = null,
    ?string $defaultTerm = null,
    bool $defaultTermSet = false,
    ?array $labels = null,
  ): self {
    return new self(
      slug: $slug ?? $this->slug,
      restBase: $restBase ?? $this->restBase,
      singularLabel: $singularLabel ?? $this->singularLabel,
      pluralLabel: $pluralLabel ?? $this->pluralLabel,
      hierarchical: $hierarchical ?? $this->hierarchical,
      rewrite: $rewrite === null ? $this->rewrite : $rewrite,
      showInRest: $showInRest ?? $this->showInRest,
      manageRoles: $manageRoles ?? $this->manageRoles,
      assignRoles: $assignRoles ?? $this->assignRoles,
      defaultTerm: $defaultTermSet ? $defaultTerm : ($defaultTerm !== null ? $defaultTerm : $this->defaultTerm),
      labels: $labels ?? $this->labels,
    );
  }
}
