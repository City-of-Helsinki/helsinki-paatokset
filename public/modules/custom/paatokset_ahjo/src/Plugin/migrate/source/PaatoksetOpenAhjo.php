<?php

declare(strict_types = 1);

namespace Drupal\paatokset_ahjo\Plugin\migrate\source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\helfi_api_base\MigrateTrait;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;
use GuzzleHttp\ClientInterface;

/**
 * Source plugin for retrieving data from OpenAhjo.
 *
 * @MigrateSource(
 *   id = "paatokset_open_ahjo"
 * )
 */
class PaatoksetOpenAhjo extends HttpSourcePluginBase implements ContainerFactoryPluginInterface {

  use MigrateTrait;

  /**
   * The number of ignored rows until we stop the migrate.
   *
   * This assumes that your API can be sorted in a way that the newest
   * changes are listed first.
   *
   * For this to have any effect 'track_changes' source setting must be set to
   * true and you must run the migrate with PARTIAL_MIGRATE=1 setting.
   *
   * @var int
   */
  protected const NUM_IGNORED_ROWS_BEFORE_STOPPING = 20;

  /**
   * The http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The entity count.
   *
   * @var int
   */
  protected int $count = 0;

  /**
   * An array of urls to fetch.
   *
   * @var string[]
   */
  protected array $urls = [];

  /**
   * The limit per page.
   *
   * @var int
   */
  protected int $limit = 0;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'OpenAhjo';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['id' => ['type' => 'string']];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    if (!$this->count) {
      $this->count = $this->doCount();
    }
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() : int {
    $source_data = $this->getContent($this->configuration['url']);

    foreach (['limit', 'offset', 'total_count'] as $key) {
      if (!isset($source_data['meta'][$key])) {
        throw new \InvalidArgumentException(sprintf('The "%s" value is missing from meta[].', $key));
      }
    }
    ['limit' => $this->limit, 'total_count' => $count] = $source_data['meta'];

    $totalPages = ceil($count / $this->limit);

    // Limit total pages to N if configured so.
    if (isset($this->configuration['limit_pages'])) {
      $limitPages = (int) $this->configuration['limit_pages'];

      $totalPages = ($totalPages > $limitPages) ? $limitPages : $totalPages;
      $count = ($totalPages * $this->limit);
    }
    return intval($count);
  }

  /**
   * Builds the metadata.
   */
  protected function buildUrls() : self {
    $this->count();
    $currentUrl = UrlHelper::parse($this->configuration['url']);

    for ($i = 0; $i < ($this->count / $this->limit); $i++) {
      $currentUrl['query']['offset'] = $this->limit * $i;

      $this->urls[] = Url::fromUri($currentUrl['path'], [
        'query' => $currentUrl['query'],
        'fragment' => $currentUrl['fragment'],
      ])->toString();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeListIterator() : \Iterator {
    $this->buildUrls();

    $processed = 0;

    foreach ($this->urls as $url) {
      $content = $this->getContent($url);

      if (is_array($content['objects'])) {
        foreach ($content['objects'] as $object) {
          // Skip entire migration once we've reached the number of maximum
          // ignored (not changed) rows.
          // @see static::NUM_IGNORED_ROWS_BEFORE_STOPPING.
          if ($this->isPartialMigrate() && ($this->ignoredRows >= static::NUM_IGNORED_ROWS_BEFORE_STOPPING)) {
            break 2;
          }
          $processed++;

          // Allow number of items to be limited by using an env variable.
          if (($this->getLimit() > 0) && $processed > $this->getLimit()) {
            break 2;
          }
          yield $object;
        }
      }
    }
  }

}
