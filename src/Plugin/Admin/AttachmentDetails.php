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

    ob_start();
    echo '<div class="media-categories-attachment-field" data-taxonomy="' . esc_attr($this->config->slug) . '">';
    // Marker so empty checkbox sets still clear terms on save.
    echo '<input type="hidden" name="media_categories_fields[' . esc_attr($this->config->slug) . ']" value="1" />';
    echo '<ul class="media-categories-checklist categorychecklist">';
    wp_terms_checklist($post->ID, [
      'taxonomy' => $this->config->slug,
      'checked_ontop' => false,
      'walker' => null,
    ]);
    echo '</ul>';

    if (current_user_can(Config::MANAGE_CAP)) {
      $this->renderAddNewForm($taxonomy);
    }

    echo '</div>';
    $html = (string) ob_get_clean();

    $fields[$this->config->slug] = [
      'label' => $taxonomy->labels->name ?? $this->config->pluralLabel,
      'input' => 'html',
      'html' => $html,
      'show_in_edit' => true,
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

    $fieldWasPresent = isset($request['media_categories_fields'][$this->config->slug])
      || isset($request['tax_input'][$this->config->slug])
      || isset($request[$this->config->slug]);

    if (!$fieldWasPresent) {
      return $attachment;
    }

    $terms = [];
    if (isset($request['tax_input'][$this->config->slug]) && is_array($request['tax_input'][$this->config->slug])) {
      $terms = array_map('intval', $request['tax_input'][$this->config->slug]);
    } elseif (isset($request[$this->config->slug]) && is_array($request[$this->config->slug])) {
      $terms = array_map('intval', $request[$this->config->slug]);
    }

    $this->termAssigner->set($id, $terms);

    return $attachment;
  }

  private function renderAddNewForm(object $taxonomy): void
  {
    $singular = $taxonomy->labels->singular_name ?? $this->config->singularLabel;
    ?>
    <div class="media-categories-add-new">
      <button type="button" class="button-link media-categories-add-toggle" aria-expanded="false">
        <?php echo esc_html($taxonomy->labels->add_new_item ?? sprintf('Add New %s', $singular)); ?>
      </button>
      <div class="media-categories-add-form hidden">
        <label>
          <span class="screen-reader-text"><?php echo esc_html($taxonomy->labels->new_item_name ?? sprintf('New %s Name', $singular)); ?></span>
          <input type="text" class="media-categories-new-name" placeholder="<?php echo esc_attr($singular); ?>" />
        </label>
        <?php if ($this->config->hierarchical) : ?>
          <label>
            <span class="screen-reader-text"><?php echo esc_html($taxonomy->labels->parent_item ?? sprintf('Parent %s', $singular)); ?></span>
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
