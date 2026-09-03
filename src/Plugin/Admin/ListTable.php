<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin\Admin;

use CloakWP\Core\Media\LibraryFilter;
use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\AttachmentQuery;
use CloakWP\MediaTaxonomies\Core\Support\TermTree;
use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;

/**
 * Media Library list view: filter dropdown HTML + bulk action.
 */
final class ListTable
{
  public function __construct(
    private readonly Config $config,
  ) {
  }

  public function register(): void
  {
    add_filter('bulk_actions-upload', [$this, 'registerBulkAction']);
    add_filter('handle_bulk_actions-upload', [$this, 'handleBulkActionsFilter'], 10, 3);
    add_action('load-upload.php', [$this, 'handleBulkActionOnLoad']);
    add_action('admin_footer-upload.php', [$this, 'renderBulkPanelBoot']);
    add_action('admin_notices', [$this, 'bulkAdminNotice']);
  }

  public function renderFilter(TaxonomyConfig $taxonomy): void
  {
    $wpTax = get_taxonomy($taxonomy->slug);
    if (!$wpTax) {
      return;
    }

    $filterArg = $taxonomy->listFilterArg();
    $selected = isset($_GET[$filterArg])
      ? sanitize_text_field(wp_unslash((string) $_GET[$filterArg]))
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

    $label = $wpTax->labels->filter_by_item ?? sprintf('Filter by %s', $taxonomy->singularLabel);
    $allLabel = $wpTax->labels->all_items ?? sprintf('All %s', strtolower($taxonomy->pluralLabel));
    $selectId = 'media-taxonomies-filter-' . $taxonomy->slug;
    $notId = 'media-taxonomies-filter-not-' . $taxonomy->slug;

    echo '<label class="screen-reader-text" for="' . esc_attr($selectId) . '">' . esc_html($label) . '</label>';
    echo '<select name="' . esc_attr($filterArg) . '" id="' . esc_attr($selectId) . '" class="attachment-filters media-categories-filter-select media-taxonomies-filter-select" data-taxonomy="' . esc_attr($taxonomy->slug) . '" data-encoded="' . esc_attr($selected) . '">';
    printf(
      '<option value=""%s>%s</option>',
      $selectedValue === '' ? ' selected="selected"' : '',
      esc_html($allLabel),
    );
    printf(
      '<option value="%s"%s>%s</option>',
      esc_attr(Config::UNCATEGORIZED_QUERY),
      $selectedValue === Config::UNCATEGORIZED_QUERY ? ' selected="selected"' : '',
      esc_html__('Uncategorized', 'media-taxonomies'),
    );

    foreach (TermTree::fromTaxonomy($taxonomy->slug) as $term) {
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
      '<label class="media-categories-filter-not-wrap"><input type="checkbox" name="%s" id="%s" value="1"%s /> %s</label>',
      esc_attr($filterArg . '_not'),
      esc_attr($notId),
      $notChecked ? ' checked="checked"' : '',
      esc_html__('Not in', 'media-taxonomies'),
    );
  }

  /**
   * @param array<string, mixed> $args
   */
  public function resolveFilterValue(TaxonomyConfig $taxonomy, array $args): string
  {
    $listValue = LibraryFilter::valueFromRequest($taxonomy->listFilterArg(), $args);
    $ajaxValue = LibraryFilter::valueFromRequest($taxonomy->slug, $args);
    $value = $listValue !== '' ? $listValue : $ajaxValue;

    $notKey = $taxonomy->listFilterArg() . '_not';
    if (
      !empty($_GET[$notKey])
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

    $actions['edit_media_taxonomies'] = 'Edit media taxonomies…';

    return $actions;
  }

  /**
   * @param list<int> $postIds
   */
  public function handleBulkActionsFilter(string $location, string $doaction, array $postIds): string
  {
    if ($doaction !== 'edit_media_taxonomies') {
      return $location;
    }

    $postIds = array_values(array_filter(array_map('intval', $postIds)));
    if ($postIds === []) {
      return $location;
    }

    return add_query_arg(
      [
        'mode' => 'list',
        'media_taxonomies_bulk' => 1,
        'media_ids' => implode(',', $postIds),
      ],
      admin_url('upload.php'),
    );
  }

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

    if ($action !== 'edit_media_taxonomies') {
      return;
    }

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

  public function renderBulkPanelBoot(): void
  {
    if (!isset($_GET['media_taxonomies_bulk']) || (string) $_GET['media_taxonomies_bulk'] !== '1') {
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
      '<script>window.mediaTaxonomiesBulkIds = %s;</script>',
      wp_json_encode($ids),
    );
  }

  public function bulkAdminNotice(): void
  {
    if (!isset($_GET['media_taxonomies_updated'])) {
      return;
    }

    $count = (int) $_GET['media_taxonomies_updated'];
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
          'media-taxonomies',
        ),
        $count,
      )),
    );
  }
}
