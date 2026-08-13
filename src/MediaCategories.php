<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use CloakWP\MediaCategories\Core\Taxonomy\Capabilities;
use CloakWP\MediaCategories\Core\Taxonomy\Registrar;
use CloakWP\MediaCategories\Plugin\Plugin;
use InvalidArgumentException;

/**
 * Fluent entry point for Media Categories.
 *
 * @example
 * MediaCategories::make()
 *   ->manageRoles(['administrator', 'editor'])
 *   ->register();
 */
final class MediaCategories
{
  private static ?self $instance = null;

  private Config $config;
  private bool $registered = false;

  private ?Registrar $registrar = null;
  private ?Capabilities $capabilities = null;
  private ?TermAssigner $termAssigner = null;
  private ?AttachmentQuery $attachmentQuery = null;
  private ?Plugin $plugin = null;

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

  public function slug(string $slug): self
  {
    $this->assertMutable();
    $this->config = $this->config->withSlug($slug);
    return $this;
  }

  public function restBase(string $restBase): self
  {
    $this->assertMutable();
    $this->config = $this->config->withRestBase($restBase);
    return $this;
  }

  public function labels(string $singular, string $plural): self
  {
    $this->assertMutable();
    $this->config = $this->config->withLabels($singular, $plural);
    return $this;
  }

  public function hierarchical(bool $hierarchical = true): self
  {
    $this->assertMutable();
    $this->config = $this->config->withHierarchical($hierarchical);
    return $this;
  }

  public function rewrite(string|false $rewrite): self
  {
    $this->assertMutable();
    $this->config = $this->config->withRewrite($rewrite);
    return $this;
  }

  public function showInRest(bool $showInRest = true): self
  {
    $this->assertMutable();
    $this->config = $this->config->withShowInRest($showInRest);
    return $this;
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

  public function defaultTerm(?string $slug): self
  {
    $this->assertMutable();
    $this->config = $this->config->withDefaultTerm($slug);
    return $this;
  }

  public function config(): Config
  {
    return $this->config;
  }

  public function termAssigner(): TermAssigner
  {
    if ($this->termAssigner === null) {
      throw new InvalidArgumentException('Media Categories has not been registered yet.');
    }

    return $this->termAssigner;
  }

  public function attachmentQuery(): AttachmentQuery
  {
    if ($this->attachmentQuery === null) {
      throw new InvalidArgumentException('Media Categories has not been registered yet.');
    }

    return $this->attachmentQuery;
  }

  /**
   * Boot taxonomy, capabilities, and WordPress integration.
   */
  public function register(): self
  {
    if ($this->registered) {
      return $this;
    }

    if (self::$instance !== null && self::$instance->registered && self::$instance !== $this) {
      throw new InvalidArgumentException(
        'Media Categories is already registered. Call MediaCategories::make()->…->register() only once.'
      );
    }

    /** @var Config $config */
    $config = apply_filters('cloakwp/media-categories/config', $this->config);
    $this->config = $config;

    $this->registrar = new Registrar($this->config);
    $this->capabilities = new Capabilities($this->config);
    $this->termAssigner = new TermAssigner($this->config);
    $this->attachmentQuery = new AttachmentQuery($this->config);

    $this->registrar->register();
    $this->capabilities->register();

    $pluginFile = defined('CLOAKWP_MEDIA_CATEGORIES_FILE')
      ? CLOAKWP_MEDIA_CATEGORIES_FILE
      : dirname(__DIR__) . '/media-categories.php';

    $this->plugin = new Plugin(
      $this->config,
      $this->termAssigner,
      $this->attachmentQuery,
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
      throw new InvalidArgumentException('Cannot change Media Categories config after register().');
    }
  }
}
