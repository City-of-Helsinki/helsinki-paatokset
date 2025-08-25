<?php

declare(strict_types=1);

namespace Drupal\paatokset\Entity;

use Drupal\node\Entity\Node;

/**
 * A bundle class for Article node.
 */
class Article extends Node {

  /**
   * Gets year when this article was published.
   */
  public function getPublishedYear() : string {
    assert($this->hasField('published_at'));

    if (!$this->get('published_at')->isEmpty()) {
      $timestamp = (int) $this->get('published_at')->value;
    }
    else {
      $timestamp = (int) $this->get('created')->value;
    }

    return date('Y', $timestamp);
  }

}
