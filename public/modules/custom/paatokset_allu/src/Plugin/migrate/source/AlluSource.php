<?php

declare(strict_types=1);

namespace Drupal\paatokset_allu\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paatokset_allu\ApprovalType;
use Drupal\paatokset_allu\Client\Client;
use Drupal\paatokset_allu\DecisionType;
use Drupal\paatokset_allu\DocumentType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Source plugin for retrieving data from Allu API.
 *
 * @MigrateSource(
 *   id = "paatokset_allu",
 *   deriver = "Drupal\paatokset_allu\Plugin\Deriver\AlluSourcePlugin",
 * )
 */
final class AlluSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Allu API client.
   */
  private Client $client;

  /**
   * Logger.
   */
  private LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): self {
    $instance = new self($configuration, $plugin_id, $plugin_definition, $migration);
    $instance->client = $container->get(Client::class);
    $instance->logger = $container->get('logger.channel.paatokset_allu');
    return $instance;
  }

  /**
   * Get documents.
   *
   * @throws \Drupal\paatokset_allu\AlluException
   */
  public function getDocuments(DocumentType $documentType): \Generator {
    try {
      $after = new \DateTimeImmutable($this->configuration['after'] ?? '-1 week');
      $before = new \DateTimeImmutable($this->configuration['before'] ?? 'now');

      $interval = new \DateInterval('P1M');
      $period = new \DatePeriod($after, $interval, $before);
    }
    catch (\DateMalformedStringException | \DateMalformedPeriodStringException $e) {
      throw new \InvalidArgumentException("Invalid date string: {$e->getMessage()}", previous: $e);
    }

    $types = match ($documentType) {
      DocumentType::DECISION => DecisionType::cases(),
      DocumentType::APPROVAL => ApprovalType::cases(),
    };

    foreach ($types as $type) {
      foreach ($period as $date) {
        $start = $date;
        $end = $date->modify('+1 month');

        $this->logger->info("Fetching {$documentType->value}:{$type->value} from {$start->format('Y-m-d')} to {$end->format('Y-m-d')}");

        yield from match ($documentType) {
          DocumentType::DECISION => $this->client->decisions($type, $start, $end),
          DocumentType::APPROVAL => $this->client->approvals($type, $start, $end),
        };
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function initializeIterator(): \Iterator {
    $document = DocumentType::from($this->pluginDefinition['document']);

    foreach ($this->getDocuments($document) as $row) {
      yield $this->getFieldsFromRow($row);
    }
  }

  /**
   * Get configured fields from Helbit response.
   *
   * @return array
   *   Configured fields.
   */
  private function getFieldsFromRow(array $row): array {
    $fields = $this->configuration['fields'];
    $result = [];

    foreach ($fields as $field) {
      ['name' => $name, 'selector' => $selector] = $field;

      if (isset($row[$selector])) {
        $result[$name] = $row[$selector];
      }
    }

    return $result;
  }

  /**
   * {@inheritDoc}
   */
  public function fields(): array {
    $fields = [];

    foreach ($this->configuration['fields'] as $field) {
      if (isset($field['name'])) {
        $fields[$field['name']] = $field['label'] ?? '';
      }
    }

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function getIds(): array {
    if (empty($this->configuration['ids'])) {
      throw new \InvalidArgumentException("Missing ids configuration option");
    }

    return $this->configuration['ids'];
  }

  /**
   * {@inheritDoc}
   */
  public function __toString(): string {
    return $this->getPluginId();
  }

}
