<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Tests;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use CloakWP\MediaCategories\Plugin\Admin\AttachmentDetails;
use CloakWP\MediaCategories\Plugin\Assets;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Modal assignment must not POST attachments[ID][{taxonomy}] — core treats that
 * as slugs and will insert terms named "4"/"5" or clear a REST/ID save.
 */
final class AttachmentDetailsCompatTest extends TestCase
{
  private AttachmentDetails $details;

  protected function setUp(): void
  {
    WpStubs::reset();
    WpStubs::$caps = [
      'assign_media_categories' => true,
      'edit_post:585' => true,
    ];
    WpStubs::$taxonomies['category_media'] = (object) [
      'labels' => (object) [
        'name' => 'Media Categories',
        'singular_name' => 'Media Category',
        'all_items' => 'All media categories',
      ],
    ];
    WpStubs::$objectTerms[585] = [
      (object) ['term_id' => 5, 'slug' => 'design-renderings', 'name' => 'Design Renderings'],
    ];
    WpStubs::$existingSlugs = [
      'design-renderings' => true,
    ];

    $config = Config::defaults();
    $this->details = new AttachmentDetails(
      $config,
      new TermAssigner($config),
      new Assets($config, '/tmp/media-categories.php'),
    );
  }

  public function testModalHtmlDoesNotEmitTaxonomyKeyCoreWouldTreatAsSlugs(): void
  {
    $fields = $this->details->fieldsToEdit([], new WP_Post(585));
    $html = (string) ($fields['category_media']['html'] ?? '');

    $this->assertNotSame('', $html);
    $this->assertStringContainsString('media-categories-checklist-wrap', $html);
    $this->assertStringContainsString('data-attachment-id="585"', $html);
    $this->assertStringNotContainsString(
      'name="attachments[585][category_media]"',
      $html,
      'Core save-attachment-compat would persist this as slugs, not term IDs.',
    );
    $this->assertFalse($fields['category_media']['show_in_edit']);
    $this->assertTrue($fields['category_media']['show_in_modal']);
  }

  public function testFieldsToSaveIgnoresTaxonomySlugStrings(): void
  {
    $this->details->fieldsToSave(
      ['ID' => 585],
      ['category_media' => 'design-renderings'],
    );

    $this->assertSame([], WpStubs::$setCalls);
  }

  public function testFieldsToSaveHandlesClassicTaxInputIds(): void
  {
    $this->details->fieldsToSave(
      ['ID' => 585],
      ['tax_input' => ['category_media' => [5]]],
    );

    $this->assertCount(1, WpStubs::$setCalls);
    $this->assertSame([5], WpStubs::$setCalls[0]['terms']);
  }

  public function testCoreWouldInsertIdNamedTermsIfTaxonomyKeyHoldsNumericStrings(): void
  {
    $this->simulateCoreTaxonomyAssignment(['category_media' => '4,5']);
    $this->assertSame(['4', '5'], WpStubs::$insertedTermNames);
  }

  /**
   * @param array<string, mixed> $attachmentData
   */
  private function simulateCoreTaxonomyAssignment(array $attachmentData): void
  {
    foreach (['category_media'] as $taxonomy) {
      if (!isset($attachmentData[$taxonomy])) {
        continue;
      }

      $terms = array_map('trim', preg_split('/,+/', (string) $attachmentData[$taxonomy]) ?: []);
      foreach ($terms as $term) {
        if ($term === '' || is_int($term)) {
          continue;
        }
        if (isset(WpStubs::$existingSlugs[$term])) {
          continue;
        }
        WpStubs::$insertedTermNames[] = $term;
      }
    }
  }
}
