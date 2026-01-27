<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Decisions;

use Drupal\Component\Utility\Html;
use Drupal\paatokset_ahjo_api\Decisions\DTO\MoreInfoDetails;
use Drupal\paatokset_ahjo_api\Decisions\DTO\PresenterInfo;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SisaltoSection;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SignatureInfo;
use Drupal\paatokset_ahjo_api\Decisions\DTO\Signer;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SignerRole;

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
   * Get main content sections.
   *
   * The main content is presented in the following format:
   *
   * ```
   * <section class="SisaltoSektioToisto">
   *   <div class="SisaltoSektio">
   *     <h3 class="SisaltoOtsikko">Päätös</h3>
   *     <p>Kaupunginhallitus päätti panna asian pöydälle.</p>
   *   </div>
   *   <div class="SisaltoSektio">
   *     ...arbitrary HTML content...
   *   </div>
   * </section>
   * ```
   *
   * The legacy format does not have the `SisaltoSektioToisto` wrapper.
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
   * Get signature information (decisionmaker).
   *
   * ```
   * <section class="SahkoinenAllekirjoitusSektio">
   *   <p class="SahkoisestiAllekirjoitettuTeksti">Allekirjoitettu.</p>
   *   <p>
   *     <div class="Puheenjohtajanimi">Aku Ankka</div>
   *     <div class="Puheenjohtajaotsikko">tehtävänimike</div>
   *   </p>
   *   <p>
   *     <div class="Poytakirjanpitajanimi">Roope Ankka</div>
   *     <div class="Poytakirjanpitajaotsikko">tehtävänimike</div>
   *   </p>
   * </section>
   * ```
   *
   * In legacy format, the wrapper is div instead of section.
   *
   * @return \Drupal\paatokset_ahjo_api\Decisions\DTO\SignatureInfo|null
   *   Signature info, or NULL if not found.
   */
  public function getSignatureInfo(): ?SignatureInfo {
    $signers = [];

    foreach (SignerRole::cases() as $role) {
      $name = $this->xpath->query("//*[contains(@class, '{$role->getNameSelector()}')]");
      if ($name->length > 0) {
        $title = $this->xpath->query("//*[contains(@class, '{$role->getRoleSelector()}')]");
        $signers[$role->name] = new Signer(
          name: trim($name->item(0)->textContent),
          title: $title->length > 0 ? ucfirst(trim($title->item(0)->textContent)) : '',
        );
      }
    }

    return $signers ? new SignatureInfo($signers) : NULL;
  }

  /**
   * Get more info details.
   *
   * More info details are presented in the following format:
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
   * Get content sections.
   *
   * Parses sections with class 'SisaltoSektio' from the HTML content.
   * Each section contains a heading (h3) and content.
   *
   * @return \Drupal\paatokset_ahjo_api\Decisions\DTO\SisaltoSection[]
   *   Array of sections.
   */
  public function getSections(): array {
    $sections = $this->xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' SisaltoSektio ')]");
    if ($sections->length < 1) {
      return [];
    }

    $output = [];
    foreach ($sections as $node) {
      if (!$node instanceof \DOMElement) {
        continue;
      }

      $headingNodes = $this->xpath->query(".//*[contains(@class, 'SisaltoOtsikko')]", $node);
      $heading = $headingNodes?->item(0)?->nodeValue;

      // Remove headings from the content.
      foreach ($headingNodes as $headingNode) {
        $headingNode->parentNode->removeChild($headingNode);
      }

      $output[] = new SisaltoSection($heading, self::getInnerHtml($node));
    }

    return $output;
  }

  /**
   * Get modification info (Muokkaustieto).
   */
  public function getModificationInfo(): ?string {
    $node = $this->xpath->query("//*[contains(@class, 'Muokkaustieto')]")->item(0);
    return $node?->nodeValue;
  }

  /**
   * Get appeal info (Muutoksenhakuohjeet).
   *
   * Format:
   *
   * ```
   * <section class="MuutoksenhakuohjeetSektio">
   *   <h3 class="MuutoksenhakuohjeetOtsikko">MUUTOKSENHAKUOHJEET</h3>
   *   <h4>VALITUSOSOITUS</h4>
   *   <p>Tähän päätökseen haetaan muutosta kunnallisvalituksella.</p>
   *   ...arbitrary HTML content...
   * </section>
   * ```
   *
   * Legacy format is missing the section wrapper. We detect the title
   * and get everything until the next section.
   *
   * ```
   * <h3 class="MuutoksenhakuOtsikko">Muutoksenhaku</h3>
   * <h4>VALITUSOSOITUS</h4>
   * <p>Tähän päätökseen haetaan muutosta kunnallisvalituksella.</p>
   * ...arbitrary HTML content...
   * <h3 class="*Otsikko">Next section title</h3>
   * ```
   */
  public function getAppealInfo(): ?string {
    // Try to find a section wrapper.
    foreach ($this->xpath->query("//*[contains(@class, 'MuutoksenhakuohjeetSektio')]") as $section) {
      // Remove section heading. We customize the heading in Drupal.
      foreach ($this->xpath->evaluate('//*[contains(@class, "MuutoksenhakuohjeetOtsikko")]', $section) as $el) {
        $el->parentNode->removeChild($el);
      }

      return self::getInnerHtml($section);
    }

    // Fall back to legacy format.
    $heading = $this->xpath->query("//*[contains(@class, 'MuutoksenhakuOtsikko')]");

    return self::getHtmlContentUntilBreakingElement($heading);
  }

  /**
   * Get presenter info (EsittelijaTiedot).
   *
   * Format:
   *
   * ```
   * <section class="EsittelijaTiedot">
   *   <h3 class="EsittelijaTiedot">Esittelijä</h3>
   *   <div></div>
   *   <div>Kaupunginhallitus</div>
   * </section>
   * ```
   *
   * Legacy format is missing the `EsittelijaTiedot` section wrapper.
   *
   * ```
   * <h3 class="EsittelijaTiedot">Esittelijä</h3>
   * <div>Pormestari</div>
   * <div>Aku Ankka</div>
   * ```
   */
  public function getPresenterInfo(): ?PresenterInfo {
    $sections = $this->xpath->query("//section[contains(@class, 'EsittelijaTiedot')]/*[not(self::h3)]");

    $content = '';
    if ($sections->length > 0) {
      foreach ($sections as $node) {
        $content .= $node->ownerDocument->saveHTML($node);
      }
    }
    else {
      // Legacy format:
      $content = self::getHtmlContentUntilBreakingElement(
        $this->xpath->query("//*[contains(@class, 'EsittelijaTiedot')]")
      );
    }

    if (!$content) {
      return NULL;
    }

    // Replace all HTML tags with #.
    $result = preg_replace('/<[^>]+>/', '#', $content);

    // Remove leading and trailing # or whitespace.
    $result = trim($result, "# \t\n\r\0\x0B");

    // Collapse multiple # into one and normalize spacing around them.
    $result = preg_replace('/#+/', '#', $result);
    $result = preg_replace('/\s*#\s*/', ' # ', $result);

    // Split into array.
    $parts = array_map('trim', explode('#', $result));

    $title = ucfirst($parts[0] ?? '') ?: NULL;
    $name = ucfirst($parts[1] ?? '') ?: NULL;

    return ($title || $name) ? new PresenterInfo($title, $name) : NULL;
  }

  /**
   * Get inner HTML of a DOM element.
   */
  private static function getInnerHtml(\DOMElement $node): string {
    $content = [];
    foreach ($node->childNodes as $child) {
      $content[] = $node->ownerDocument->saveHTML($child);
    }

    return implode('', $content);
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
  private static function getHtmlContentUntilBreakingElement(\DOMNodeList $list): ?string {
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
