<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;

/**
 * Bundle class for meetings.
 */
class Meeting extends Node implements AhjoUpdatableInterface {

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
    return Url::fromRoute('paatokset_ahjo_proxy.meetings_single', [
      'id' => $this->getAhjoId(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getAhjoId(): string {
    return $this->get('field_meeting_id')->getString();
  }

  /**
   * {@inheritDoc}
   */
  public static function getAhjoEndpoint(): string {
    return 'meetings';
  }

}
