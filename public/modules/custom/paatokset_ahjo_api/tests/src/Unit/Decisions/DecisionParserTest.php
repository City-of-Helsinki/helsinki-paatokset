<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Decisions;

use Drupal\paatokset_ahjo_api\Decisions\DecisionParser;
use Drupal\paatokset_ahjo_api\Decisions\DTO\MoreInfoDetails;
use Drupal\paatokset_ahjo_api\Decisions\DTO\PresenterInfo;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SignatureInfo;
use Drupal\paatokset_ahjo_api\Decisions\DTO\Signer;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SignerRole;
use Drupal\paatokset_ahjo_api\Decisions\DTO\SisaltoSection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests DecisionParser.
 */
#[Group('paatokset_ahjo_api')]
class DecisionParserTest extends UnitTestCase {

  /**
   * Tests parsing more info details.
   *
   * @param string $html
   *   Decision HTML content.
   * @param array<mixed< $expected
   *   Expected contacts.
   */
  #[DataProvider('moreInfoDetailsData')]
  public function testGetMoreInfoDetails(string $html, array $expected): void {
    $result = DecisionParser::parse($html)->getMoreInfoDetails();

    $this->assertCount(count($expected), $result);

    foreach ($expected as $delta => $contact) {
      $contact += [
        'title' => '',
        'phone' => NULL,
        'email' => NULL,
        'phone_uri' => NULL,
        'email_uri' => NULL,
      ];

      $this->assertInstanceOf(MoreInfoDetails::class, $result[$delta]);
      $this->assertEquals($contact['name'], $result[$delta]->name);
      $this->assertEquals($contact['title'], $result[$delta]->title);
      $this->assertEquals($contact['phone'], $result[$delta]->phone);
      $this->assertEquals($contact['email'], $result[$delta]->email);

      $phone_link = $result[$delta]->getPhoneLink();
      $this->assertEquals($contact['phone_uri'], $phone_link?->getUrl()->getUri());
      $this->assertEquals($contact['phone_uri'] ? $contact['phone'] : NULL, $phone_link?->getText());

      $email_link = $result[$delta]->getEmailLink();
      $this->assertEquals($contact['email_uri'], $email_link?->getUrl()->getUri());
      $this->assertEquals($contact['email_uri'] ? $contact['email'] : NULL, $email_link?->getText());
    }
  }

  /**
   * Data provider for testGetMoreInfoDetails.
   */
  public static function moreInfoDetailsData(): array {
    return [
      'new format' => [
        <<<HTML
        <section class="Lisatiedot">
          <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
          <p>
            <span class="LisatiedonantajanNimi">Etunimi Sukunimi</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
            <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">09 310 12345</span>, <span class="LisatiedonantajanSahkoposti">etunimi.sukunimi@hel.fi</span>
          </p>
        </section>
        HTML,
        [
          [
            'name' => 'Etunimi Sukunimi',
            'title' => 'Titteli',
            'phone' => '09 310 12345',
            'phone_uri' => 'tel:0931012345',
            'email' => 'etunimi.sukunimi@hel.fi',
            'email_uri' => 'mailto:etunimi.sukunimi@hel.fi',
          ],
        ],
      ],
      'new format, multiple contacts' => [
        <<<HTML
        <section class="Lisatiedot">
          <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
          <p>
            <span class="LisatiedonantajanNimi">Aku Ankka</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
            <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">09 310 12345</span>, <span class="LisatiedonantajanSahkoposti">aku.ankka@hel.fi</span>
          </p>
          <p>
            <span class="LisatiedonantajanNimi">Roope Ankka</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
            <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">09 310 12346</span>, <span class="LisatiedonantajanSahkoposti">roope.ankka@hel.fi</span>
          </p>
        </section>
        HTML,
        [
          [
            'name' => 'Aku Ankka',
            'title' => 'Titteli',
            'phone' => '09 310 12345',
            'phone_uri' => 'tel:0931012345',
            'email' => 'aku.ankka@hel.fi',
            'email_uri' => 'mailto:aku.ankka@hel.fi',
          ],
          [
            'name' => 'Roope Ankka',
            'title' => 'Titteli',
            'phone' => '09 310 12346',
            'phone_uri' => 'tel:0931012346',
            'email' => 'roope.ankka@hel.fi',
            'email_uri' => 'mailto:roope.ankka@hel.fi',
          ],
        ],
      ],
      'new format, only name' => [
        <<<HTML
        <section class="Lisatiedot">
          <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
          <p>
            <span class="LisatiedonantajanNimi">Only Name</span>
          </p>
        </section>
        HTML,
        [
          ['name' => 'Only Name'],
        ],
      ],
      // Area code is added to city phone numbers. Production data contains
      // phone numbers containing only "310". Drupal can't handle short phone
      // numbers, so the link contains a visual separator.
      'new format, phone number without area code' => [
        <<<HTML
        <section class="Lisatiedot">
          <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
          <p>
            <span class="LisatiedonantajanNimi">Etunimi Sukunimi</span>, <span class="LisatiedonantajanTitteli">titteli</span><br>
            <span class="LisatiedotantajanPuhelinOtsikko">puhelin: </span><span class="LisatiedonantajanPuhelin">310</span>, <span class="LisatiedonantajanSahkoposti">etunimi.sukunimi@hel.fi</span>
          </p>
        </section>
        HTML,
        [
          [
            'name' => 'Etunimi Sukunimi',
            'title' => 'Titteli',
            'phone' => '09 310',
            'phone_uri' => 'tel:0-9310',
            'email' => 'etunimi.sukunimi@hel.fi',
            'email_uri' => 'mailto:etunimi.sukunimi@hel.fi',
          ],
        ],
      ],
      'legacy format' => [
        <<<HTML
        <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
        <p>Etunimi Sukunimi, kaupunginsihteeri, puhelin: 09 310 12345
        <div>etunimi.sukunimi@hel.fi</div>
        </p>
        HTML,
        [
          [
            'name' => 'Etunimi Sukunimi',
            'title' => 'Kaupunginsihteeri',
            'phone' => '09 310 12345',
            'phone_uri' => 'tel:0931012345',
            'email' => 'etunimi.sukunimi@hel.fi',
            'email_uri' => 'mailto:etunimi.sukunimi@hel.fi',
          ],
        ],
      ],
      'legacy format, multiple contacts' => [
        <<<HTML
        <h3 class="LisatiedotOtsikko">Lisätiedot</h3>
        <p>Aku Ankka, kaupunginsihteeri, puhelin: 09 310 12345
        <div>aku.ankka@hel.fi</div>
        </p>
        <p>Roope Ankka, kaupunginsihteeri, puhelin: 09 310 12346
        <div>roope.ankka@hel.fi</div>
        </p>
        HTML,
        [
          [
            'name' => 'Aku Ankka',
            'title' => 'Kaupunginsihteeri',
            'phone' => '09 310 12345',
            'phone_uri' => 'tel:0931012345',
            'email' => 'aku.ankka@hel.fi',
            'email_uri' => 'mailto:aku.ankka@hel.fi',
          ],
          [
            'name' => 'Roope Ankka',
            'title' => 'Kaupunginsihteeri',
            'phone' => '09 310 12346',
            'phone_uri' => 'tel:0931012346',
            'email' => 'roope.ankka@hel.fi',
            'email_uri' => 'mailto:roope.ankka@hel.fi',
          ],
        ],
      ],
      'no more info section' => [
        '<div>Some other content</div>',
        [],
      ],
    ];
  }

  /**
   * Tests that nothing is returned when the HTML has no decision content.
   */
  #[TestWith([NULL])]
  #[TestWith(['<div>Some other content</div>'])]
  public function testNoContent(?string $html): void {
    $parser = DecisionParser::parse($html);

    $this->assertEmpty($parser->getMoreInfoDetails());
    $this->assertNull($parser->getMainContent());
    $this->assertNull($parser->getSignatureInfo());
    $this->assertNull($parser->getModificationInfo());
    $this->assertNull($parser->getAppealInfo());
    $this->assertNull($parser->getPresenterInfo());
    $this->assertEmpty($parser->getSections());
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

  /**
   * Tests getAppealInfo with new format (section wrapper).
   */
  public function testGetAppealInfoNewFormat(): void {
    $html = <<<HTML
      <section class="MuutoksenhakuohjeetSektio">
        <h3 class="MuutoksenhakuohjeetOtsikko">MUUTOKSENHAKUOHJEET</h3>
        <h4>VALITUSOSOITUS</h4>
        <p>Tähän päätökseen haetaan muutosta kunnallisvalituksella.</p>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getAppealInfo();

    $this->assertNotNull($result);
    $this->assertStringNotContainsString('MUUTOKSENHAKUOHJEET', $result);
    $this->assertStringContainsString('VALITUSOSOITUS', $result);
    $this->assertStringContainsString('Tähän päätökseen haetaan muutosta', $result);
  }

  /**
   * Tests getAppealInfo with legacy format (heading only).
   */
  public function testGetAppealInfoLegacyFormat(): void {
    $html = <<<HTML
      <h3 class="MuutoksenhakuOtsikko">Muutoksenhaku</h3>
      <p>Appeal content here.</p>
      <h3 class="SomeOtherHeading">Next section</h3>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getAppealInfo();

    $this->assertNotNull($result);
    $this->assertStringContainsString('Appeal content here.', $result);
    $this->assertStringNotContainsString('Next section', $result);
  }

  /**
   * Tests getPresenterInfo with new format.
   */
  public function testGetPresenterInfoNewFormat(): void {
    $html = <<<HTML
      <section class="EsittelijaTiedot">
        <h3 class="EsittelijaTiedot">Esittelijä</h3>
        <div></div>
        <div>Kaupunginhallitus</div>
      </section>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getPresenterInfo();

    $this->assertInstanceOf(PresenterInfo::class, $result);
    $this->assertEquals('Kaupunginhallitus', $result->title);
  }

  /**
   * Tests getPresenterInfo with legacy format.
   */
  public function testGetPresenterInfoLegacyFormat(): void {
    $html = <<<HTML
      <h3 class="EsittelijaTiedot">Esittelijä</h3>
      <div>Apulaispormestari</div>
      <div>Aku Ankka</div>
      <h3 class="SomeOtherHeading">Next section</h3>
    HTML;

    $parser = DecisionParser::parse($html);
    $result = $parser->getPresenterInfo();

    $this->assertInstanceOf(PresenterInfo::class, $result);
    $this->assertEquals('Apulaispormestari', $result->title);
    $this->assertEquals('Aku Ankka', $result->name);
  }

}
