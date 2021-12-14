<?php

declare(strict_types = 1);

namespace Drupal\Tests\paatokset_ahjo\Functional;

use Drupal\Tests\paatokset_ahjo\Traits\MigrateTrait;
use Drupal\Tests\helfi_api_base\Functional\MigrationTestBase;

/**
 * Tests entity list functionality.
 *
 * @group paatokset_ahjo
 */
class ListTest extends MigrationTestBase {

  use MigrateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'paatokset_ahjo',
    'migrate',
    'path', 'scheduler', 'text', 'link', 'menu_ui', 'node', 'image',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests collection route (views).
   */
  public function testList() : void {
    // Make sure anonymous user can't see the entity list.
    $this->drupalGet('/admin/content/integrations/paatokset-issue');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/content/integrations/paatokset-agenda-item');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/content/integrations/paatokset-meeting-document');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('/admin/content/integrations/paatokset-organization');
    $this->assertSession()->statusCodeEquals(403);

    // Make sure logged in user with access remote entities overview permission
    // can see the entity list.
    $account = $this->createUser([
      'access remote entities overview',
      'edit remote entities',
    ]);
    $this->drupalLogin($account);
    // Migrate entities and make sure we can see all entities from fixture.
    $this->createIssueMigration();
    $this->drupalGet('/admin/content/integrations/paatokset-issue');
    $this->assertSession()->pageTextContains('Päätökset - Issues');

    $this->createAgendaMigration();
    $this->drupalGet('/admin/content/integrations/paatokset-agenda-item');
    $this->assertSession()->pageTextContains('Päätökset - Agenda items');

    $this->createMeetingDocumentMigration();
    $this->drupalGet('/admin/content/integrations/paatokset-meeting-document');
    $this->assertSession()->pageTextContains('Päätökset - Meeting documents');

    $this->createOrganizationsMigration();
    $this->drupalGet('/admin/content/integrations/paatokset-organization');
    $this->assertSession()->pageTextContains('Päätökset - Organization');

  }

}
