<?php

declare(strict_types = 1);

namespace Drupal\paatokset_policymakers\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Lazy building functions for policymaker data.
 */
class PolicymakerLazyBuilder implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Current language.
   *
   * @var string
   */
  protected $currentLanguage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Policymaker service.
   *
   * @var \Drupal\paatokset_policymakers\Service\PolicymakerService
   */
  protected $policymakerService;

  /**
   * Lazy Builder constructor.
   *
   * @param \Drupal\Core\Language\LanguageManager $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\paatokset_policymakers\Service\PolicymakerService $policymaker_service
   *   Policymaker service.
   */
  public function __construct(LanguageManager $language_manager, EntityTypeManagerInterface $entity_type_manager, PolicymakerService $policymaker_service) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->policymakerService = $policymaker_service;
    $this->currentLanguage = $this->languageManager->getCurrentLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'policymakersAccordions',
      'policymakersCards',
    ];
  }

  /**
   * Lazybuilder render for city council and board cards.
   *
   * @return array
   *   The render array.
   */
  public function policymakersCards(): array {
    $council = $this->policymakerService->getPolicyMaker(PolicymakerService::CITY_COUNCIL_DM_ID);
    $board = $this->policymakerService->getPolicyMaker('00400');

    $cache_tags = [];
    $cards = [];
    foreach ([$council, $board] as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $cache_tags[] = 'node:' . $node->id();

      $title = $node->get('title')->value;

      $cards[] = [
        'title' => $title,
        'ahjo_title' => $this->policymakerService->getPolicymakerTypeFromNode($node),
        'link' => $this->policymakerService->getPolicymakerRoute($node, $this->currentLanguage),
        'image' => $node->get('field_policymaker_image')->view('default'),
        'organization_color' => $this->policymakerService->getPolicymakerClassById($node->get('field_policymaker_id')->value),
      ];
    }

    return [
      '#theme' => 'policymaker_cards',
      '#cards' => $cards,
      '#cache' => [
        'max-age' => Cache::PERMANENT,
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * Lazybuilder render for policymakers.
   *
   * @return array
   *   Render array.
   */
  public function policymakersAccordions(): array {
    $policymakers = $this->getPolicyMakerAccordions();
    $council_members = $this->getCouncilMembersAccordion();
    $accordions = array_merge($policymakers, [$council_members]);

    return [
      '#theme' => 'policymaker_accordions',
      '#accordions' => $accordions,
      '#cache' => [
        'max-age' => Cache::PERMANENT,
        'tags' => ['node_list:policymaker', 'node_list:trustee'],
      ],
    ];
  }

  /**
   * Get accordions for all policy makers.
   *
   * @return array|null
   *   Accordions array.
   */
  protected function getPolicyMakerAccordions(): ?array {
    $policymakers = $this->getActivePolicyMakers();

    // Filter out city council divisions into a separate array.
    $hallituksen_jaosto = array_filter($policymakers, function ($var) {
      return ($var['is_city_council_division'] === '1');
    });

    $filtered = array_filter($policymakers, function ($var) {
      return ($var['is_city_council_division'] === NULL || $var['is_city_council_division'] === '0');
    });

    // Filter policymakers into correct sections.
    $accordion_contents = [
      'valtuusto' => [
        'filter' => 'Valtuusto',
        'items' => [],
      ],
      'hallitus' => [
        'filter' => 'Hallitus',
        'items' => [],
      ],
      'viranhaltijat' => [
        'filter' => 'Viranhaltija',
        'items' => [],
      ],
      'trustee' => [
        'filter' => 'Luottamushenkilö',
        'items' => [],
      ],
      'lautakunta' => [
        'filter' => 'Lautakunta',
        'items' => [],
      ],
      'jaosto' => [
        'filter' => 'Jaosto',
        'items' => [],
      ],
      'toimikunnat' => [
        'filter' => 'Toimi-/Neuvottelukunta',
        'items' => [],
      ],
    ];

    foreach ($accordion_contents as $key => $value) {
      $filter = $value['filter'];
      $accordion_contents[$key]['items'] = array_filter($filtered, function ($var) use ($filter) {
        return ($var['organization_type'] == $filter);
      });
    };

    // Merge "lautakunnat" and "jaostot" into single section.
    $lautakunnat_jaostot = array_merge($accordion_contents['lautakunta']['items'], $accordion_contents['jaosto']['items']);

    // Sort by titles.
    usort($accordion_contents['viranhaltijat']['items'], function ($a, $b) {
      return strnatcmp($a['sort_title'], $b['sort_title']);
    });
    usort($accordion_contents['trustee']['items'], function ($a, $b) {
      return strnatcmp($a['title'], $b['title']);
    });
    usort($lautakunnat_jaostot, function ($a, $b) {
      return strnatcmp($a['title'], $b['title']);
    });
    usort($hallituksen_jaosto, function ($a, $b) {
      return strnatcmp($a['title'], $b['title']);
    });

    // Divide boards and committees into sectors.
    $sectors = [];
    foreach ($lautakunnat_jaostot as $row) {
      if (isset($sectors[$row['sector']])) {
        continue;
      }
      $sectors[$row['sector']] = [
        'title' => $row['sector'],
        'subitems' => [],
      ];
    }
    foreach ($sectors as $key => $value) {
      $filter = $key;

      $sectors[$key]['subitems'] = array_filter($lautakunnat_jaostot, function ($var) use ($filter) {
        return ($var['sector'] === $filter);
      });
    };

    ksort($sectors);

    // Divide officials by sector.
    $sectors_occupants = [];
    foreach ($accordion_contents['viranhaltijat']['items'] as $row) {
      if (isset($sectors_occupants[$row['sector']])) {
        continue;
      }

      if (empty($row['sector'])) {
        $title = t('Office holders');
      }
      else {
        $title = t('Office holders: @sector_title', ['@sector_title' => $row['sector']]);
      }

      $sectors_occupants[$row['sector']] = [
        'title' => $title,
        'items' => [],
      ];
    }

    foreach ($sectors_occupants as $key => $value) {
      $filter = $key;

      $sectors_occupants[$key]['items'] = array_filter($accordion_contents['viranhaltijat']['items'], function ($var) use ($filter) {
        return ($var['sector'] === $filter);
      });
    };

    ksort($sectors_occupants);

    // Format actual accordions.
    $accordions = [];
    if (!empty($hallituksen_jaosto)) {
      $accordions[] = [
        'heading' => t('City Board sub-committees'),
        'items' => $hallituksen_jaosto,
      ];
    }
    if (!empty($sectors)) {
      $accordions[] = [
        'heading' => t('Committees and Boards'),
        'items' => $sectors,
      ];
    }

    foreach ($sectors_occupants as $official_sectors) {
      if (!empty($official_sectors['items'])) {
        $accordions[] = [
          'heading' => $official_sectors['title'],
          'items' => $official_sectors['items'],
        ];
      }
    }

    if (!empty($accordion_contents['trustee']['items'])) {
      $accordions[] = [
        'heading' => t('Elected official decisionmakers'),
        'items' => $accordion_contents['trustee']['items'],
      ];
    }

    return $accordions;
  }

  /**
   * Get formatted list of active policymakers.
   *
   * @return array
   *   Array of formatted policymaker content.
   */
  protected function getActivePolicyMakers(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()->condition('type', 'policymaker')
      ->condition('field_policymaker_existing', 1)
      ->condition('status', 1)
      ->execute();

    $nodes = $storage->loadMultiple($nids);

    $filtered = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }

      if (!$node->hasField('field_ahjo_title') || !$node->hasField('field_dm_org_name') || $node->get('field_ahjo_title')->isEmpty()) {
        continue;
      }

      if ($node->hasTranslation($this->currentLanguage)) {
        $node = $node->getTranslation($this->currentLanguage);
      }

      if ($node->hasField('field_sector_name') && !$node->get('field_sector_name')->isEmpty()) {
        $sector = $this->policymakerService->getSectorTranslation($node->get('field_sector_name')->value, $this->currentLanguage);
        $sector_display = $node->get('field_sector_name')->value;
      }
      else {
        $sector = '';
        $sector_display = '';
      }

      $filtered[] = [
        'title' => $node->get('title')->value,
        'sort_title' => $node->get('field_dm_org_name')->value . ' ' . $node->get('title')->value,
        'ahjo_title' => $node->get('field_ahjo_title')->value,
        'link' => $this->policymakerService->getPolicymakerRoute($node, $this->currentLanguage),
        'organization_type' => $node->get('field_organization_type')->value,
        'organization_name' => $node->get('field_dm_org_name')->value,
        'image' => $node->get('field_policymaker_image')->view('default'),
        'organization_color' => $this->policymakerService->getPolicymakerClassById($node->get('field_policymaker_id')->value),
        'is_city_council_division' => $node->get('field_city_council_division')->value,
        'sector' => $sector,
        'sector_display' => $sector_display,
      ];
    }

    return $filtered;
  }

  /**
   * Get accordion content for council members and deputies.
   *
   * @return array|null
   *   Array of accordion content.
   */
  protected function getCouncilMembersAccordion(): ?array {
    // City council nodes.
    $nodes = $this->policymakerService->getComposition(PolicymakerService::CITY_COUNCIL_DM_ID);

    $filter = 'Jäsen';
    $members = array_filter($nodes, function ($var) use ($filter) {
      return (str_contains($var['role_orig'], $filter));
    });

    $filter = 'Varajäsen';
    $deputies = array_filter($nodes, function ($var) use ($filter) {
      return (str_contains($var['role_orig'], $filter));
    });

    usort($members, function ($a, $b) {
      return strcmp($a['last_name'], $b['last_name']);
    });
    usort($deputies, function ($a, $b) {
      return strcmp($a['last_name'], $b['last_name']);
    });

    $items = [];
    foreach ($members as $node) {
      $items[] = [
        'title' => $node['first_name'] . ' ' . $node['last_name'],
        'link' => $node['url'],
        'organization_type' => 'trustee',
        'trustee_type' => $node['role'],
      ];
    };
    foreach ($deputies as $node) {
      $items[] = [
        'title' => $node['first_name'] . ' ' . $node['last_name'],
        'link' => $node['url'],
        'organization_type' => 'trustee',
        'trustee_type' => $node['role'],
      ];
    };

    if (!empty($items)) {
      return [
        'heading' => t('City Council members'),
        'items' => $items,
      ];
    }
    return NULL;
  }

}
