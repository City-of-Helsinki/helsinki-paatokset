<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\ExistingSite;

use Drupal\Tests\helfi_api_base\Functional\ExistingSiteTestBase;

/**
 * Test CaseNodeViewController.
 *
 * @group paatokset_ahjo_api
 */
class CaseNodeViewControllerTest extends ExistingSiteTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $test_content = [
      'case' => [
        // Case with finnish and swedish decision.
        [
          'langcode' => 'fi',
          'title' => 'Test case',
          'field_diary_number' => '001',
        ],
        // Case with only finnish decision.
        [
          'langcode' => 'fi',
          'title' => 'Test case',
          'field_diary_number' => '002',
        ],
        // Case with only swedish decision.
        [
          'langcode' => 'fi',
          'title' => 'Test case',
          'field_diary_number' => '003',
        ],
        // Case with no decisions.
        [
          'langcode' => 'fi',
          'title' => 'Test case',
          'field_diary_number' => '004',
        ],
      ],
      'decision' => [
        [
          'langcode' => 'fi',
          'title' => 'Test case',
          'field_diary_number' => '001',
          'field_decision_native_id' => '{001-decision-fi}',
        ],
        [
          'langcode' => 'sv',
          'title' => 'Test case',
          'field_diary_number' => '001',
          'field_decision_native_id' => '{001-decision-sv}',
        ],
        [
          'langcode' => 'fi',
          'title' => 'Test case',
          'field_diary_number' => '002',
          'field_decision_native_id' => '{002-decision-fi}',
        ],
        [
          'langcode' => 'sv',
          'title' => 'Test case',
          'field_diary_number' => '003',
          'field_decision_native_id' => '{003-decision-sv}',
        ],
      ],
    ];

    // Create test content.
    foreach ($test_content as $contentType => $values) {
      foreach ($values as $value) {
        $this->createNode(array_merge([
          'status' => 1,
          'type' => $contentType,
        ], $value));
      }
    }
  }

  /**
   * Assert that page has expected `<link rel="canonical" href="..." />` tag.
   */
  private function assertCanonicalTag(string $expected): void {
    $element = $this->assertSession()->elementAttributeExists('xpath', "//link[@rel='canonical']", 'href');
    $actual = (string) $element->getAttribute('href');
    $this->assertStringEndsWith($expected, $actual);
  }

  /**
   * Test that canonical url is set correctly.
   *
   * @param string $url
   *   Request is made to this url.
   * @param string $expected
   *   The expected canonical tag.
   *
   * @dataProvider canonicalUrlData
   */
  public function testCanonicalUrl(string $url, string $expected): void {
    $pathParts = explode('/', trim($url, '/'));

    // The language code should be the first item.
    $langcode = array_shift($pathParts);
    $this->assertTrue(in_array($langcode, ['fi', 'sv', 'en']));

    // Rebuild the url without the langcode.
    $url = '/' . implode('/', $pathParts);

    // Remove query from the url, create options array from the query.
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);
    $options = [];

    if (!empty($query)) {
      parse_str($query, $result);
      $options['query'] = $result;
    }

    // Make the request.
    $this->drupalGetWithLanguage($path, $langcode, $options);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertCanonicalTag($expected);
  }

  /**
   * Data provider for canonical url tests.
   *
   * @return array
   *   Test data.
   */
  private function canonicalUrlData(): array {
    // Format ['url', 'expected canonical url'].
    return [
      // Case has no translation or decisions, use case url in current language.
      ['/sv/arende/004', '/sv/arende/004'],
      // Case has no translation or decisions, use case url in current language.
      ['/fi/asia/004', '/fi/asia/004'],
      // Case has invalid decisions, use case url in current language.
      // fixme: should this link to default decision?
      ['/sv/arende/003?beslut=invalid', '/sv/arende/003'],
      // Case must own the decision.
      ['/sv/arende/003?beslut=001-decision-fi', '/sv/arende/003'],
      // Generate url from decision when using query parameter.
      ['/fi/asia/002?paatos=002-decision-fi', '/fi/asia/002/002-decision-fi'],
      // Generate url from decision when using query parameter.
      ['/sv/arende/003?beslut=003-decision-sv', '/sv/arende/003/003-decision-sv'],
      // Default decision is used (only fi available).
      ['/sv/arende/002', '/fi/asia/002/002-decision-fi'],
      // Default prefers current language (sv -> sv).
      ['/sv/arende/001', '/sv/arende/001/001-decision-sv'],
      // Default prefers finnish (no en, both fi and sv available).
      ['/en/case/001', '/fi/asia/001/001-decision-fi'],
      // Decision canonical url.
      ['/fi/asia/001/001-decision-fi', '/fi/asia/001/001-decision-fi'],
      // Untranslated decision should use actual language of the decision.
      ['/en/case/001/001-decision-fi', '/fi/asia/001/001-decision-fi'],
      // Untranslated decision should use actual language of the decision.
      ['/en/case/001/001-decision-sv', '/sv/arende/001/001-decision-sv'],
    ];
  }

}
