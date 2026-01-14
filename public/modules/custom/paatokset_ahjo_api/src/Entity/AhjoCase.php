<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Entity;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\paatokset_ahjo_api\Entity\Routing\AhjoRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Entity\Access\RemoteEntityAccess;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;
use Drupal\views\EntityViewsData;

/**
 * Defines the decision entity class.
 *
 * The naming convention is inconsistent because
 * "Case" is a reserved keyword in PHP.
 */
#[ContentEntityType(
  id: 'ahjo_case',
  label: new TranslatableMarkup('Case'),
  label_collection: new TranslatableMarkup('Cases'),
  label_singular: new TranslatableMarkup('case'),
  label_plural: new TranslatableMarkup('cases'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
    'langcode' => 'langcode',
    'published' => 'status',
  ],
  handlers: [
    'list_builder' => EntityListBuilder::class,
    'view_builder' => EntityViewBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => RemoteEntityAccess::class,
    'translation' => ContentTranslationHandler::class,
    'route_provider' => [
      'html' => AhjoRouteProvider::class,
    ],
  ],
  links: [
    // @todo remove v2 once prod database has all cases in the new format.
    'canonical' => '/v2/case/{ahjo_case}',
    // 'delete-form' => '/admin/content/ahjo/{block_content}/delete',
    // 'edit-form' => '/admin/content/block/{block_content}',
    'collection' => '/admin/content/v2/cases',
  ],
  admin_permission: "administer remote entities",
  base_table: 'paatokset_case',
  data_table: 'paatokset_case_data',
  // Currently, ahjo cases are not translated. However,
  // I assume that translations are harder to add later
  // if the need rises, unless we plan ahead.
  translatable: TRUE,
)]
final class AhjoCase extends RemoteEntityBase implements EntityPublishedInterface, ConfidentialityInterface, AhjoUpdatableInterface {

  use EntityPublishedTrait;

  /**
   * {@inheritDoc}
   */
  public const int MAX_SYNC_ATTEMPTS = 0;

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function getMigration() : ? string {
    return 'ahjo_cases';
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function label() : string {
    if ($this->hasOwnLabel()) {
      return parent::label();
    }

    // Some decisions released prior to 2019 don't have a label.
    // Show a related decision label instead.
    if ($decision = $this->getDefaultDecision()) {
      return $decision->label();
    }

    return (string) new TranslatableMarkup('NO TITLE');
  }

  /**
   * Check if title has own label.
   */
  public function hasOwnLabel(): bool {
    return (bool) parent::label();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields += self::publishedBaseFieldDefinitions($entity_type);

    // ID fields should match the remote entity id field.
    assert($fields[$entity_type->getKey('id')] instanceof BaseFieldDefinition);
    $fields[$entity_type->getKey('id')]
      ->setDescription(new TranslatableMarkup('Machine readable diary number'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', 32);

    assert($fields['created'] instanceof BaseFieldDefinition);
    $fields['created']
      ->setLabel(new TranslatableMarkup('Created'))
      ->setTranslatable(FALSE);

    assert($fields['changed'] instanceof BaseFieldDefinition);
    $fields['changed']
      ->setLabel(new TranslatableMarkup('Acquired'))
      ->setTranslatable(FALSE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Diary number label'))
      ->setDescription(new TranslatableMarkup('Human-readable diary number label'))
      ->setTranslatable(FALSE)
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', 32);

    // Diary number label is how the ID should be printed,
    // the title is the title of the issue.
    $fields[$entity_type->getKey('label')] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setDescription(new TranslatableMarkup('Human-readable case title'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 832);

    $fields['classification_title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Classification Title'))
      ->setDescription(new TranslatableMarkup('Case classification title.'))
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255);

    $fields['classification_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Classification Code'))
      ->setDescription(new TranslatableMarkup('Case classification code'))
      ->setTranslatable(FALSE)
      ->setSetting('max_length', 255);

    $fields['security_reasons'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Security Reasons'))
      ->setDescription(new TranslatableMarkup('Comma separated list of security reasons. The document is considered confidential if this field is not empty'))
      ->setTranslatable(TRUE)
      ->setSetting('max_length', 255);

    // Handling records are not currently used anywhere.
    $fields['handlings'] = BaseFieldDefinition::create('json_native')
      ->setLabel(new TranslatableMarkup('Handlings'))
      ->setDescription(new TranslatableMarkup('JSON-encoded array of case handlings.'))
      ->setTranslatable(TRUE);

    $fields['records'] = BaseFieldDefinition::create('json_native')
      ->setLabel(new TranslatableMarkup('Records'))
      ->setDescription(new TranslatableMarkup('JSON-encoded array of case records.'))
      ->setTranslatable(TRUE);

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function isConfidential(): bool {
    return !$this->get('security_reasons')->isEmpty();
  }

  /**
   * {@inheritDoc}
   */
  public function getConfidentialityReason(): string|null {
    return $this->get('security_reasons')?->getString();
  }

  /**
   * {@inheritDoc}
   */
  public function getProxyUrl(): Url {
    return Url::fromRoute('paatokset_ahjo_proxy.cases_single', [
      'id' => $this->id(),
    ]);
  }

  /**
   * Gets the top category from the classification code.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\TopCategory|null
   *   The top category enum, or NULL if not found.
   */
  public function getTopCategory(): ?TopCategory {
    $classificationCode = $this->get('classification_code')->value;
    if (empty($classificationCode)) {
      return NULL;
    }

    // Extract the first digits from the classification code.
    return TopCategory::tryFrom(trim(array_first(explode(' ', $classificationCode))));
  }

  /**
   * {@inheritDoc}
   */
  public function getAhjoId(): string {
    return $this->id();
  }

  /**
   * {@inheritDoc}
   */
  public static function getAhjoEndpoint(): string {
    return 'cases';
  }

  /**
   * Gets the default decision for this case.
   */
  public function getDefaultDecision(): Decision {
    return array_first($this->getAllDecisions());
  }

  /**
   * Get all decisions for this case.
   *
   * @return \Drupal\paatokset_ahjo_api\Entity\Decision[]
   *   Array of decision nodes.
   */
  public function getAllDecisions(): array {
    static $cache = [];

    // Cache DB query per case ID.
    if (isset($cache[$this->id()])) {
      return $cache[$this->id()];
    }

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $ids = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'decision')
      ->condition('status', 1)
      ->condition('field_diary_number', $this->id())
      ->sort('field_meeting_date', 'DESC')
      ->sort('field_decision_section', 'DESC')
      ->execute();

    $results = Decision::loadMultiple($ids);

    // Track unique IDs for current language decisions.
    $native_results = [];
    foreach ($results as $node) {
      if ($node->language()->getId() === $currentLanguage) {
        $native_results[$node->field_unique_id->value] = $node;
      }
    }

    // Remove any decisions where:
    // - The language is not the currently active language.
    // - Another decision with the same ID exists in the active language.
    $results = array_filter($results, fn (Decision $decision) =>
      $decision->language()->getId() === $currentLanguage || !isset($native_results[$decision->field_unique_id->value])
    );

    return $cache[$this->id()] = $results;
  }

  /**
   * Gets next decision, if one exists.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $decision
   *   Current decision.
   */
  public function getNextDecision(Decision $decision): ?Decision {
    return $this->getAdjacentDecision(-1, $decision);
  }

  /**
   * Gets previous decision, if one exists.
   *
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $decision
   *   Current decision.
   */
  public function getPrevDecision(Decision $decision): ?Decision {
    return $this->getAdjacentDecision(1, $decision);
  }

  /**
   * Gets adjacent decision in the list, if one exists.
   *
   * @param int $offset
   *   Which offset to use (1 for previous, -1 for next).
   * @param \Drupal\paatokset_ahjo_api\Entity\Decision $decision
   *   Current decision.
   *
   * @return Decision|null
   *   Adjacent decision in the list.
   */
  private function getAdjacentDecision(int $offset, Decision $decision): ?Decision {
    $allDecisions = array_values($this->getAllDecisions());
    $key = array_find_key($allDecisions, fn ($node) => (string) $node->id() === $decision->id());

    if ($key === NULL) {
      throw new \InvalidArgumentException("Decision with ID {$decision->id()} not found in case {$this->id()}");
    }

    return $allDecisions[$key + $offset] ?? NULL;
  }

}
