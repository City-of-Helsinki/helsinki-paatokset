<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\AhjoOpenId;

/**
 * Builds inline status messages.
 *
 * Requires \Drupal\Core\StringTranslation\StringTranslationTrait.
 */
trait StatusMessageTrait {

  /**
   * Builds an inline status message.
   *
   * @param 'status'|'warning'|'error' $type
   *   Message type.
   * @param \Stringable|string $message
   *   The message.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  protected function statusMessage(string $type, \Stringable|string $message): array {
    return [
      '#theme' => 'status_messages',
      '#message_list' => [$type => [$message]],
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'warning' => $this->t('Warning message'),
        'error' => $this->t('Error message'),
      ],
    ];
  }

}
