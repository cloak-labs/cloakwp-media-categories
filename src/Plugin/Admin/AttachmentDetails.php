<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies\Plugin\Admin;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\TermAssigner;
use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;
use CloakWP\MediaTaxonomies\Plugin\Assets;
use WP_Post;

/**
 * Attachment sidebar / modal: taxonomy checklists + add-new term UI.
 */
final class AttachmentDetails
{
  /**
   * @param array<string, TermAssigner> $termAssigners
   */
  public function __construct(
    private readonly Config $config,
    private readonly array $termAssigners,
    private readonly Assets $assets,
  ) {
  }

  public function register(): void
  {
    add_filter('attachment_fields_to_edit', [$this, 'fieldsToEdit'], 10, 2);
    add_filter('attachment_fields_to_save', [$this, 'fieldsToSave'], 10, 2);
    add_action('wp_enqueue_media', [$this->assets, 'enqueue']);
  }

  /**
   * @param array<string, array<string, mixed>> $fields
   * @return array<string, array<string, mixed>>
   */
  public function fieldsToEdit(array $fields, WP_Post $post): array
  {
    if (!current_user_can(Config::ASSIGN_CAP) || !current_user_can('edit_post', $post->ID)) {
      return $fields;
    }

    foreach ($this->config->taxonomies as $taxonomy) {
      $wpTax = get_taxonomy($taxonomy->slug);
      if (!$wpTax) {
        continue;
      }

      unset($fields[$taxonomy->slug]);

      $termIds = wp_get_object_terms($post->ID, $taxonomy->slug, ['fields' => 'ids']);
      if (is_wp_error($termIds)) {
        $termIds = [];
      }
      $termIds = array_values(array_map('intval', $termIds));

      ob_start();
      printf(
        '<div class="media-categories-attachment-field" data-taxonomy="%s" data-attachment-id="%d">',
        esc_attr($taxonomy->slug),
        (int) $post->ID,
      );
      echo '<input type="hidden" name="media_taxonomies_fields[' . esc_attr($taxonomy->slug) . ']" value="1" />';
      echo '<div class="media-categories-checklist-wrap">';
      echo '<ul class="media-categories-checklist categorychecklist">';
      wp_terms_checklist($post->ID, [
        'taxonomy' => $taxonomy->slug,
        'checked_ontop' => false,
        'selected_cats' => $termIds,
        'walker' => class_exists(\Walker_Category_Checklist::class) ? new ChecklistWalker() : null,
      ]);
      echo '</ul>';
      echo '</div>';

      if (current_user_can(Config::MANAGE_CAP)) {
        $this->renderAddNewForm($taxonomy, $wpTax);
      }

      echo '</div>';
      $html = (string) ob_get_clean();

      $fields[$taxonomy->slug] = [
        'label' => $wpTax->labels->name ?? $taxonomy->pluralLabel,
        'input' => 'html',
        'html' => $html,
        'show_in_edit' => false,
        'show_in_modal' => true,
      ];
    }

    return $fields;
  }

  /**
   * @param array<string, mixed> $attachment
   * @param array<string, mixed> $request
   * @return array<string, mixed>
   */
  public function fieldsToSave(array $attachment, array $request): array
  {
    $id = isset($attachment['ID']) ? (int) $attachment['ID'] : 0;
    if ($id <= 0) {
      return $attachment;
    }

    if (!current_user_can(Config::ASSIGN_CAP) || !current_user_can('edit_post', $id)) {
      return $attachment;
    }

    foreach ($this->config->taxonomies as $taxonomy) {
      $slug = $taxonomy->slug;
      if (!isset($request['tax_input'][$slug]) || !is_array($request['tax_input'][$slug])) {
        continue;
      }

      $assigner = $this->termAssigners[$slug] ?? null;
      if ($assigner === null) {
        continue;
      }

      $terms = array_values(array_filter(array_map('intval', $request['tax_input'][$slug])));
      $assigner->set($id, $terms);
    }

    return $attachment;
  }

  private function renderAddNewForm(TaxonomyConfig $taxonomy, object $wpTax): void
  {
    $singular = $wpTax->labels->singular_name ?? $taxonomy->singularLabel;
    $addLabel = $wpTax->labels->add_new_item ?? sprintf('Add New %s', $singular);
    $cancelLabel = __('Cancel', 'media-taxonomies');
    ?>
    <div class="media-categories-add-new">
      <button
        type="button"
        class="button-link media-categories-add-toggle"
        aria-expanded="false"
        data-label-add="<?php echo esc_attr($addLabel); ?>"
        data-label-cancel="<?php echo esc_attr($cancelLabel); ?>"
      >
        <?php echo esc_html($addLabel); ?>
      </button>
      <div class="media-categories-add-form hidden">
        <label class="media-categories-add-label">
          <span><?php echo esc_html__('Name', 'media-taxonomies'); ?></span>
          <input type="text" class="media-categories-new-name" />
        </label>
        <?php if ($taxonomy->hierarchical) : ?>
          <label class="media-categories-add-label">
            <span><?php echo esc_html__('Parent', 'media-taxonomies'); ?></span>
            <?php
            wp_dropdown_categories([
              'taxonomy' => $taxonomy->slug,
              'name' => '',
              'id' => '',
              'class' => 'media-categories-new-parent',
              'hierarchical' => true,
              'show_option_none' => '— None —',
              'option_none_value' => '0',
              'hide_empty' => false,
              'echo' => true,
            ]);
            ?>
          </label>
        <?php endif; ?>
        <button type="button" class="button media-categories-add-submit">
          <?php echo esc_html__('Add', 'media-taxonomies'); ?>
        </button>
      </div>
    </div>
    <?php
  }
}
