<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\search_api\processor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Computes CSS class for the given entity.
 *
 * @SearchApiProcessor(
 *    id = "special_status",
 *    label = @Translation("Special status"),
 *    description = @Translation("Marks the entity with pre-defined special statuses"),
 *    stages = {
 *      "add_properties" = 0
 *    },
 *    locked = true,
 *    hidden = true
 * )
 */
class SpecialStatus extends ProcessorPluginBase {
  public const CITY_COUNCIL = '_city_council';
  public const CITY_HALL = '_city_hall';
  public const TRUSTEE = '_trustee';

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
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL) {
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
  public function addFieldValues(ItemInterface $item) {
    $datasourceId = $item->getDataSourceId();
    if ($datasourceId !== 'entity:node') {
      return;
    }

    $node = $item->getOriginalObject()->getValue();
    if (!$node instanceof Decision) {
      return;
    }

    $status = NULL;
    $config = $this->configFactory->get('paatokset_helsinki_kanava.settings');
    $id = $node->get('field_policymaker_id')->value;
    if ((string) $config->get('city_council_id') === $id) {
      $status = self::CITY_COUNCIL;
    }
    elseif ((string) $config->get('city_hall_id') === $id) {
      $status = self::CITY_HALL;
    }
    else {
      $policymaker = $node->getPolicymaker($item->getLanguage());
      if ($policymaker && (string) $policymaker->get('field_organization_type_id')->value === $config->get('trustee_organization_type_id')) {
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
