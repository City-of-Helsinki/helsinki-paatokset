<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use Drupal\Tests\paatokset_ahjo_api\Kernel\AhjoEntityKernelTestBase;
use Drupal\Tests\paatokset_ahjo_api\Kernel\DecisionPathPatternsTrait;
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
  use DecisionPathPatternsTrait;
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
    'path',
    // Token module requires filter for text field tokens.
    'filter',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupLanguages();
    $this->installConfig(['pathauto']);
    $this->createDecisionPathPatterns();
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
      $routeParameter => $entity->id(),
    ]);

    $manager = $this->languageManager();
    $manager->getNegotiator()->setCurrentUser($user);
    $links = $manager->getLanguageSwitchLinks(LanguageInterface::TYPE_INTERFACE, $current_url);

    // Links should be set. This fails if e.g. access check for urls fail.
    $this->assertEqualsCanonicalizing(array_keys($tests), array_keys($links?->links ?? []));

    foreach ($links->links as $langcode => $link) {
      // Alter hook sets #override_url for some links
      // which is used in hdbt_subtheme templates. Other links
      // use the current route in the link language.
      $this->assertInstanceOf(Url::class, $link['url']);
      $url = $link['#override_url'] ?? (clone $link['url'])
        ->setOption('language', $link['language'])
        ->toString();

      $this->assertEquals($tests[$langcode], $url);
    }
  }

  /**
   * Data provider for tests.
   */
  public static function languageSwitcherDataProvider(): array {
    // URL language prefixes are not configured in kernel tests.
    return [
      // Untranslated case.
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
          'langcode' => 'fi',
          'field_diary_number' => 'HEL-123',
        ],
        // Other entities in the db.
        [
          [
            'type' => 'decision',
            'title' => 'Test decision',
            'status' => '1',
            'langcode' => 'fi',
            'field_decision_native_id' => '{234}',
            'field_diary_number' => 'HEL-123',
          ],
        ],
        // Tests.
        [
          'en' => '/case/hel-123',
          'fi' => '/asia/hel-123',
          'sv' => '/arende/hel-123',
        ],
      ],
      // Decision with a Swedish version.
      [
        // Current route.
        'entity.node.canonical',
        // Route parameter name.
        'node',
        // Route parameter node.
        [
          'type' => 'decision',
          'title' => 'Test decision',
          'status' => '1',
          'langcode' => 'fi',
          'field_decision_native_id' => '{234}',
          'field_diary_number' => 'HEL-123',
          'field_unique_id' => 'unique-234',
        ],
        // Other entities in the db.
        [
          [
            'type' => 'decision',
            'title' => 'Test decision SV',
            'status' => '1',
            'langcode' => 'sv',
            'field_decision_native_id' => '{345}',
            'field_diary_number' => 'HEL-123',
            'field_unique_id' => 'unique-234',
          ],
        ],
        // Tests.
        [
          'en' => '/case/hel-123/234',
          'fi' => '/asia/hel-123/234',
          'sv' => '/arende/hel-123/345',
        ],
      ],
      // Decision without case.
      [
        // Current route.
        'entity.node.canonical',
        // Route parameter name.
        'node',
        // Route parameter node.
        [
          'type' => 'decision',
          'title' => 'Test decision',
          'status' => '1',
          'langcode' => 'fi',
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
