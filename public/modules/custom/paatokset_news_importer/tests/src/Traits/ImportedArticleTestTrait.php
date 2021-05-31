<?php

declare(strict_types = 1);

namespace Drupal\Tests\paatokset_news_importer\Traits;

/**
 * Provides shared functionality for Imported artcile tests.
 */
trait ImportedArticleTestTrait {

  /**
   * Create mock XML responses.
   *
   * @param int $count
   *   The number of response items.
   *
   * @return string
   *   The generated XML String
   */
  private function createResponseXml($count) : string {
    $contentSchema = "http://purl.org/rss/1.0/modules/content/";
    $xml = new \SimpleXmlElement('<rss version="2.0" xmlns:content="' . $contentSchema . '"></rss>');
    $channel = $xml->addChild('channel');

    for ($i = 0; $i < $count; $i++) {
      $child = $channel->addChild('item');
      $child->addChild('title', 'Title for item ' . ($i + 1) . '.');
      $child->addChild('guid', (string) ($i + 1));
      $child->addChild('pubDate', '19 May 2021 23:36:28 +0300');
      $child->addChild('content:encoded', 'Content for item ' . ($i + 1) . '.', $contentSchema);
      $child->addChild('description', '<img src="image.com"/><br/>Description for item ' . ($i + 1) . '.');
    }

    return $xml->asXML();
  }

}
