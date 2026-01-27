<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paatokset_ahjo_api\Entity\ConfidentialityInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;

/**
 * Tests decision bundle class.
 */
class DecisionTest extends AhjoEntityKernelTestBase {

  /**
   * Tests bundle class.
   */
  public function testBundleClass(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_diary_number' => 'test-diary-number',
      'field_decision_native_id' => 'test-native-id',
      'field_decision_case_title' => 'test-case-title',
      'field_dm_org_name' => 'test-dm-org-name',
      'field_policymaker_id' => '123',
    ]);
    $this->assertInstanceOf(Decision::class, $decision);

    $this->assertEquals('test-native-id', $decision->getNativeId());
    $this->assertEquals('test-dm-org-name', $decision->getDecisionMakerOrgName());

    $this->assertEmpty($decision->getPolicymaker('en'));

    $storage->create([
      'type' => 'policymaker',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test policymaker',
      'field_policymaker_id' => '123',
    ])->save();

    $this->assertEquals('123', $decision->getPolicymakerId());
    $this->assertNotEmpty($decision->getPolicymaker('en'));

    $this->assertEmpty($decision->getCase());
    $this->assertEquals('test-case-title', $decision->getDecisionHeading());

    $storage->create([
      'type' => 'case',
      'status' => '1',
      'langcode' => 'en',
      'title' => 'Test case',
      'field_full_title' => 'test-full-title',
      'field_diary_number' => 'test-diary-number',
    ])->save();

    $this->assertEquals('test-diary-number', $decision->getDiaryNumber());
    $this->assertNotEmpty($decision->getCase());
    $this->assertEquals('test-case-title', $decision->getDecisionHeading());
  }

  /**
   * Tests decision section formatting.
   */
  public function testDecisionSectionFormatting(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_decision_section' => NULL,
    ]);

    $this->assertInstanceOf(Decision::class, $decision);
    $this->assertEquals(NULL, $decision->getFormattedDecisionSection());

    $decision->set('field_decision_section', '123');
    $this->assertEquals('§ 123', $decision->getFormattedDecisionSection());

    // Invalid json.
    $decision->set('field_decision_record', json_encode([
      'AgendaPoint' => NULL,
    ]));
    $this->assertEquals('§ 123', $decision->getFormattedDecisionSection());

    // Valid json.
    $decision->set('field_decision_record', json_encode([
      'AgendaPoint' => '13',
    ]));
    $this->assertEquals('Case 13. / 123 §', $decision->getFormattedDecisionSection());
  }

  /**
   * Tests decision attachments.
   */
  public function testDecisionAttachments(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_decision_attachments' => file_get_contents(__DIR__ . '/../../../fixtures/decision-attachments.json'),
    ]);

    $this->assertInstanceOf(Decision::class, $decision);

    $attachments = $decision->getAttachments();

    $this->assertArrayHasKey('publicity_reason', $attachments);
    $this->assertCount(1, $attachments['items']);
    $this->assertEmpty(array_diff(['number', 'title', 'publicity_class', 'file_url'], array_keys($attachments['items'][0])));
  }

  /**
   * Tests decision voting results parsing.
   */
  public function testDecisionVotingResults(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
    ]);

    $this->assertInstanceOf(Decision::class, $decision);
    $this->assertEquals(NULL, $decision->getVotingResults());

    $decision->set('field_voting_results', file_get_contents(__DIR__ . '/../../../fixtures/decision-vote-results.json'));
    $vote = $decision->getVotingResults();
    $byParty = $vote[0]['by_party'] ?? NULL;
    $findParty = fn (string $name) => array_find($byParty, fn ($result) => $result['Name'] === $name) ?? [];

    $this->assertEquals(2, $findParty('Sosiaalidemokraattinen valtuustoryhmä')['Ayes'] ?? NULL);
    $this->assertEquals(2, $findParty('Perussuomalaisten valtuustoryhmä')['Noes'] ?? NULL);
  }

  /**
   * Tests decision pdf link.
   */
  public function testDecisionPdf(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_decision_record' => file_get_contents(__DIR__ . '/../../../fixtures/decision-record.json'),
      'field_organization_type' => OrganizationType::TRUSTEE_TYPES[0],
    ]);

    $this->assertInstanceOf(Decision::class, $decision);

    // Is trustee, but minutes filed is empty.
    $decision->set('field_decision_minutes_pdf', json_encode([]));
    $this->assertEquals('https://example.com/test-file.pdf', $decision->getDecisionPdf());

    // Is trustee.
    $decision->set('field_decision_minutes_pdf', file_get_contents(__DIR__ . '/../../../fixtures/decision-minutes.json'));
    $this->assertEquals('https://example.com/minutes.pdf', $decision->getDecisionPdf());

    // Is not trustee.
    $decision->set('field_organization_type', 'Something else');
    $this->assertEquals('https://example.com/test-file.pdf', $decision->getDecisionPdf());
  }

  /**
   * Tests decision content parsing.
   *
   * Decision HTML format was changed in 2026. We need to support
   * the old format until all decisions are re-fetched.
   */
  public function testLegacyParseContent(): void {
    $decision = Decision::create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_organization_type' => 'Viranhaltija',
    ]);

    // Some decisions in production have 'null' on history field.
    $decision->set('field_decision_history', 'null');
    $decision->set('field_decision_content', file_get_contents(__DIR__ . '/../../../fixtures/decision-content.html'));
    $decision->set('field_diary_number', 'HEL-2024-009117');
    $decision->set('field_hide_decision_content', '0');
    $content = $decision->parseContent();

    $this->assertEquals('Jane Doe', $content['more_info']['content']['name']['#plain_text'] ?? '');
    $this->assertEquals('mailto:john.doe@example.com', $content['more_info']['content']['email']?->getUrl()->toString() ?? '');
    $this->assertEquals('tel:0-9123', $content['more_info']['content']['phone']?->getUrl()->toString() ?? '');
    $this->assertEquals('John Doe', $content['presenter_info']['content']['name']['#plain_text'] ?? '');
    $this->assertEquals('Aku Ankka', $content['signature_info']['content']['name']['#plain_text'] ?? '');
    $this->assertEquals('Tilapäällikkö', $content['signature_info']['content']['title']['#plain_text'] ?? '');

    // Empty <p> tags are stripped from accordions.
    $this->assertStringContainsString('<p></p>', file_get_contents(__DIR__ . '/../../../fixtures/decision-content.html'));
    foreach ($content['accordions'] as $accordion) {
      $this->assertStringNotContainsString('<p></p>', $accordion['content']['#text'] ?? '');
    }
  }

  /**
   * Tests decision content parsing.
   */
  public function testParseContent(): void {
    $decision = Decision::create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_organization_type' => 'Viranhaltija',
    ]);

    $this->assertEmpty($decision->parseContent());

    // Hide decision.
    $this->config('paatokset_ahjo_api.default_texts')->set('hidden_decisions_text.value', 'test-message')->save();
    $decision->set('field_hide_decision_content', '1');
    $this->assertEquals('test-message', $decision->parseContent()['message']['#markup'] ?? NULL);

    // Some decisions in production have 'null' on history field.
    $decision->set('field_decision_history', 'null');
    $decision->set('field_decision_content', file_get_contents(__DIR__ . '/../../../fixtures/decision-content-new.html'));
    $decision->set('field_decision_motion', file_get_contents(__DIR__ . '/../../../fixtures/decision-motion.html'));
    $decision->set('field_diary_number', 'HEL-2024-009117');
    $decision->set('field_hide_decision_content', '0');
    $content = $decision->parseContent();

    $this->assertEquals('Jane Doe', $content['more_info']['content']['name']['#plain_text'] ?? '');
    $this->assertEquals('mailto:john.doe@example.com', $content['more_info']['content']['email']?->getUrl()->toString() ?? '');
    $this->assertEquals('tel:0-9123', $content['more_info']['content']['phone']?->getUrl()->toString() ?? '');
    $this->assertEquals('Aku Ankka', $content['signature_info']['content']['name']['#plain_text'] ?? '');
    $this->assertEquals('Tehtävänimike', $content['signature_info']['content']['title']['#plain_text'] ?? '');

    // Empty <p> tags are stripped from accordions.
    $this->assertStringContainsString('<p></p>', file_get_contents(__DIR__ . '/../../../fixtures/decision-content-new.html'));
    foreach ($content['accordions'] as $accordion) {
      $this->assertStringNotContainsString('<p></p>', $accordion['content']['#text'] ?? '');
    }
  }

  /**
   * Tests decision confidentiality markings.
   */
  public function testConfidentiality(): void {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $decision = $storage->create([
      'type' => 'decision',
      'title' => 'Test decision',
      'field_decision_content' => file_get_contents(__DIR__ . '/../../../fixtures/confidential-decision-content.html'),
    ]);

    $this->assertInstanceOf(ConfidentialityInterface::class, $decision);
    $this->assertTrue($decision->isConfidential());
    $this->assertEquals('SalassapidonPerustelut', $decision->getConfidentialityReason());
  }

}
