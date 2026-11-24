<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Calendar\HtmxPlaceholder;
use Drupal\paatokset_ahjo_api\Entity\Policymaker;

/**
 * Provides policymaker calendar block.
 *
 * The calendar content is loaded with htmx, so it can expire
 * when the day changes without affecting the page cache.
 *
 * @see \Drupal\paatokset_ahjo_api\Calendar\Controller\PolicymakerCalendarController
 */
#[Block(
  id: 'policymaker_calendar',
  admin_label: new TranslatableMarkup('Paatokset policymaker calendar'),
  category: new TranslatableMarkup('Paatokset custom blocks'),
)]
final class PolicymakerCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new instance.
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   *
   * @phpstan-return array<string, mixed>
   */
  public function build(): array {
    $build = [];
    $cache = (new CacheableMetadata())->addCacheContexts(['route']);

    $policymaker = $this->routeMatch->getParameter('node');

    if ($policymaker instanceof Policymaker && $policymaker->getPolicymakerId()) {
      $cache->addCacheableDependency($policymaker);
      $build = HtmxPlaceholder::build(Url::fromRoute('paatokset_ahjo_api.policymaker_calendar', [
        'node' => $policymaker->id(),
      ]));
    }

    CacheableMetadata::createFromRenderArray($build)
      ->merge($cache)
      ->applyTo($build);

    return $build;
  }

}
