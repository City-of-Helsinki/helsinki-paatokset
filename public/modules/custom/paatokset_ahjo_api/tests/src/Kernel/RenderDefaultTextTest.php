<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests default text renderer.
 *
 * @group paatokset_ahjo_api
 */
class RenderDefaultTextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'paatokset_ahjo_api',
    'helfi_api_base',
  ];

  /**
   * Tests default text render array conversion.
   */
  public function testRenderDefaultText(): void {
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

    $this->assertEquals($emptyProcessedArray, _paatokset_ahjo_api_render_default_text([]));
    $this->assertEquals($processedArray, _paatokset_ahjo_api_render_default_text($defaultText));
  }

}
