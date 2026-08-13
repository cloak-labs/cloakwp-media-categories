<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Core\Support;

use CloakWP\MediaCategories\Core\Config;
use WP_Error;

/**
 * Assigns / removes taxonomy terms on attachments via core term APIs.
 */
final class TermAssigner
{
  public function __construct(
    private readonly Config $config,
  ) {
  }

  /**
   * @param list<int|string> $termIds
   * @return array<string, mixed>|WP_Error
   */
  public function add(int $attachmentId, array $termIds): array|WP_Error
  {
    return wp_set_object_terms($attachmentId, $this->normalizeTermIds($termIds), $this->config->slug, true);
  }

  /**
   * @param list<int|string> $termIds
   * @return bool|WP_Error
   */
  public function remove(int $attachmentId, array $termIds): bool|WP_Error
  {
    return wp_remove_object_terms($attachmentId, $this->normalizeTermIds($termIds), $this->config->slug);
  }

  /**
   * Replace all terms on an attachment. Empty $termIds clears them.
   *
   * @param list<int|string> $termIds
   * @return array<string, mixed>|WP_Error
   */
  public function set(int $attachmentId, array $termIds): array|WP_Error
  {
    return wp_set_object_terms($attachmentId, $this->normalizeTermIds($termIds), $this->config->slug, false);
  }

  /**
   * @param list<int> $attachmentIds
   * @param list<int|string> $termIds
   * @return array{updated: list<int>, skipped: list<int>, errors: list<array{id: int, message: string}>}
   */
  public function bulkAssign(array $attachmentIds, array $termIds, bool $append): array
  {
    $updated = [];
    $skipped = [];
    $errors = [];
    $termIds = $this->normalizeTermIds($termIds);

    foreach ($attachmentIds as $attachmentId) {
      $attachmentId = (int) $attachmentId;

      if ($attachmentId <= 0 || get_post_type($attachmentId) !== 'attachment') {
        $skipped[] = $attachmentId;
        continue;
      }

      if (!current_user_can(Config::ASSIGN_CAP) || !current_user_can('edit_post', $attachmentId)) {
        $skipped[] = $attachmentId;
        continue;
      }

      if ($append) {
        if ($termIds === []) {
          $skipped[] = $attachmentId;
          continue;
        }
        $result = $this->add($attachmentId, $termIds);
      } else {
        if ($termIds === []) {
          $result = $this->set($attachmentId, []);
        } else {
          $result = $this->remove($attachmentId, $termIds);
        }
      }

      if (is_wp_error($result)) {
        $errors[] = [
          'id' => $attachmentId,
          'message' => $result->get_error_message(),
        ];
        continue;
      }

      $updated[] = $attachmentId;
    }

    return [
      'updated' => $updated,
      'skipped' => $skipped,
      'errors' => $errors,
    ];
  }

  /**
   * Ensure a default term exists and assign it to a newly uploaded attachment.
   */
  public function assignDefaultIfConfigured(int $attachmentId): void
  {
    $slug = $this->config->defaultTerm;
    if ($slug === null || $slug === '') {
      return;
    }

    $term = get_term_by('slug', $slug, $this->config->slug);
    if (!$term) {
      $created = wp_insert_term($slug, $this->config->slug, ['slug' => $slug]);
      if (is_wp_error($created)) {
        return;
      }
      $termId = (int) $created['term_id'];
    } else {
      $termId = (int) $term->term_id;
    }

    $this->add($attachmentId, [$termId]);
  }

  /**
   * @param list<int|string> $termIds
   * @return list<int>
   */
  private function normalizeTermIds(array $termIds): array
  {
    $normalized = [];
    foreach ($termIds as $id) {
      $id = (int) $id;
      if ($id > 0) {
        $normalized[] = $id;
      }
    }

    return array_values(array_unique($normalized));
  }
}
