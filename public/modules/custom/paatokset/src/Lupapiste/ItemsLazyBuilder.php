<?php

declare(strict_types=1);

namespace Drupal\paatokset\Lupapiste;

use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A lazy builder to render Lupapiste RSS items.
 */
final class ItemsLazyBuilder implements TrustedCallbackInterface {

  use StringTranslationTrait;

  public function __construct(
    private PagerManagerInterface $pagerManager,
    private ItemsStorage $storage,
  ) {

  }

  /**
   * A lazy loader callback to build current page.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The render array.
   */
  public function build(string $langcode) : array {
    $offset = ($this->pagerManager->findPage() * ItemsStorage::PER_PAGE);
    $collection = $this->storage->load($langcode, $offset);

    $build = [
      '#cache' => [
        'contexts' => ['languages:language_content', 'url.query_args.pagers'],
        'tags' => [ItemsStorage::CACHE_TAG],
      ],
      'items' => [
        '#theme' => 'lupapiste_rss_list',
        '#feed_link' => $collection->url,
        '#title' => '',
        '#content' => [],
      ],
    ];

    if ($collection->total > 1) {
      $build['items']['#title'] = $this->t('@count proclamations', ['@count' => $collection->total], [
        'context' => 'Lupapiste rss',
      ]);
    }
    $this->pagerManager->createPager($collection->total, ItemsStorage::PER_PAGE);

    foreach ($collection->items as $item) {
      $build['items']['#content'][] = [
        '#theme' => 'lupapiste_rss_item',
        '#item' => $item,
      ];
    }
    $build['pager'] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['build'];
  }

}
