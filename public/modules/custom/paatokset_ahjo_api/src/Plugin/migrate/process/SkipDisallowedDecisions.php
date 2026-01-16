<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\process;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Skips processing the current row if the decision is flagged for mass removal.
 *
 * The skip_disallowed_decisions process plugin checks multiple values.
 * If those values correspond to a decision flagged for removal,
 * a MigrateSkipRowException is thrown.
 *
 * Available configuration keys:
 * - source: Array of Organization ID, Date and Section fields. Must be in this
 *    order.
 *
 * Example:
 *
 * @code
 *  process:
 *    settings:
 *      plugin: skip_disallowed_decisions
 *      source:
 *        - organization_id
 *        - decision_date
 *        - section
 * @endcode
 *
 * This will return $data['contact'] if it exists. Otherwise, the row will be
 * skipped and the message "Missed the 'data' key" will be logged in the
 * message table.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 */
#[MigrateProcess('skip_disallowed_decisions', handle_multiples: TRUE)]
class SkipDisallowedDecisions extends ProcessPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    #[Autowire('logger.channel.paatokset_ahjo_api')]
    private readonly LoggerInterface $logger,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($values, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($values) || !is_array($values)) {
      return;
    }

    $dm_id = $values[0];
    $date = $values[1];
    $section = $values[2];

    if (empty($dm_id) || empty($date) || empty($section)) {
      return;
    }

    if ($this->checkIfDisallowed($dm_id, $date, $section)) {
      $this->logger->warning('Skipped decision. ID: @id, section: @section, date: @date', [
        '@id' => $dm_id,
        '@section' => $section,
        '@date' => $date,
      ]);
      throw new MigrateSkipRowException('Skipped disallowed decision');
    }
  }

  /**
   * Check if decision is disallowed based on ID, Date and Section.
   *
   * @param string $dm_id
   *   Organization ID.
   * @param string $date_str
   *   Decision date.
   * @param string $section
   *   Decision section.
   *
   * @return bool
   *   TRUE if values match a disallowed decision config entity.
   */
  protected function checkIfDisallowed(string $dm_id, string $date_str, string $section): bool {
    $config = $this->configFactory->get('paatokset_ahjo_api.disallowed_prefixes');

    // Check if decision years match before proceeding.
    $years = explode(',', $config->get('years'));
    $year = date('Y', strtotime($date_str));
    if (!in_array($year, $years)) {
      return FALSE;
    }

    // Check ID prefix matches before loading disallowed decision entities.
    $dm_ids = explode(',', $config->get('id_prefixes'));
    $id_match = FALSE;
    foreach ($dm_ids as $id_prefix) {
      if (str_contains($dm_id, $id_prefix)) {
        $id_match = TRUE;
        break;
      }
    }
    if (!$id_match) {
      return FALSE;
    }

    /** @var \Drupal\paatokset_ahjo_api\DisallowedDecisionsStorageManager $dd_manager */
    $dd_manager = $this->entityTypeManager->getStorage('disallowed_decisions');
    return $dd_manager->checkIfDisallowed($dm_id, $year, $section);
  }

}
