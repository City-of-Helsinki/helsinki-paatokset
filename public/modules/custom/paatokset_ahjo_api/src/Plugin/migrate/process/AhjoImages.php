<?php

declare(strict_types=1);

namespace Drupal\paatokset_ahjo_api\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\Exception\FileExistsException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements 'ahjo_images' process plugin.
 *
 * Ahjo decision content includes HTML that may contain images with data URLs
 * (i.e., images embedded directly in the HTML). This process plugin extracts
 * those images and saves them as files in the filesystem, instead of storing
 * them in the database.
 *
 * @MigrateProcessPlugin(
 *  id = "ahjo_images"
 * )
 */
final class AhjoImages extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The file system service.
   */
  private FileSystemInterface $filesystem;

  /**
   * The file url generator.
   */
  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Base uri.
   */
  private string $baseUri;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->filesystem = $container->get(FileSystemInterface::class);
    $instance->fileUrlGenerator = $container->get(FileUrlGeneratorInterface::class);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->baseUri = $configuration['base_uri'] ?? 'public://ahjo-images';
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value) || !str_contains($value, '<img')) {
      return $value;
    }

    $dom = new \DOMDocument();
    if (!$dom->loadHTML($value)) {
      return $value;
    }

    $images = $dom->getElementsByTagName('img');

    $this->filesystem->prepareDirectory($this->baseUri, FileSystemInterface::CREATE_DIRECTORY);

    foreach ($images as $image) {
      $src = $image->getAttribute('src');

      // Detect base64 encoded data-urls.
      if (preg_match('/^data:(.*?);base64,(.*)$/', $src, $matches)) {
        try {
          $file = $this->convertToFile(base64_decode($matches[2]), $matches[1]);
        }
        catch (\InvalidArgumentException) {
          // Do not touch the existing HTML markup if the processing fails.
          continue;
        }

        $image->setAttribute('src', $this->fileUrlGenerator->generateString($file->getFileUri()));
        $image->setAttribute('data-entity-uuid', $file->uuid());
        $image->setAttribute('data-entity-type', 'file');
      }
    }

    return $dom->saveHTML();
  }

  /**
   * Saves file to Drupal filesystem.
   *
   * @param mixed $data
   *   File data.
   * @param string $mimeType
   *   File mime type.
   *
   * @return \Drupal\file\FileInterface
   *   File entity.
   */
  private function convertToFile(mixed $data, string $mimeType): FileInterface {
    $filename = hash('sha256', $data) . '.' . $this->mimeToExtension($mimeType);
    $uri = $this->baseUri . '/' . $filename;

    try {
      $this->filesystem->saveData($data, $uri, FileExists::Error);
    }
    catch (FileExistsException) {
      // Filenames are hashed contents of the file, so if
      // the file exists, it has been processed previously.
      if ($file = $this->loadExistingFile($uri)) {
        return $file;
      }
    }

    // Create Drupal file entity to track the file.
    $file = $this->entityTypeManager
      ->getStorage('file')
      ->create([
        'uri' => $uri,
      ]);

    /** @var \Drupal\file\FileInterface $file */
    $file->setTemporary();
    $file->setMimeType($mimeType);
    $file->save();

    return $file;
  }

  /**
   * Loads existing file by uri.
   *
   * @param string $uri
   *   File uri.
   *
   * @return \Drupal\file\FileInterface|null
   *   File entity.
   */
  private function loadExistingFile(string $uri): ?FileInterface {
    $fids = $this->entityTypeManager->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uri', $uri)
      ->execute();

    if ($fids) {
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->load(reset($fids));

      if ($file instanceof FileInterface) {
        return $file;
      }
    }

    return NULL;
  }

  /**
   * Maps MIME types to file extensions.
   *
   * @param string $mime
   *   The MIME type.
   *
   * @return string
   *   The file extension
   */
  private function mimeToExtension(string $mime): string {
    return match ($mime) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/gif' => 'gif',
      'image/webp' => 'webp',
      default => throw new \InvalidArgumentException('Invalid mime type: ' . $mime),
    };
  }

}
