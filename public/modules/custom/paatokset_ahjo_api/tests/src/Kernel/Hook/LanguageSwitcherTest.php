<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\paatokset_ahjo_api\Entity\CaseBundle;
use Drupal\paatokset_ahjo_api\Entity\Decision;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\PropertyTrait;
use Symfony\Component\Routing\Route;

/**
 * Tests paatokset language switcher.
 *
 * @see \Drupal\paatokset_ahjo_api\Hook\Breadcrumbs
 */
class LanguageSwitcherTest extends AhjoEntityKernelTestBase {

  use PropertyTrait;
  use LanguageManagerTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_language_negotiator_test',
    'language',
    // Päätökset Language switcher relies on hdbt_admin_tool settings
    // #untranslated = TRUE to untranslated language switcher links.
    // Paatokset also relies on hdbt_admin_tools hooks being run before
    // paatokset_ahjo_api hooks.
    'hdbt_admin_tools',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupLanguages();
  }

  /**
   * Tests language switcher for decisions.
   */
  #[DataProvider('languageSwitcherDataProvider')]
  public function testLanguageSwitcher(
    string $routeName,
    string $routeParameter,
    array $entityData,
    array $otherEntities,
    array $tests,
  ): void {
    $user = $this->createUser([
      'access content',
    ]);
    $this->setCurrentUser($user);

    // Language switcher hook behaviour depends on
    // current route name and the route parameter.
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $this->container->set('current_route_match', $routeMatch->reveal());

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('node');

    $entity = $storage->create($entityData);
    $entity->save();

    // Create additional entities so the associations can be tested.
    foreach ($otherEntities as $otherEntity) {
      $storage->create($otherEntity)->save();
    }

    $routeMatch->getRouteName()
      ->willReturn($routeName);
    $routeMatch->getParameter(Argument::any())
      ->willReturn($entity);
    // HDbT admin tools uses route options
    // to check route parameter name.
    $routeMatch->getRouteObject()
      ->willReturn(new Route('/test', options: [
        'parameters' => [
          $routeParameter => [
            'type' => 'entity:node',
          ],
        ],
      ]));

    // Entity id depends on route and entity type.
    // Without correct entity id for current url, access checks fail.
    $current_url = new Url($routeName, [
      $routeParameter => $this->getEntityId($routeName, $entity),
    ]);

    $manager = $this->languageManager();
    $manager->getNegotiator()->setCurrentUser($user);
    $links = $manager->getLanguageSwitchLinks(LanguageInterface::TYPE_INTERFACE, $current_url);

    // Links should be set. This fails if e.g. access check for urls fail.
    $this->assertEqualsCanonicalizing(array_keys($tests), array_keys($links?->links ?? []));

    foreach ($links->links as $langcode => $link) {
      // Alter hook sets #override_url for some links
      // which is used in hdbt_subtheme templates.
      $url = $link['#override_url'] ?? $link['url']?->toString();

      $this->assertEquals($tests[$langcode], $url);
    }
  }

  /**
   * Get entity id.
   */
  private function getEntityId(string $route, EntityInterface $entity): string {
    if ($route === 'entity.node.canonical') {
      return $entity->id();
    }

    if ($entity instanceof CaseBundle) {
      return $entity->get('field_diary_number')->value;
    }
    elseif ($entity instanceof Decision) {
      return str_replace(['{', '}'], '', $entity->get('field_decision_native_id')->value);
    }

    throw new \InvalidArgumentException();
  }

  /**
   * Data provider for tests.
   */
  public static function languageSwitcherDataProvider(): array {
    return [
      // Case without decisions, canonical route.
      [
        // Current route.
        'entity.node.canonical',
        // Route parameter name.
        'node',
        // Route parameter node.
        [
          'type' => 'case',
          'title' => 'Test case',
          'status' => '1',
          'langcode' => 'en',
          'field_diary_number' => '123',
        ],
        // Other entities in the db.
        [],
        // Tests.
        [
          'en' => '/node/1',
          'fi' => '/asia/123',
          'sv' => '/arende/123',
        ],
      ],
      // Case with decisions, canonical route.
      [
        // Current route.
        'entity.node.canonical',
        // Route parameter name.
        'node',
        // Route parameter node.
        [
          'type' => 'case',
          'title' => 'Test case',
          'status' => '1',
          'langcode' => 'en',
          'field_diary_number' => '123',
        ],
        // Other entities in the db.
        [
          [
            'type' => 'decision',
            'title' => 'Test decision',
            'status' => '1',
            'langcode' => 'en',
            'field_decision_native_id' => '234',
            'field_diary_number' => '123',
          ],
        ],
        // Tests.
        [
          'en' => '/node/1',
          'fi' => '/asia/123?paatos=234',
          'sv' => '/arende/123?beslut=234',
        ],
      ],
      // Decision without case, case route.
      [
        // Current route.
        'paatokset_case.en',
        // Route parameter name.
        'case',
        // Route parameter node.
        [
          'type' => 'decision',
          'title' => 'Test decision',
          'status' => '1',
          'langcode' => 'en',
          'field_decision_native_id' => '{234}',
        ],
        // Other entities in the db.
        [],
        // Tests.
        [
          'en' => '/case/234',
          'fi' => '/asia/234',
          'sv' => '/arende/234',
        ],
      ],
    ];
  }

}
