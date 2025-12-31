<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Plugin\DebugDataItem;

use Drupal\paatokset_ahjo_api\AhjoOpenId\AhjoOpenId;
use Drupal\paatokset_ahjo_api\Plugin\DebugDataItem\AhjoToken;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\paatokset_ahjo_api\Plugin\DebugDataItem\AhjoToken
 */
#[Group('paatokset_ahjo_api')]
class AhjoTokenTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param bool $expectedReturnValue
   *   The expected return value.
   *
   * @return \Drupal\paatokset_ahjo_api\Plugin\DebugDataItem\AhjoToken
   *   The SUT.
   */
  private function getSut(bool $expectedReturnValue): AhjoToken {
    $openId = $this->prophesize(AhjoOpenId::class);
    $openId->checkAuthToken()
      ->shouldBeCalled()
      ->willReturn($expectedReturnValue);
    return new AhjoToken([], '', '', $openId->reveal());
  }

  /**
   * Tests a failed check().
   */
  public function testFailedCheck(): void {
    $this->assertFalse($this->getSut(FALSE)->check());
  }

  /**
   * Test successful check().
   */
  public function testCheck(): void {
    $this->assertTrue($this->getSut(TRUE)->check());
  }

}
