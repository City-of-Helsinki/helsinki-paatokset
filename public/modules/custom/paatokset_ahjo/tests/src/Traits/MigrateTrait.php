<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo\Traits;

use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Trait for shared migration tasks.
 */
trait MigrateTrait {

  use ApiTestTrait;

  /**
   * The issue migration.
   */
  protected function createIssueMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('paatokset_issues');
  }

  /**
   * The agenda migration.
   */
  protected function createAgendaMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('paatokset_agenda_items');
  }

  /**
   * The meeting document migration.
   */
  protected function createMeetingDocumentMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('paatokset_meeting_documents');
  }

  /**
   * The meeting migration.
   */
  protected function createMeetingMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('paatokset_meetings');
  }

  /**
   * The organizations migration.
   */
  protected function createOrganizationsMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('paatokset_organizations');
  }

  /**
   * The policymaker migration.
   */
  protected function createPolicymakersMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('paatokset_policymakers');
  }

}
