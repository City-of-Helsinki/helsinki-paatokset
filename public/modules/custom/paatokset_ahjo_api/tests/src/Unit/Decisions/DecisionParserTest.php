<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Decisions;

use Drupal\Core\Link;
use Drupal\paatokset_ahjo_api\Decisions\DecisionParser;
use Drupal\paatokset_ahjo_api\Decisions\DTO\MoreInfoDetails;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SignatureInfo;
use Drupal\paatokset_ahjo_api\Decisions\DTO\Signer;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SignerRole;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SisaltoSection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DecisionParser.
 */
#[Group('paatokset_ahjo_api')]
class DecisionParserTest extends UnitTestCase {

  /**
   * Tests parsing more info with new format (class names on spans).
   */
  public function testGetMoreInfoDetailsNewFormat(): void {
    $html = <<<HTML
      <section class="Lisatiedot">
        <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
        <p>
          <span class="LisatiedonantajanNimi">Etunimi Sukunimi</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
          <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">09 310 12345</span>, <span class="LisatiedonantajanSahkoposti">etunimi.sukunimi@hel.fi</span>
        </p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMoreInfoDetails();

    $this->assertInstanceOf(MoreInfoDetails::class, $result);
    $this->assertEquals('Etunimi Sukunimi', $result->name);
    $this->assertEquals('Titteli', $result->title);
    $this->assertEquals('09 310 12345', $result->phone);
    $this->assertInstanceOf(Link::class, $result->getPhoneLink());
    $this->assertEquals('09 310 12345', $result->getPhoneLink()->getText());
    $this->assertEquals('tel:0931012345', $result->getPhoneLink()->getUrl()->getUri());
    $this->assertEquals('etunimi.sukunimi@hel.fi', $result->email);
    $this->assertInstanceOf(Link::class, $result->getEmailLink());
    $this->assertEquals('etunimi.sukunimi@hel.fi', $result->getEmailLink()->getText());
    $this->assertEquals('mailto:etunimi.sukunimi@hel.fi', $result->getEmailLink()->getUrl()->getUri());
  }

  /**
   * Tests parsing more info with legacy format (plain text without classes).
   */
  public function testGetMoreInfoDetailsLegacyFormat(): void {
    $html = <<<HTML
      <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
      <p>Etunimi Sukunimi, kaupunginsihteeri, puhelin: 09 310 12345
      <div>etunimi.sukunimi@hel.fi</div>
      </p>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMoreInfoDetails();

    $this->assertInstanceOf(MoreInfoDetails::class, $result);
    $this->assertEquals('Etunimi Sukunimi', $result->name);
    $this->assertEquals('Kaupunginsihteeri', $result->title);
    $this->assertEquals('09 310 12345', $result->phone);
    $this->assertInstanceOf(Link::class, $result->getPhoneLink());
    $this->assertEquals('09 310 12345', $result->getPhoneLink()->getText());
    $this->assertEquals('tel:0931012345', $result->getPhoneLink()->getUrl()->getUri());
    $this->assertEquals('etunimi.sukunimi@hel.fi', $result->email);
    $this->assertInstanceOf(Link::class, $result->getEmailLink());
    $this->assertEquals('etunimi.sukunimi@hel.fi', $result->getEmailLink()->getText());
    $this->assertEquals('mailto:etunimi.sukunimi@hel.fi', $result->getEmailLink()->getUrl()->getUri());
  }

  /**
   * Tests that NULL is returned when no content exists.
   */
  public function testNoContent(): void {
    $html = '<div>Some other content</div>';

    $parser = DecisionParser::parse($html);

    $this->assertNull($parser->getMoreInfoDetails());
    $this->assertNull($parser->getMainContent());
    $this->assertNull($parser->getSignatureInfo());
    $this->assertNull($parser->getModificationInfo());
    $this->assertEmpty($parser->getSections());
  }

  /**
   * Tests parsing with NULL HTML input.
   */
  public function testParseWithNullHtml(): void {
    $parser = DecisionParser::parse(NULL);

    $this->assertNull($parser->getMoreInfoDetails());
    $this->assertNull($parser->getMainContent());
    $this->assertNull($parser->getSignatureInfo());
    $this->assertNull($parser->getModificationInfo());
    $this->assertEmpty($parser->getSections());
  }

  /**
   * Tests new format with missing optional fields.
   */
  public function testNewFormatWithMissingOptionalFields(): void {
    $html = <<<HTML
      <section class="Lisatiedot">
        <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
        <p>
          <span class="LisatiedonantajanNimi">Only Name</span>
        </p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMoreInfoDetails();

    $this->assertInstanceOf(MoreInfoDetails::class, $result);
    $this->assertEquals('Only Name', $result->name);
    $this->assertEquals('', $result->title);
    $this->assertNull($result->phone);
    $this->assertNull($result->email);
    $this->assertNull($result->getEmailLink());
    $this->assertNull($result->getPhoneLink());
  }

  /**
   * Tests getMainContent with new format (SisaltoSektioToisto wrapper).
   */
  public function testGetMainContentNewFormat(): void {
    $html = <<<HTML
      <section class="SisaltoSektioToisto">
        <div class="SisaltoSektio">
          <h3 class="SisaltoOtsikko">Päätös</h3>
          <div><p>Decision content.</p></div>
        </div>
        <div class="SisaltoSektio">
          <h3 class="SisaltoOtsikko">Käsittely</h3>
          <div><p>Handling content.</p></div>
        </div>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMainContent();

    $this->assertNotNull($result);
    $this->assertStringContainsString('SisaltoSektioToisto', $result);
    $this->assertStringContainsString('Päätös', $result);
    $this->assertStringContainsString('Käsittely', $result);
    $this->assertStringContainsString('Decision content.', $result);
    $this->assertStringContainsString('Handling content.', $result);
  }

  /**
   * Tests getMainContent with legacy format (individual SisaltoSektio divs).
   */
  public function testGetMainContentLegacyFormat(): void {
    $html = <<<HTML
      <div class="paatos">
        <div class="SisaltoSektio">
          <h3 class="SisaltoOtsikko">Päätös</h3>
          <div><p>Decision content.</p></div>
        </div>
        <div class="SisaltoSektio">
          <h3 class="SisaltoOtsikko">Käsittely</h3>
          <div><p>Handling content.</p></div>
        </div>
      </div>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMainContent();

    $this->assertNotNull($result);
    $this->assertStringNotContainsString('SisaltoSektioToisto', $result);
    $this->assertStringContainsString('Päätös', $result);
    $this->assertStringContainsString('Käsittely', $result);
    $this->assertStringContainsString('Decision content.', $result);
    $this->assertStringContainsString('Handling content.', $result);
  }

  /**
   * Tests area code handling for city phone numbers.
   */
  public function testPhoneNumberAreaCode(): void {
    $html = <<<HTML
      <section class="Lisatiedot">
        <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
        <p>
          <span class="LisatiedonantajanNimi">Etunimi Sukunimi</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
          <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">310</span>, <span class="LisatiedonantajanSahkoposti">etunimi.sukunimi@hel.fi</span>
        </p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMoreInfoDetails();

    $this->assertInstanceOf(MoreInfoDetails::class, $result);

    // Area code is added to city phone numbers.
    // Production data contains phone numbers containing only "310".
    $this->assertEquals('09 310', $result->phone);
    // Drupal can't handle short phone numbers. Tests that the bug is mitigated.
    $this->assertEquals('tel:0-9310', $result->getPhoneLink()->getUrl()->getUri());
  }

  /**
   * Tests getSignatureInfo with multiple signers.
   */
  public function testGetSignatureInfoNewFormat(): void {
    $html = <<<HTML
      <section class="SahkoinenAllekirjoitusSektio">
        <p class="SahkoisestiAllekirjoitettuTeksti">Päätös on sähköisesti allekirjoitettu.</p>
        <p>
          <div class="Puheenjohtajanimi">Aku Ankka</div>
          <div class="Puheenjohtajaotsikko">puheenjohtaja</div>
        </p>
        <p>
          <div class="Poytakirjanpitajanimi">Roope Ankka</div>
          <div class="Poytakirjanpitajaotsikko">sihteeri</div>
        </p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getSignatureInfo();

    $this->assertInstanceOf(SignatureInfo::class, $result);
    $this->assertCount(2, $result->signers);

    $chairman = $result->getSigner(SignerRole::CHAIRMAN);
    $this->assertInstanceOf(Signer::class, $chairman);
    $this->assertEquals('Aku Ankka', $chairman->name);
    $this->assertEquals('Puheenjohtaja', $chairman->title);

    $secretary = $result->getSigner(SignerRole::SECRETARY);
    $this->assertInstanceOf(Signer::class, $secretary);
    $this->assertEquals('Roope Ankka', $secretary->name);
    $this->assertEquals('Sihteeri', $secretary->title);
  }

  /**
   * Tests getSignatureInfo with missing data.
   */
  public function testGetSignatureInfoNewFormatMissingData(): void {
    $html = <<<HTML
      <section class="SahkoinenAllekirjoitusSektio">
        <p class="SahkoisestiAllekirjoitettuTeksti">Päätös on sähköisesti allekirjoitettu.</p>
        <p>
          <div class="Puheenjohtajanimi">Aku Ankka</div>
        </p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getSignatureInfo();

    $this->assertInstanceOf(SignatureInfo::class, $result);
    $this->assertCount(1, $result->signers);

    $chairman = $result->getSigner(SignerRole::CHAIRMAN);
    $this->assertInstanceOf(Signer::class, $chairman);
    $this->assertEquals('Aku Ankka', $chairman->name);
    $this->assertEquals('', $chairman->title);

    $this->assertNull($result->getSigner(SignerRole::SECRETARY));
  }

  /**
   * Tests getSections with multiple sections.
   */
  public function testGetSections(): void {
    $html = <<<HTML
      <section class="SisaltoSektioToisto">
        <div class="SisaltoSektio">
          <h3 class="SisaltoOtsikko">Päätös</h3>
          <p>Decision content.</p>
        </div>
        <div class="SisaltoSektio">
          <h3 class="SisaltoOtsikko">Käsittely</h3>
          <p>Handling content.</p>
          <p>More content.</p>
        </div>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getSections();

    $this->assertCount(2, $result);

    $this->assertInstanceOf(SisaltoSection::class, $result[0]);
    $this->assertEquals('Päätös', $result[0]->heading);
    $this->assertStringContainsString('<p>Decision content.</p>', $result[0]->content);

    $this->assertInstanceOf(SisaltoSection::class, $result[1]);
    $this->assertEquals('Käsittely', $result[1]->heading);
    $this->assertStringContainsString('Handling content.', $result[1]->content);
    $this->assertStringContainsString('More content.', $result[1]->content);
  }

  /**
   * Tests getModificationInfo returns content.
   */
  public function testGetModificationInfo(): void {
    $html = <<<HTML
      <div class="Muokkaustieto">Blaablaa</div>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getModificationInfo();

    $this->assertEquals('Blaablaa', $result);
  }

}
