<?php

declare(strict_types=1);

namespace CloakWP\MediaCategories\Plugin;

use CloakWP\MediaCategories\Core\Config;
use CloakWP\MediaCategories\Core\Support\AttachmentQuery;
use CloakWP\MediaCategories\Core\Support\TermAssigner;
use CloakWP\MediaCategories\Plugin\Admin\AttachmentDetails;
use CloakWP\MediaCategories\Plugin\Admin\Grid;
use CloakWP\MediaCategories\Plugin\Admin\ListTable;
use CloakWP\MediaCategories\Plugin\Rest\BulkAssignController;

/**
 * WordPress integration layer (hooks, admin UI, REST, assets).
 */
final class Plugin
{
  public function __construct(
    private readonly Config $config,
    private readonly TermAssigner $termAssigner,
    private readonly AttachmentQuery $attachmentQuery,
    private readonly string $pluginFile,
  ) {
  }

  public function boot(): void
  {
    $assets = new Assets($this->config, $this->pluginFile);
    $assets->register();

    (new ListTable($this->config, $this->attachmentQuery))->register();
    (new Grid($this->config, $this->attachmentQuery))->register();
    (new AttachmentDetails($this->config, $this->termAssigner, $assets))->register();
    (new BulkAssignController($this->config, $this->termAssigner))->register();

    if ($this->config->defaultTerm !== null) {
      add_action('add_attachment', [$this->termAssigner, 'assignDefaultIfConfigured']);
    }

    // Flush rewrites once when rewrite slug changes (not on every request).
    add_action('init', [$this, 'maybeFlushRewrites'], 99);
  }

  public function maybeFlushRewrites(): void
  {
    if ($this->config->rewrite === false) {
      return;
    }

    $optionKey = 'cloakwp_media_categories_rewrite';
    $current = (string) get_option($optionKey, '');
    $desired = $this->config->slug . '|' . $this->config->rewrite;

    if ($current === $desired) {
      return;
    }

    flush_rewrite_rules(false);
    update_option($optionKey, $desired, false);
  }
}
