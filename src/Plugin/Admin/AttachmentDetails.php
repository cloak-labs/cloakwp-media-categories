<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin\Admin;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use CloakWP\MediaCategories\Plugin\Assets;
use WP_Post;

/**
 * Attachment sidebar / modal: category checklist + add-new term UI.
 */
final class AttachmentDetails
{
  public function __construct(
    private readonly Config $config,
    private readonly TermAssigner $termAssigner,
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

    $taxonomy = get_taxonomy($this->config->slug);
    if (!$taxonomy) {
      return $fields;
    }

    // Remove any default taxonomy field WP/plugin may have added for this slug.
    unset($fields[$this->config->slug]);

    $termIds = wp_get_object_terms($post->ID, $this->config->slug, ['fields' => 'ids']);
    if (is_wp_error($termIds)) {
      $termIds = [];
    }
    $termIds = array_values(array_map('intval', $termIds));

    ob_start();
    printf(
      '<div class="media-categories-attachment-field" data-taxonomy="%s" data-attachment-id="%d">',
      esc_attr($this->config->slug),
      (int) $post->ID,
    );
    echo '<input type="hidden" name="media_categories_fields[' . esc_attr($this->config->slug) . ']" value="1" />';
    /*
     * Do not output attachments[ID][{taxonomy}]. Core save-attachment-compat treats
     * that key as slugs and will clobber REST/ID-based saves (or insert terms named "4").
     */
    echo '<div class="media-categories-checklist-wrap">';
    echo '<ul class="media-categories-checklist categorychecklist">';
    wp_terms_checklist($post->ID, [
      'taxonomy' => $this->config->slug,
      'checked_ontop' => false,
      'selected_cats' => $termIds,
      'walker' => class_exists(\Walker_Category_Checklist::class) ? new ChecklistWalker() : null,
    ]);
    echo '</ul>';
    echo '</div>';

    if (current_user_can(Config::MANAGE_CAP)) {
      $this->renderAddNewForm($taxonomy);
    }

    echo '</div>';
    $html = (string) ob_get_clean();

    $fields[$this->config->slug] = [
      'label' => $taxonomy->labels->name ?? $this->config->pluralLabel,
      'input' => 'html',
      'html' => $html,
      // Dedicated post.php edit screen already has the core taxonomy metabox.
      'show_in_edit' => false,
      'show_in_modal' => true,
    ];

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

    $slug = $this->config->slug;
    if (!isset($request['tax_input'][$slug]) || !is_array($request['tax_input'][$slug])) {
      return $attachment;
    }

    $terms = array_values(array_filter(array_map('intval', $request['tax_input'][$slug])));
    $this->termAssigner->set($id, $terms);

    return $attachment;
  }

  private function renderAddNewForm(object $taxonomy): void
  {
    $singular = $taxonomy->labels->singular_name ?? $this->config->singularLabel;
    $addLabel = $taxonomy->labels->add_new_item ?? sprintf('Add New %s', $singular);
    $cancelLabel = __('Cancel', 'media-categories');
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
          <span><?php echo esc_html__('Name', 'media-categories'); ?></span>
          <input type="text" class="media-categories-new-name" />
        </label>
        <?php if ($this->config->hierarchical) : ?>
          <label class="media-categories-add-label">
            <span><?php echo esc_html__('Parent', 'media-categories'); ?></span>
            <?php
            wp_dropdown_categories([
              'taxonomy' => $this->config->slug,
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
          <?php echo esc_html__('Add', 'media-categories'); ?>
        </button>
      </div>
    </div>
    <?php
  }
}
