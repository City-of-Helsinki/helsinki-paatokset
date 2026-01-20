<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\paatokset_ahjo_api\Entity\Meeting;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests trustee bundle class.
 */
class MeetingTest extends AhjoEntityKernelTestBase {

  /**
   * Tests bundle class.
   */
  public function testBundleClass(): void {
    $meeting = Meeting::create([
      'type' => 'trustee',
      'title' => 'Test meeting',
      'field_meeting_id' => '123',
      'field_meeting_documents' => [
        json_encode([
          'Language' => 'fi',
          'Type' => 'pöytäkirja',
          'NativeId' => 'foo-fi',
        ]),
        json_encode([
          'Language' => 'fi',
          'Type' => 'esityslista',
          'NativeId' => 'bar-fi',
        ]),
        json_encode([
          'Language' => 'sv',
          'Type' => 'esityslista',
          'NativeId' => 'bar-sv',
        ]),
        json_encode([
          'Language' => 'en',
          'Type' => 'esityslista',
        ]),
      ],
    ]);
    $meeting->save();

    $this->assertEquals('123', $meeting->getAhjoId());

    $this->assertNull($meeting->getDocumentFromEntity('invalid-type'));
    $this->assertNull($meeting->getDocumentFromEntity('esityslista', 'en'));
    $this->assertEquals('bar-fi', $meeting->getDocumentFromEntity('esityslista')['NativeId'] ?? '');
    $this->assertEquals('bar-sv', $meeting->getDocumentFromEntity('esityslista', 'sv')['NativeId'] ?? '');
  }

}
