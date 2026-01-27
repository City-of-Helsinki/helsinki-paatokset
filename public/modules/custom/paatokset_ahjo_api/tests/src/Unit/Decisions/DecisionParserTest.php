<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Decisions;

use Drupal\Core\Link;
use Drupal\paatokset_ahjo_api\Decisions\DecisionParser;
use Drupal\paatokset_ahjo_api\Decisions\DTO\MoreInfoDetails;
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
   * Tests that NULL is returned when no more info section exists.
   */
  public function testGetMoreInfoDetailsReturnsNullWhenNoContent(): void {
    $html = '<div>Some other content</div>';

    $parser = DecisionParser::parse($html);
    $result = $parser->getMoreInfoDetails();

    $this->assertNull($result);
  }

  /**
   * Tests parsing with NULL HTML input.
   */
  public function testParseWithNullHtml(): void {
    $parser = DecisionParser::parse(NULL);
    $result = $parser->getMoreInfoDetails();

    $this->assertNull($result);
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
   * Tests area code handling for city phone numbers.
   */
  public function testPhoneNumberAreaCode(): void {
    $html = <<<HTML
      <section class="Lisatiedot">
        <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
        <p>
          <span class="LisatiedonantajanNimi">Etunimi Sukunimi</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
          <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">310 12345</span>, <span class="LisatiedonantajanSahkoposti">etunimi.sukunimi@hel.fi</span>
        </p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getMoreInfoDetails();

    $this->assertInstanceOf(MoreInfoDetails::class, $result);

    // Area code is added to city phone numbers.
    $this->assertEquals('09 310 12345', $result->phone);
  }

}
