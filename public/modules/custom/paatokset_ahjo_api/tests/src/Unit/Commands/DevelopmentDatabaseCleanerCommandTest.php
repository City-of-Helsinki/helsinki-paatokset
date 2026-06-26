<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Unit\Commands;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\paatokset_ahjo_api\Drush\Commands\DevelopmentDatabaseCleanerCommand;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests DevelopmentDatabaseCleanerCommand.
 */
#[Group('paatokset_ahjo_api')]
class DevelopmentDatabaseCleanerCommandTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getCurrentLanguage')->willReturn($language);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('language_manager', $languageManager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that missing environment stops execution.
   */
  public function testStopsWhenNoActiveEnvironment(): void {
    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()
      ->willThrow(new \InvalidArgumentException());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage(Argument::any())->shouldNotBeCalled();

    $tester = $this->createCommandTester(
      $environmentResolver->reveal(),
      $entityTypeManager->reveal(),
    );

    $tester->execute([]);
    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    $this->assertStringContainsString(
      'Stopping execution. No active environment found.',
      $tester->getDisplay(),
    );
  }

  /**
   * Tests that disallowed environments stop execution.
   */
  #[DataProvider('disallowedEnvironmentProvider')]
  public function testStopsWhenEnvironmentIsNotLocalOrTest(string $environment): void {
    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()->willReturn($environment);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage(Argument::any())->shouldNotBeCalled();

    $tester = $this->createCommandTester(
      $environmentResolver->reveal(),
      $entityTypeManager->reveal(),
    );

    $tester->execute([]);
    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    $this->assertStringContainsString(
      'Stopping execution. App environment is not "local" or "test".',
      $tester->getDisplay(),
    );
  }

  /**
   * Data provider for disallowed environments.
   *
   * @phpstan-return array<string, array{string}>
   */
  public static function disallowedEnvironmentProvider(): array {
    return [
      'dev' => [EnvironmentEnum::Dev->value],
      'stage' => [EnvironmentEnum::Stage->value],
      'prod' => [EnvironmentEnum::Prod->value],
    ];
  }

  /**
   * Tests that decisions are deleted in local environment.
   */
  public function testDeletesDecisionsInLocalEnvironment(): void {
    $this->assertDecisionsAreDeleted(EnvironmentEnum::Local->value);
  }

  /**
   * Tests that decisions are deleted in test environment.
   */
  public function testDeletesDecisionsInTestEnvironment(): void {
    $this->assertDecisionsAreDeleted(EnvironmentEnum::Test->value);
  }

  /**
   * Tests that a custom date argument is used in the query.
   */
  public function testUsesCustomDateFrom(): void {
    $dateFrom = '2020-06-15';
    $formattedDate = $this->formatDecisionDate($dateFrom);

    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()->willReturn(EnvironmentEnum::Local->value);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn(
      $this->mockNodeStorage([42], $formattedDate)->reveal(),
    );

    $tester = $this->createCommandTester(
      $environmentResolver->reveal(),
      $entityTypeManager->reveal(),
    );

    $tester->execute(['date-from' => $dateFrom]);
    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  /**
   * Tests that deletion continues in batches until no ids are returned.
   */
  public function testDeletesDecisionsInBatches(): void {
    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()->willReturn(EnvironmentEnum::Local->value);

    $query = $this->prophesize(QueryInterface::class);
    $query->accessCheck(FALSE)->willReturn($query->reveal());
    $query->condition('type', 'decision')->willReturn($query->reveal());
    $query->condition('field_decision_date', Argument::type('string'), '<')->willReturn($query->reveal());
    $query->range(0, 100)->willReturn($query->reveal());
    $query->execute()->willReturn([1], [2], []);

    $nodeStorage = $this->prophesize(EntityStorageInterface::class);
    $nodeStorage->getQuery()->willReturn($query->reveal());

    foreach ([1, 2] as $id) {
      $node = $this->prophesize(ContentEntityInterface::class);
      $node->delete()->shouldBeCalled();
      $nodeStorage->load($id)->willReturn($node->reveal());
    }

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $tester = $this->createCommandTester(
      $environmentResolver->reveal(),
      $entityTypeManager->reveal(),
    );

    $tester->execute([]);
    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  /**
   * Asserts that matching decision nodes are deleted for given environment.
   */
  private function assertDecisionsAreDeleted(string $environment): void {
    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()->willReturn($environment);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn(
      $this->mockNodeStorage([1, 2])->reveal(),
    );

    $tester = $this->createCommandTester(
      $environmentResolver->reveal(),
      $entityTypeManager->reveal(),
    );

    $tester->execute([]);
    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
  }

  /**
   * Creates a command tester for the database cleaner command.
   */
  private function createCommandTester(
    EnvironmentResolverInterface $environmentResolver,
    EntityTypeManagerInterface $entityTypeManager,
  ): CommandTester {
    $command = new DevelopmentDatabaseCleanerCommand($entityTypeManager, $environmentResolver);

    return new CommandTester($command);
  }

  /**
   * Mocks node storage that returns and deletes the given node ids.
   *
   * @phpstan-param list<int> $nodeIds
   *
   * @phpstan-return \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Entity\EntityStorageInterface>
   */
  private function mockNodeStorage(array $nodeIds, ?string $expectedDate = NULL): object {
    $query = $this->prophesize(QueryInterface::class);
    $query->accessCheck(FALSE)->willReturn($query->reveal());
    $query->condition('type', 'decision')->willReturn($query->reveal());

    if ($expectedDate !== NULL) {
      $query->condition('field_decision_date', $expectedDate, '<')
        ->shouldBeCalled()
        ->willReturn($query->reveal());
    }
    else {
      $query->condition('field_decision_date', Argument::type('string'), '<')
        ->willReturn($query->reveal());
    }

    $query->range(0, 100)->willReturn($query->reveal());
    $query->execute()->willReturn($nodeIds, []);

    $nodeStorage = $this->prophesize(EntityStorageInterface::class);
    $nodeStorage->getQuery()->willReturn($query->reveal());

    foreach ($nodeIds as $id) {
      $node = $this->prophesize(ContentEntityInterface::class);
      $node->delete()->shouldBeCalled();
      $nodeStorage->load($id)->willReturn($node->reveal());
    }

    return $nodeStorage;
  }

  /**
   * Formats a date the same way as the command does.
   */
  private function formatDecisionDate(string $dateFrom): string {
    $date = new \DateTime($dateFrom);
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));

    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

}
