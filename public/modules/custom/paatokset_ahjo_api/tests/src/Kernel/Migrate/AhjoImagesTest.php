<?php

declare(strict_types=1);

namespace Drupal\Tests\paatokset_ahjo_api\Kernel\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\KernelTests\Core\File\FileTestBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paatokset_ahjo_api\Plugin\migrate\process\AhjoImages;

/**
 * Tests the `ahjo_images` process plugin.
 *
 * @group paatokset_ahjo_api
 */
class AhjoImagesTest extends FileTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'system', 'file', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');

    $this->container->get('stream_wrapper_manager')->registerWrapper('temporary', 'Drupal\Core\StreamWrapper\TemporaryStream', StreamWrapperInterface::LOCAL_NORMAL);

    $directory = 'temporary://ahjo-images';
    $this->container
      ->get(FileSystemInterface::class)
      ->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
  }

  /**
   * Tests successful conversions.
   */
  public function testConversion(): void {
    $data_sets = [
      [
        '<body><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=" alt=""></body>',
        'temporary://ahjo-images/431ced6916a2a21a156e38701afe55bbd7f88969fbbfc56d7fe099d47f265460.png',
      ],
      [
        '<body><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==" alt=""></body>',
        'temporary://ahjo-images/bc09c2590d2502c8ffaf1a3c09aa89df222e03d186a8daa0c7fce6321fb6e928.png',
      ],
      [
        '<body><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M/wHwAEBgIApD5fRAAAAABJRU5ErkJggg==" alt=""></body>',
        'temporary://ahjo-images/761523e5d2ef1e871b506467348fa22c24d9bf3d7466f7138712b15c5d9310bb.png',
      ],
    ];

    $fileStorage = $this->container
      ->get(EntityTypeManagerInterface::class)
      ->getStorage('file');

    // File entry already exists.
    $fileStorage->create([
      'uri' => 'temporary://ahjo-images/bc09c2590d2502c8ffaf1a3c09aa89df222e03d186a8daa0c7fce6321fb6e928.png',
    ]);

    // File already exists.
    $this->createUri('ahjo-images/761523e5d2ef1e871b506467348fa22c24d9bf3d7466f7138712b15c5d9310bb.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M/wHwAEBgIApD5fRAAAAABJRU5ErkJggg=='), scheme: 'temporary');

    foreach ($data_sets as $data) {
      [$markup, $destination] = $data;
      $transformed = $this->doTransform($markup, [
        'base_uri' => 'temporary://ahjo-images',
      ]);

      $this->assertFileExists($destination);
      $this->assertStringNotContainsString('data:', $transformed);

      $fids = $fileStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uri', $destination)
        ->execute();

      $this->assertNotEmpty($fids);
    }
  }

  /**
   * Tests errors.
   */
  public function testErrors(): void {
    $data_sets = [
      '<body><img src="https://example.com/external-url.png" alt=""></body>',
      '<body><img src="data:application/pdf;base64,unknown-mime-type" alt=""></body>',
    ];

    foreach ($data_sets as $markup) {
      $transformed = $this->doTransform($markup, [
        'base_uri' => 'temporary://ahjo-images',
      ]);

      // Existing markup was not modified.
      $this->assertStringContainsString($markup, $transformed);
    }
  }

  /**
   * Do an import using the destination.
   *
   * @param string $value
   *   Input markup.
   * @param array $configuration
   *   Process plugin configuration settings.
   *
   * @return string
   *   The transformed markup.
   */
  private function doTransform(string $value, array $configuration = []): string {
    $plugin = AhjoImages::create($this->container, $configuration, 'ahjo_images', []);
    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();
    $row = new Row([], []);

    return $plugin->transform($value, $executable, $row, 'foo');
  }

}
