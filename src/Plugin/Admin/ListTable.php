<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Admin;

use CloakWP\Core\Media\LibraryFilter;
use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use CloakWP\MediaCategories\Core\Support\TermTree;

/**
 * Media Library list view: filter dropdown HTML + bulk action.
 * Query filtering is registered via CloakWP\Core\Media\LibraryFilter.
 */
final class ListTable
{
  /** Admin list filter query arg — must NOT match the taxonomy query_var. */
  public const FILTER_ARG = 'media_category';

  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    add_filter('bulk_actions-upload', [$this, 'registerBulkAction']);
    // upload.php applies this in the switch default, then redirects.
    add_filter('handle_bulk_actions-upload', [$this, 'handleBulkActionsFilter'], 10, 3);
    // Belt-and-suspenders: also catch on load (before upload.php's own handler).
    add_action('load-upload.php', [$this, 'handleBulkActionOnLoad']);
    add_action('admin_footer-upload.php', [$this, 'renderBulkPanelBoot']);
    add_action('admin_notices', [$this, 'bulkAdminNotice']);
  }

  public function renderFilter(string $postType, string $which): void
  {
    if ($postType !== 'attachment') {
      return;
    }

    // WP_Media_List_Table::views() calls extra_tablenav( 'bar' ) in the
    // list-mode filter bar. 'top'/'bottom' tablenav exits before the action.
    if ($which !== 'bar') {
      return;
    }

    $taxonomy = get_taxonomy($this->config->slug);
    if (!$taxonomy) {
      return;
    }

    $selected = isset($_GET[self::FILTER_ARG])
      ? sanitize_text_field(wp_unslash((string) $_GET[self::FILTER_ARG]))
      : '';

    $parsed = AttachmentQuery::parse($selected);
    $notChecked = $parsed['mode'] === AttachmentQuery::MODE_NOT;
    $selectedValue = '';
    if ($parsed['uncategorized']) {
      $selectedValue = Config::UNCATEGORIZED_QUERY;
    } elseif (count($parsed['termIds']) === 1) {
      $selectedValue = (string) $parsed['termIds'][0];
    } elseif ($parsed['termIds'] !== []) {
      $selectedValue = implode(',', $parsed['termIds']);
    }

    $label = $taxonomy->labels->filter_by_item ?? sprintf('Filter by %s', $this->config->singularLabel);
    $allLabel = $taxonomy->labels->all_items ?? sprintf('All %s', strtolower($this->config->pluralLabel));

    echo '<label class="screen-reader-text" for="media-categories-filter">' . esc_html($label) . '</label>';
    echo '<select name="' . esc_attr(self::FILTER_ARG) . '" id="media-categories-filter" class="attachment-filters media-categories-filter-select" data-encoded="' . esc_attr($selected) . '">';
    printf(
      '<option value=""%s>%s</option>',
      $selectedValue === '' ? ' selected="selected"' : '',
      esc_html($allLabel),
    );
    printf(
      '<option value="%s"%s>%s</option>',
      esc_attr(Config::UNCATEGORIZED_QUERY),
      $selectedValue === Config::UNCATEGORIZED_QUERY ? ' selected="selected"' : '',
      esc_html__('Uncategorized', 'media-categories'),
    );

    foreach (TermTree::fromTaxonomy($this->config->slug) as $term) {
      $optionValue = (string) $term['id'];
      $optionLabel = TermTree::prefix($term['depth']) . $term['name'] . ' (' . $term['count'] . ')';
      $isSelected = $selectedValue === $optionValue;
      printf(
        '<option value="%s"%s>%s</option>',
        esc_attr($optionValue),
        $isSelected ? ' selected="selected"' : '',
        esc_html($optionLabel),
      );
    }

    echo '</select>';
    printf(
      '<label class="media-categories-filter-not-wrap"><input type="checkbox" name="media_category_not" id="media-categories-filter-not" value="1"%s /> %s</label>',
      $notChecked ? ' checked="checked"' : '',
      esc_html__('Not in', 'media-categories'),
    );
  }

  /**
   * List view uses FILTER_ARG; grid/modal uses the taxonomy slug (WP's ajax allowlist).
   *
   * @param array<string, mixed> $args
   */
  public function resolveFilterValue(array $args): string
  {
    $listValue = LibraryFilter::valueFromRequest(self::FILTER_ARG, $args);
    $ajaxValue = LibraryFilter::valueFromRequest($this->config->slug, $args);
    $value = $listValue !== '' ? $listValue : $ajaxValue;

    if (
      !empty($_GET['media_category_not'])
      && $value !== ''
      && !str_starts_with($value, AttachmentQuery::NOT_PREFIX)
    ) {
      $value = AttachmentQuery::NOT_PREFIX . $value;
    }

    return $value;
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
   * @param list<int> $postIds
   */
  public function handleBulkActionsFilter(string $location, string $doaction, array $postIds): string
  {
    if ($doaction !== 'edit_media_categories') {
      return $location;
    }

    $postIds = array_values(array_filter(array_map('intval', $postIds)));
    if ($postIds === []) {
      return $location;
    }

    return add_query_arg(
      [
        'mode' => 'list',
        'media_categories_bulk' => 1,
        'media_ids' => implode(',', $postIds),
      ],
      admin_url('upload.php'),
    );
  }

  /**
   * Process list-view bulk action early (load-upload.php runs before upload.php body).
   */
  public function handleBulkActionOnLoad(): void
  {
    $action = isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1'
      ? sanitize_text_field(wp_unslash((string) $_REQUEST['action']))
      : '';

    if ($action === '' || $action === '-1') {
      $action = isset($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1'
        ? sanitize_text_field(wp_unslash((string) $_REQUEST['action2']))
        : '';
    }

    if ($action !== 'edit_media_categories') {
      return;
    }

    // Avoid dying on the follow-up GET after we already redirected.
    if (!isset($_REQUEST['_wpnonce'])) {
      return;
    }

    check_admin_referer('bulk-media');

    $postIds = [];
    if (isset($_REQUEST['media'])) {
      $postIds = array_map('intval', (array) wp_unslash($_REQUEST['media']));
    } elseif (isset($_REQUEST['ids'])) {
      $postIds = array_map('intval', explode(',', (string) wp_unslash($_REQUEST['ids'])));
    }

    $postIds = array_values(array_filter($postIds));
    if ($postIds === []) {
      return;
    }

    $location = $this->handleBulkActionsFilter(admin_url('upload.php'), $action, $postIds);
    wp_safe_redirect($location);
    exit;
  }

  /**
   * Boot the bulk panel from PHP when redirected back with selected IDs.
   * Avoids relying solely on JS reading query args (and works if wp.media isn't loaded yet).
   */
  public function renderBulkPanelBoot(): void
  {
    if (!isset($_GET['media_categories_bulk']) || (string) $_GET['media_categories_bulk'] !== '1') {
      return;
    }

    if (!current_user_can(Config::ASSIGN_CAP)) {
      return;
    }

    $ids = isset($_GET['media_ids'])
      ? array_values(array_filter(array_map('intval', explode(',', (string) wp_unslash($_GET['media_ids'])))))
      : [];

    if ($ids === []) {
      return;
    }

    printf(
      '<script>window.mediaCategoriesBulkIds = %s;</script>',
      wp_json_encode($ids),
    );
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
