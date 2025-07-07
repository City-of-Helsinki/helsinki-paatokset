<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paatokset_ahjo_api\Service\DefaultTextProcessor;

/**
 * Tests default text processor.
 *
 * @group paatokset_ahjo_api
 */
class DefaultTextProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'paatokset_ahjo_api',
  ];

  /**
   * Tests default text processor method.
   */
  public function testDefaultTextProcessor(): void {
    $defaultText = [
      'value' => 'Some default text',
    ];

    $emptyProcessedArray = [
      '#type' => 'processed_text',
      '#text' => '',
      '#format' => 'full_html',
    ];

    $processedArray = $emptyProcessedArray;
    $processedArray['#text'] = $defaultText['value'];

    $processorService = $this->container->get(DefaultTextProcessor::class);
    $this->assertEquals($emptyProcessedArray, $processorService->process([]));
    $this->assertEquals($processedArray, $processorService->process($defaultText));
  }

}
