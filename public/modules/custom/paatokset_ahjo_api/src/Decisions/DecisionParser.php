<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Decisions;

use Drupal\Component\Utility\Html;
use Drupal\paatokset_ahjo_api\Decisions\DTO\MoreInfoDetails;

/**
 * Parser for decision HTML content.
 */
readonly class DecisionParser {

  /**
   * XPath object.
   */
  public \DOMXPath $xpath;

  private function __construct(
    public \DOMDocument $dom,
  ) {
    $this->xpath = new \DOMXPath($this->dom);
  }

  /**
   * Parse decision HTML content.
   *
   * The HTML content can be NULL if the decision is not set yet. If so, the
   * parser uses an empty DOMDocument. No results will be returned in this case.
   */
  public static function parse(string|NULL $html): self {
    return new self($html ? Html::load($html) : new \DOMDocument());
  }

  /**
   * Get main content sections (SisaltoSektio).
   *
   * New format has a wrapper element `SisaltoSektioToisto` containing
   * all `SisaltoSektio` sections. Legacy format has individual
   * `SisaltoSektio` divs without a wrapper.
   *
   * @return string|null
   *   HTML content of the main sections, or NULL if not found.
   */
  public function getMainContent(): ?string {
    // Try new format first: query the wrapper element.
    $wrapper = $this->xpath->query("//*[contains(@class, 'SisaltoSektioToisto')]");
    if ($wrapper->length > 0) {
      return $wrapper->item(0)->ownerDocument->saveHTML($wrapper->item(0));
    }

    // Fall back to legacy format: query individual sections.
    $sections = $this->xpath->query("//*[contains(@class, 'SisaltoSektio')]");
    if ($sections->length === 0) {
      return NULL;
    }

    $content = '';
    foreach ($sections as $section) {
      $content .= $section->ownerDocument->saveHTML($section);
    }

    return $content ?: NULL;
  }

  /**
   * Get more info details.
   *
   * More info details presented in the following format:
   *
   * ```
   * phpcs:disable
   * <section class="Lisatiedot">
   *   <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
   *   <p>
   *     <span class="LisatiedonantajanNimi">Etunimi Sukunimi</span>, <span class="LisatiedonantajanTitteli">Titteli</span><br>
   *     <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">123 4567</span>, <span class="LisatiedonantajanSahkoposti">etunimi.sukunimi@hel.fi</span>
   *   </p>
   * </section>
   * phpcs:enable
   * ```
   *
   * The legacy format does not have all the class names present. We
   * need to support both until all decisions have been updated.
   *
   * Legacy format:
   * ...
   * <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
   * <p>Etunimi Sukunimi, kaupunginsihteeri, puhelin: 09 310 12345
   * <div>etunimi.sukunimi@hel.fi</div>
   * </p>
   * ...
   */
  public function getMoreInfoDetails(): ?MoreInfoDetails {
    // Try the new format first.
    if ($result = $this->parseMoreInfoNewFormat()) {
      return $result;
    }

    // Fall back to legacy format parsing.
    // @todo re-fetch all decisions so we can remove the legacy parsing.
    return $this->parseMoreInfoLegacyFormat();
  }

  /**
   * Parse more info using the new format with class names.
   *
   * New format has specific class names on span elements:
   * - LisatiedonantajanNimi: name
   * - LisatiedonantajanTitteli: title
   * - LisatiedonantajanPuhelin: phone
   * - LisatiedonantajanSahkoposti: email.
   */
  private function parseMoreInfoNewFormat(): ?MoreInfoDetails {
    $name = $this->xpath->query("//*[contains(@class, 'LisatiedonantajanNimi')]");
    if ($name->length === 0) {
      return NULL;
    }

    $title = $this->xpath->query("//*[contains(@class, 'LisatiedonantajanTitteli')]");
    $phone = $this->xpath->query("//*[contains(@class, 'LisatiedonantajanPuhelin')]");
    $email = $this->xpath->query("//*[contains(@class, 'LisatiedonantajanSahkoposti')]");

    return new MoreInfoDetails(
      name: trim($name->item(0)->textContent),
      title: $title->length > 0 ? ucfirst(trim($title->item(0)->textContent)) : '',
      phone: $phone->length > 0 ? trim($phone->item(0)->textContent) : NULL,
      email: $email->length > 0 ? trim($email->item(0)->textContent) : NULL,
    );
  }

  /**
   * Parse more info using legacy format (without class names).
   */
  private function parseMoreInfoLegacyFormat(): ?MoreInfoDetails {
    $more_info = $this->xpath->query("//*[contains(@class, 'LisatiedotOtsikko')]");
    $more_info_content = NULL;
    if ($more_info->length > 0) {
      $more_info_content = self::getHtmlContentUntilBreakingElement($more_info);
    }

    if (!$more_info_content) {
      return NULL;
    }

    // Replace all HTML tags with commas.
    $result = preg_replace('/<[^>]+>/', ',', $more_info_content);

    // Remove leading and trailing commas/spaces.
    $result = trim($result, ", \t\n\r\0\x0B");

    // Remove multiple subsequent commas and clean spacing.
    $result = preg_replace('/,+/', ',', $result);
    $result = preg_replace('/\s*,\s*/', ', ', $result);

    // Split into array.
    $parts = array_map('trim', explode(',', $result));

    // Make sure we have a phone number at index 2.
    $phone = NULL;
    if (isset($parts[2])) {
      // Strip non-digit characters from start and end, keep spaces between.
      $phone = preg_replace('/(?:^\D+)|(?:\D+$)/', '', $parts[2]);
      // Only keep phone if it has meaningful content.
      $phone = $phone !== '' ? $phone : NULL;
    }

    return new MoreInfoDetails(
      name: $parts[0] ?? '',
      title: isset($parts[1]) ? ucfirst($parts[1]) : '',
      phone: $phone,
      email: $parts[3] ?? NULL,
    );
  }

  /**
   * Get HTML content from first heading until next heading.
   *
   * @param \DOMNodeList $list
   *   Xpath query results.
   *
   * @return string|null
   *   HTML content as string, or NULL if content is empty.
   */
  public static function getHtmlContentUntilBreakingElement(\DOMNodeList $list): ?string {
    $output = NULL;
    if ($list->length < 1) {
      return NULL;
    }

    $current_item = $list->item(0);
    while ($current_item->nextSibling instanceof \DOMNode) {
      // Iterate over to next sibling. This skips the first one.
      $current_item = $current_item->nextSibling;

      if (!$current_item instanceof \DOMElement) {
        continue;
      }

      // H3 with a class is considered a breaking element.
      if ($current_item->nodeName === 'h3' && !empty($current_item->getAttribute('class'))) {
        break;
      }
      // More information section should stop before the signatures.
      if ($current_item->getAttribute('class') === 'SahkoinenAllekirjoitusSektio') {
        break;
      }

      // Strip empty nodes. Ahjo HTML seems to contain a lot of <p></p> tags.
      if (empty($current_item->nodeValue) && $current_item->nodeName !== 'img') {
        continue;
      }

      $output .= $current_item->ownerDocument->saveHTML($current_item);
    }

    return $output;
  }

}
