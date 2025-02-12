<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_datapumppu\Kernel;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_datapumppu\Entity\Statement;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests cache tag invalidation.
 *
 * @group paatokset_datapumppu
 */
class CacheTagInvalidationTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paatokset_datapumppu',
    'node',
    'user',
    'system',
    'datetime',
  ];

  /**
   * {@inheritDoc}
   */
  public function testCacheTagInvalidation(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paatokset_statement');

    $cacheTagInvalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $cacheTagInvalidator
      ->invalidateTags(Argument::containing('trustee_statements:123'))
      ->shouldBeCalledTimes(1);

    $this->container->set(CacheTagsInvalidatorInterface::class, $cacheTagInvalidator->reveal());

    Statement::create([
      'title' => 'Foobar',
      'speaker' => [
        'target_id' => '123',
      ],
      'speech_type' => 1,
      'video_url' => 'https://youtube.com',
      'start_time' => 0,
      'duration' => 300,
      'case_number' => 1,
      'meeting_id' => 'meeting1',
    ])->save();

    Statement::create([
      'title' => 'Foobar',
      'speaker' => NULL,
      'speech_type' => 1,
      'video_url' => 'https://youtube.com',
      'start_time' => 0,
      'duration' => 300,
      'case_number' => 1,
      'meeting_id' => 'meeting1',
    ])->save();

  }

}
