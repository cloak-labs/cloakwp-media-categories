<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Admin;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use WP_Query;

/**
 * Media Library list view: filter dropdown, uncategorized query, bulk action.
 */
final class ListTable
{
  public function __construct(
    private readonly Config $config,
    private readonly AttachmentQuery $attachmentQuery,
  ) {
  }

  public function register(): void
  {
    add_action('restrict_manage_posts', [$this, 'renderFilter'], 10, 2);
    add_action('pre_get_posts', [$this, 'filterQuery']);
    add_filter('bulk_actions-upload', [$this, 'registerBulkAction']);
    add_filter('handle_bulk_actions-upload', [$this, 'handleBulkAction'], 10, 3);
    add_action('admin_notices', [$this, 'bulkAdminNotice']);
  }

  public function renderFilter(string $postType, string $which): void
  {
    if ($postType !== 'attachment') {
      return;
    }

    $taxonomy = get_taxonomy($this->config->slug);
    if (!$taxonomy) {
      return;
    }

    $selected = isset($_GET[$this->config->slug])
      ? sanitize_text_field(wp_unslash((string) $_GET[$this->config->slug]))
      : '';

    $label = $taxonomy->labels->filter_by_item ?? sprintf('Filter by %s', $this->config->singularLabel);

    echo '<label class="screen-reader-text" for="media-categories-filter">' . esc_html($label) . '</label>';

    $dropdown = wp_dropdown_categories([
      'taxonomy' => $this->config->slug,
      'name' => $this->config->slug,
      'id' => 'media-categories-filter',
      'show_option_all' => $taxonomy->labels->all_items ?? sprintf('All %s', $this->config->pluralLabel),
      'hide_empty' => false,
      'hierarchical' => $this->config->hierarchical,
      'show_count' => true,
      'orderby' => 'name',
      'selected' => is_numeric($selected) ? (int) $selected : 0,
      'echo' => false,
      'value_field' => 'term_id',
    ]);

    // Inject Uncategorized option after "All".
    $uncategorizedLabel = esc_html__('Uncategorized', 'media-categories');
    $uncategorizedValue = Config::UNCATEGORIZED_QUERY;
    $selectedAttr = $selected === $uncategorizedValue ? ' selected="selected"' : '';
    $option = sprintf(
      '<option value="%s"%s>%s</option>',
      esc_attr($uncategorizedValue),
      $selectedAttr,
      $uncategorizedLabel,
    );

    $dropdown = preg_replace(
      '/(<option[^>]*value="0"[^>]*>.*?<\/option>)/',
      '$1' . $option,
      $dropdown,
      1,
    );

    echo $dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  }

  public function filterQuery(WP_Query $query): void
  {
    if (!is_admin() || !$query->is_main_query()) {
      return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'upload') {
      return;
    }

    $value = isset($_GET[$this->config->slug])
      ? sanitize_text_field(wp_unslash((string) $_GET[$this->config->slug]))
      : '';

    if ($value === '' || $value === '0') {
      return;
    }

    if ($value === Config::UNCATEGORIZED_QUERY) {
      $taxQuery = $query->get('tax_query');
      if (!is_array($taxQuery)) {
        $taxQuery = [];
      }
      $taxQuery[] = $this->attachmentQuery->uncategorizedClause();
      $query->set('tax_query', $taxQuery);
      return;
    }

    if (is_numeric($value)) {
      $taxQuery = $query->get('tax_query');
      if (!is_array($taxQuery)) {
        $taxQuery = [];
      }
      $taxQuery[] = [
        'taxonomy' => $this->config->slug,
        'field' => 'term_id',
        'terms' => [(int) $value],
      ];
      $query->set('tax_query', $taxQuery);
    }
  }

  /**
   * @param array<string, string> $actions
   * @return array<string, string>
   */
  public function registerBulkAction(array $actions): array
  {
    if (!current_user_can(Config::ASSIGN_CAP)) {
      return $actions;
    }

    $actions['edit_media_categories'] = sprintf(
      'Edit %s…',
      strtolower($this->config->pluralLabel),
    );

    return $actions;
  }

  /**
   * List-view bulk: redirect into a lightweight assign screen via query args.
   * Actual assignment is handled client-side via the shared REST endpoint
   * (see media-library.js). Here we only pass selected IDs through.
   *
   * @param list<int|string> $postIds
   */
  public function handleBulkAction(string $redirectTo, string $action, array $postIds): string
  {
    if ($action !== 'edit_media_categories') {
      return $redirectTo;
    }

    $ids = array_values(array_filter(array_map('intval', $postIds)));
    if ($ids === []) {
      return $redirectTo;
    }

    return add_query_arg([
      'media_categories_bulk' => 1,
      'media_ids' => implode(',', $ids),
    ], $redirectTo);
  }

  public function bulkAdminNotice(): void
  {
    if (!isset($_GET['media_categories_updated'])) {
      return;
    }

    $count = (int) $_GET['media_categories_updated'];
    if ($count <= 0) {
      return;
    }

    printf(
      '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
      esc_html(sprintf(
        _n(
          '%d media item updated.',
          '%d media items updated.',
          $count,
          'media-categories',
        ),
        $count,
      )),
    );
  }
}
