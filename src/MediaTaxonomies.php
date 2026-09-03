<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies;

use CloakWP\MediaTaxonomies\Core\Config;
use CloakWP\MediaTaxonomies\Core\Support\AttachmentQuery;
use CloakWP\MediaTaxonomies\Core\Support\TermAssigner;
use CloakWP\MediaTaxonomies\Core\Taxonomy\Capabilities;
use CloakWP\MediaTaxonomies\Core\Taxonomy\Registrar;
use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;
use CloakWP\MediaTaxonomies\Plugin\Plugin;
use InvalidArgumentException;

/**
 * Fluent entry point for Media Taxonomies.
 *
 * @example
 * MediaTaxonomies::make()
 *   ->taxonomies([
 *     MediaTaxonomy::make('landscape_type')->labels('Landscape Type', 'Landscape Types'),
 *   ])
 *   ->register();
 */
final class MediaTaxonomies
{
  private static ?self $instance = null;

  private Config $config;
  private bool $registered = false;

  private ?Registrar $registrar = null;
  private ?Capabilities $capabilities = null;
  private ?Plugin $plugin = null;

  /** @var array<string, TermAssigner> */
  private array $termAssigners = [];

  /** @var array<string, AttachmentQuery> */
  private array $attachmentQueries = [];

  private function __construct(Config $config)
  {
    $this->config = $config;
  }

  public static function make(): self
  {
    return new self(Config::defaults());
  }

  public static function booted(): bool
  {
    return self::$instance !== null && self::$instance->registered;
  }

  public static function instance(): ?self
  {
    return self::$instance;
  }

  /**
   * Registered taxonomies, or the default Media Categories taxonomy before boot.
   *
   * @return list<TaxonomyConfig>
   */
  public static function all(): array
  {
    if (self::$instance !== null) {
      return self::$instance->config->taxonomies;
    }

    return Config::defaults()->taxonomies;
  }

  /**
   * @param list<string> $roles
   */
  public function manageRoles(array $roles): self
  {
    $this->assertMutable();
    $this->config = $this->config->withManageRoles($roles);
    return $this;
  }

  /**
   * @param list<string> $roles
   */
  public function assignRoles(array $roles): self
  {
    $this->assertMutable();
    $this->config = $this->config->withAssignRoles($roles);
    return $this;
  }

  /**
   * Replace the default taxonomy set.
   *
   * @param list<MediaTaxonomy> $taxonomies
   */
  public function taxonomies(array $taxonomies): self
  {
    $this->assertMutable();
    $configs = [];
    foreach ($taxonomies as $taxonomy) {
      if (!$taxonomy instanceof MediaTaxonomy) {
        throw new InvalidArgumentException('taxonomies() expects a list of MediaTaxonomy instances.');
      }
      $configs[] = $taxonomy->config();
    }
    if ($configs === []) {
      throw new InvalidArgumentException('taxonomies() requires at least one MediaTaxonomy.');
    }
    $this->config = $this->config->withTaxonomies($configs);
    return $this;
  }

  public function config(): Config
  {
    return $this->config;
  }

  public function termAssigner(string $slug): TermAssigner
  {
    if (!isset($this->termAssigners[$slug])) {
      throw new InvalidArgumentException(sprintf('Unknown media taxonomy: %s', $slug));
    }

    return $this->termAssigners[$slug];
  }

  public function attachmentQuery(string $slug): AttachmentQuery
  {
    if (!isset($this->attachmentQueries[$slug])) {
      throw new InvalidArgumentException(sprintf('Unknown media taxonomy: %s', $slug));
    }

    return $this->attachmentQueries[$slug];
  }

  public function register(): self
  {
    if ($this->registered) {
      return $this;
    }

    if (self::$instance !== null && self::$instance->registered && self::$instance !== $this) {
      throw new InvalidArgumentException(
        'Media Taxonomies is already registered. Call MediaTaxonomies::make()->…->register() only once.',
      );
    }

    /** @var Config $config */
    $config = apply_filters('cloakwp/media-taxonomies/config', $this->config);
    $this->config = $config;

    foreach ($this->config->taxonomies as $taxonomy) {
      $this->termAssigners[$taxonomy->slug] = new TermAssigner($taxonomy);
      $this->attachmentQueries[$taxonomy->slug] = new AttachmentQuery($taxonomy);
    }

    $this->registrar = new Registrar($this->config);
    $this->capabilities = new Capabilities($this->config);
    $this->registrar->register();
    $this->capabilities->register();

    $pluginFile = defined('CLOAKWP_MEDIA_TAXONOMIES_FILE')
      ? CLOAKWP_MEDIA_TAXONOMIES_FILE
      : dirname(__DIR__) . '/media-taxonomies.php';

    $this->plugin = new Plugin(
      $this->config,
      $this->termAssigners,
      $this->attachmentQueries,
      $pluginFile,
    );
    $this->plugin->boot();

    $this->registered = true;
    self::$instance = $this;

    return $this;
  }

  private function assertMutable(): void
  {
    if ($this->registered) {
      throw new InvalidArgumentException('Cannot change Media Taxonomies config after register().');
    }
  }
}
