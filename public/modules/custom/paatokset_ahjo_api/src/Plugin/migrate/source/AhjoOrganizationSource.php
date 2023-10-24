<?php

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\source;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Webmozart\Assert\Assert;

/**
 * Source plugin for retrieving organization hierarchy from Ahjo.
 *
 * Example:
 *
 * @code
 *  source:
 *    plugin: ahjo_api_organizations
 * @endcode
 *
 * @MigrateSource(
 *   id = "ahjo_api_organizations"
 * )
 */
final class AhjoOrganizationSource extends AhjoSourceBase implements ContainerFactoryPluginInterface {
  /**
   * The root id of the whole organization.
   */
  public const ROOT_ORGANIZATION_ID = '00001';

  /**
   * Organization translations supported by Ahjo.
   */
  private const ALL_LANGCODES = ['fi', 'sv'];

  /**
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    return [
      'id' => [
        'type' => 'string',
      ],
      'langcode' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): \Iterator {
    // Using stack allows the iterator to keep track of much less information.
    // The stack basically leads to Depth-first search instead of breadth-first.
    $stack = new \SplStack();

    $root = $this->configuration['root_org'] ?? self::ROOT_ORGANIZATION_ID;
    $langcodes = $this->configuration['languages'] ?? self::ALL_LANGCODES;

    Assert::isArray($langcodes);

    // Iterate the org chart using Depth-first search starting from the root.
    $stack->push($root);

    while (!$stack->isEmpty()) {
      $id = $stack->pop();

      // Traverse org char once for each langcode.
      foreach ($langcodes as $langcode) {
        $this->logger->info("Importing organization @id (@language)", [
          "@id" => $id,
          "@language" => $langcode,
        ]);

        $organization = $this->getOrganization($id, $langcode);
        if ($organization === NULL) {
          continue;
        }

        $title = Unicode::truncate($organization['Name'], '255', TRUE, TRUE);

        // Some organizations don't have Name in Ahjo.
        if (empty($title)) {
          $title = $id;
        }

        // Add organizations below this one to the stack.
        foreach ($organization['OrganizationLevelBelow']['organizations'] as $org_below) {
          $include_inactive = $this->configuration['include_inactive'] ?? FALSE;
          if ($include_inactive !== TRUE && $org_below['Existing'] !== 'true') {
            continue;
          }

          $stack->push($org_below['ID']);
        }

        yield [
          'id' => $id,
          'response' => $organization,
          'title' => $title,
          'langcode' => $langcode,
        ];
      }
    }
  }

  /**
   * Get organization info from Ahjo.
   *
   * @return ?array
   *   NULL if received invalid data from Ahjo.
   */
  private function getOrganization(string $id, string $langcode): ?array {
    if (!$this->ahjoProxy->isOperational()) {
      $this->logger->error('Ahjo Proxy is not operational, exiting.');
      throw new MigrateException('Ahjo Proxy is not operational, exiting.');
    }

    if (!empty(getenv('AHJO_PROXY_BASE_URL'))) {
      $url = 'organization/single/' . (string) $id . '?apireqlang=' . (string) $langcode;
      $query_string = NULL;
    }
    else {
      $url = 'organization';
      $query_string = 'orgid=' . (string) $id . '&apireqlang=' . (string) $langcode;
    }

    $organization = $this->ahjoProxy->getData($url, $query_string);

    // Local organization is formatted a bit differently.
    if (!empty($organization['decisionMakers'][0]['Organization'])) {
      $organization = $organization['decisionMakers'][0]['Organization'];
    }

    if (empty($organization['ID'])) {
      $this->logger->error('Data not found for @id', [
        '@id' => $id,
      ]);
      return NULL;
    }

    return $organization;
  }

  /**
   * {@inheritDoc}
   */
  public function __toString() {
    return 'AhjoOrganizations';
  }

}
