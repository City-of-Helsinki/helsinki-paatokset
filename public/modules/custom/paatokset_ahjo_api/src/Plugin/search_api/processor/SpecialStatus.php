<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\paatokset_ahjo_api\Entity\OrganizationType;
use Drupal\paatokset_ahjo_api\Service\PolicymakerService;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Computes CSS class for the given entity.
 */
#[SearchApiProcessor(
  id: 'special_status',
  label: new TranslatableMarkup('Special status'),
  description: new TranslatableMarkup('Marks the entity with pre-defined special statuses'),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class SpecialStatus extends ProcessorPluginBase {

  public const string CITY_COUNCIL = '_city_council';
  public const string CITY_HALL = '_city_hall';
  public const string TRUSTEE = '_trustee';

  /**
   * Config factory.
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get(ConfigFactoryInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource) {
      $definition = [
        'label' => $this->t('Special status'),
        'description' => $this->t('Marks the entity with pre-defined special statuses'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['special_status'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof Decision) {
      return;
    }

    $status = NULL;
    $id = $node->get('field_policymaker_id')->value;
    if ($id === PolicymakerService::CITY_COUNCIL_DM_ID) {
      $status = self::CITY_COUNCIL;
    }
    elseif ($id === PolicymakerService::CITY_BOARD_DM_ID) {
      $status = self::CITY_HALL;
    }
    else {
      $policymakerOrgType = $node
        ->getPolicymaker($item->getLanguage())
        ?->getOrganizationType();

      if ($policymakerOrgType == OrganizationType::OFFICE_HOLDER) {
        $status = self::TRUSTEE;
      }
    }

    if ($status) {
      $fields = $this
        ->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), $item->getDatasourceId(), 'special_status');

      foreach ($fields as $field) {
        $field->addValue($status);
      }
    }
  }

}
