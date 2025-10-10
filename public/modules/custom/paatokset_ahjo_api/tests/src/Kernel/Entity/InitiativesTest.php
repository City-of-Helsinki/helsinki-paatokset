<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\helfi_api_base\Entity\RemoteEntityInterface;
use Drupal\node\Entity\NodeType;
use Drupal\paatokset_ahjo_api\Entity\Initiative;
use Drupal\Tests\helfi_api_base\Kernel\Entity\Access\RemoteEntityAccessTestBase;

/**
 * Kernel tests for document entity.
 */
class InitiativesTest extends RemoteEntityAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_ahjo_api',
    'system',
    'node',
    'user',
    'field',
    'file',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUpRemoteEntity(): RemoteEntityInterface {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    NodeType::create([
      'type' => 'trustee',
      'name' => 'Trustee',
    ])->save();

    $this->installEntitySchema('ahjo_initiative');

    return Initiative::create([
      'title' => 'test',
      'date' => (new \DateTimeImmutable())->getTimestamp(),
      'uri' => 'https://example.com/file.pdf',
      'trustee_nid' => '1',
    ]);
  }

}
