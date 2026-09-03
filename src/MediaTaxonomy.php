<?php

declare(strict_types=1);

namespace CloakWP\MediaTaxonomies;

use CloakWP\MediaTaxonomies\Core\TaxonomyConfig;

/**
 * Fluent builder for one media taxonomy.
 *
 * @example
 * MediaTaxonomy::make('landscape_type')
 *   ->labels('Landscape Type', 'Landscape Types')
 *   ->hierarchical();
 */
final class MediaTaxonomy
{
  private function __construct(
    private TaxonomyConfig $config,
  ) {
  }

  public static function make(string $slug): self
  {
    return new self(TaxonomyConfig::fromSlug($slug));
  }

  public function restBase(string $restBase): self
  {
    $this->config = $this->config->withRestBase($restBase);
    return $this;
  }

  public function labels(string $singular, string $plural): self
  {
    $this->config = $this->config->withLabels($singular, $plural);
    return $this;
  }

  public function hierarchical(bool $hierarchical = true): self
  {
    $this->config = $this->config->withHierarchical($hierarchical);
    return $this;
  }

  public function rewrite(string|false $rewrite): self
  {
    $this->config = $this->config->withRewrite($rewrite);
    return $this;
  }

  public function showInRest(bool $showInRest = true): self
  {
    $this->config = $this->config->withShowInRest($showInRest);
    return $this;
  }

  public function defaultTerm(?string $slug): self
  {
    $this->config = $this->config->withDefaultTerm($slug);
    return $this;
  }

  public function config(): TaxonomyConfig
  {
    return $this->config;
  }
}
