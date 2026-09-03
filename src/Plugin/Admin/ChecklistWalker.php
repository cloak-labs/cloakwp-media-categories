<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin\Admin;

use Walker_Category_Checklist;
use WP_Term;

/**
 * Checklist walker that stamps data-slug on each checkbox so the media modal
 * can POST comma-separated slugs (what save-attachment-compat expects).
 */
final class ChecklistWalker extends Walker_Category_Checklist
{
  /**
   * @param string $output
   * @param WP_Term $data_object
   * @param array<string, mixed> $args
   */
  public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0): void
  {
    parent::start_el($output, $data_object, $depth, $args, $current_object_id);

    $slug = esc_attr((string) ($data_object->slug ?? ''));
    if ($slug === '') {
      return;
    }

    $needle = 'value="' . (int) $data_object->term_id . '"';
    $output = str_replace($needle, $needle . ' data-slug="' . $slug . '"', $output);
  }
}
