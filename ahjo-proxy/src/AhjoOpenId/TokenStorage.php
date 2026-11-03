<?php

declare(strict_types=1);

namespace App\AhjoOpenId;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * File based storage for the Ahjo auth token.
 *
 * We assume that only one process ever refreshes the tokens. Every other
 * consumer only reads.
 */
final class TokenStorage {

  private const string TOKEN_FILE = 'ahjo-token.json';
  private const string OLD_TOKEN_FILE = 'ahjo-token.old.json';

  /**
   * The filesystem.
   */
  private readonly Filesystem $filesystem;

  /**
   * Lazily initialized lock factory.
   */
  private ?LockFactory $lockFactory = NULL;

  public function __construct(
    private readonly string $storageDir,
  ) {
    $this->filesystem = new Filesystem();
  }

  /**
   * Gets the stored token.
   *
   * @throws \InvalidArgumentException
   *   If the token is missing or invalid.
   */
  public function getToken(): AhjoAuthToken {
    $content = $this->read(self::TOKEN_FILE);
    if ($content === NULL || $content === '') {
      throw new \InvalidArgumentException('Token not found');
    }

    return AhjoAuthToken::fromJson($content);
  }

  /**
   * Stores the token.
   */
  public function setToken(AhjoAuthToken $token): void {
    $this->write(self::TOKEN_FILE, json_encode($token, JSON_THROW_ON_ERROR));
  }

  /**
   * Clears the current token.
   */
  public function clearToken(): void {
    $content = $this->read(self::TOKEN_FILE);
    if ($content) {
      $this->write(self::OLD_TOKEN_FILE, $content);
      $this->write(self::TOKEN_FILE, '');
    }
  }

  /**
   * Creates a lock that is shared between processes using this storage.
   */
  public function createLock(string $name): LockInterface {
    if (!$this->lockFactory) {
      $this->ensureStorageDir();
      $this->lockFactory = new LockFactory(new FlockStore($this->storageDir));
    }

    return $this->lockFactory->createLock($name, autoRelease: TRUE);
  }

  /**
   * Reads a file from the storage.
   */
  private function read(string $file): ?string {
    $path = $this->path($file);
    if (!is_file($path)) {
      return NULL;
    }

    $content = file_get_contents($path);

    return $content === FALSE ? NULL : $content;
  }

  /**
   * Writes a file to the storage atomically.
   */
  private function write(string $file, string $content): void {
    $this->ensureStorageDir();

    $path = $this->path($file);
    $this->filesystem->dumpFile($path, $content);
    $this->filesystem->chmod($path, 0600);
  }

  /**
   * Gets file path.
   */
  private function path(string $file): string {
    return $this->storageDir . '/' . $file;
  }

  /**
   * Creates the storage directory if it does not exist.
   */
  private function ensureStorageDir(): void {
    if (!is_dir($this->storageDir)) {
      $this->filesystem->mkdir($this->storageDir, 0700);
    }
  }

}
