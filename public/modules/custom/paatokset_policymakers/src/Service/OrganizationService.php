<?php

declare(strict_types = 1);

namespace Drupal\paatokset_policymakers\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Webmozart\Assert\Assert;

/**
 * Service class for retrieving organization-related data.
 */
final class OrganizationService {

  private const ORGANIZATION_TYPE = 'organization';

  /**
   * Node storage interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $organizationStorage;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private LoggerChannelInterface $logger;

  /**
   * Creates a new OrganizationService.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(
    private LanguageManagerInterface $languageManager,
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
    $this->organizationStorage = $entityTypeManager->getStorage('node');
    $this->logger = $loggerChannelFactory->get('paatokset_policymakers');
  }

  /**
   * Get policymaker organization.
   *
   * @param \Drupal\node\NodeInterface $policymaker
   *   Policymaker node.
   * @param ?\Drupal\Core\Language\LanguageInterface $language
   *   Language for the organization. Defaults to current language.
   *
   * @returns ?NodeInterface
   *   Organizations of the given policymaker. Null if the policymaker does not
   *   belong to any organization (we get invalid data from API).
   */
  public function getPolicymakerOrganization(NodeInterface $policymaker, ?LanguageInterface $language = NULL): ?NodeInterface {
    if (is_null($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }

    Assert::eq($policymaker->getType(), PolicymakerService::NODE_TYPE);

    $field = $policymaker->get('field_dm_organization');
    if ($field->isEmpty()) {
      return NULL;
    }

    $entity = $field->entity;
  
    /** @var \Drupal\node\NodeInterface $entity */
    Assert::isInstanceOf($entity, NodeInterface::class);

    if ($entity->hasTranslation($language->getId())) {
      return $entity->getTranslation($language->getId());
    }

    return NULL;
  }

  /**
   * Get list of organization hierarchy from root up to the given organization.
   *
   * @param \Drupal\node\NodeInterface $organization
   *   Starting organization.
   * @param ?\Drupal\Core\Language\LanguageInterface $language
   *   Language for organization hierarchy. Defaults to current language.
   *
   * @returns \Drupal\node\NodeInterface[]
   *   Organizations above the given organization and the given organization.
   *   Root organization is the first element.
   */
  public function getOrganizationHierarchy(NodeInterface $organization, ?LanguageInterface $language = NULL): array {
    if (is_null($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }

    // Include the given organization.
    $hierarchy = [$organization];

    // Recursively load until the root organization is reached.
    while (!is_null($organization = $this->getParentOrganization($organization, $language))) {
      $hierarchy[] = $organization;
    }

    return array_reverse($hierarchy);
  }

  /**
   * Get parent organization or NULL if root.
   *
   * @param \Drupal\node\NodeInterface $organization
   *   Current organization level.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Language.
   *
   * @returns \Drupal\node\NodeInterface
   *   Parent organization or NULL if current is root.
   */
  private function getParentOrganization(NodeInterface $organization, LanguageInterface $language): ?NodeInterface {
    Assert::eq($organization->getType(), self::ORGANIZATION_TYPE);

    if ($organization->get('field_org_level_above_id')->isEmpty()) {
      return NULL;
    }

    $nodes = $this->organizationStorage->loadByProperties([
      'type' => self::ORGANIZATION_TYPE,
      'langcode' => $language->getId(),
      'field_policymaker_id' => $organization->get('field_org_level_above_id')->getString(),
    ]);

    if ($node = reset($nodes)) {
      return $node;
    }

    // Should not happen.
    $this->logger->warning("Missing parent organization for @org", [
      '@org' => $organization->get('field_org_level_above_id')->getString(),
    ]);

    return NULL;
  }

}
