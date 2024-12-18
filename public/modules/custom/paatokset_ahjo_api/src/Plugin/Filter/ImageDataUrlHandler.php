<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Image Data URL handler' filter.
 *
 * @Filter(
 *   id = "paatokset_image_data_url_handler",
 *   title = @Translation("Paatokset: Image data URL handler"),
 *   description = @Translation("Allows data URLs for images. NOTE: This filter must be run after 'Limit allowed HTML tags and correct faulty HTML' filter."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {},
 *   weight = -10
 * )
 */
final class ImageDataUrlHandler extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) : FilterProcessResult {
    $result = new FilterProcessResult($text);
    $dom = Html::load($text);
    $hasChanges = FALSE;

    /** @var \DOMElement $node */
    foreach ($dom->getElementsByTagName('img') as $node) {
      $hasChanges = TRUE;
      // Nothing to do if image has no src.
      if (!$value = $node->getAttribute('src')) {
        continue;
      }

      if (strpos($value, 'image/') === 0) {
        $node->setAttribute('src', 'data:' . $value);
      }
    }

    if ($hasChanges) {
      $result->setProcessedText(Html::serialize($dom));
    }
    return $result;
  }

}
