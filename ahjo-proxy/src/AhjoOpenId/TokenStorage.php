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
 * The storage directory should be on a persistent volume. Redis is not
 * used, because Drupal flushes the shared Redis on every deployment.
 */
final class TokenStorage {

  private const string TOKEN_FILE = 'ahjo-token.json';
  private const string OLD_TOKEN_FILE = 'ahjo-token.old.json';
  private const string STATE_FILE = 'openid-state.json';

  /**
   * How long OpenID state is valid in seconds.
   */
  private const int STATE_TTL = 600;

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
   *
   * The old token is kept to help with debugging issues. The previous
   * token is invalid after refreshing, so it is not strictly necessary.
   * However, clearing the current token makes it easier to detect that
   * an invalid token is used, in case the refresh fails.
   */
  public function clearToken(): void {
    $content = $this->read(self::TOKEN_FILE);
    if ($content) {
      $this->write(self::OLD_TOKEN_FILE, $content);
      $this->write(self::TOKEN_FILE, '');
    }
  }

  /**
   * Creates and stores a new OpenID state value.
   */
  public function createState(): string {
    $state = bin2hex(random_bytes(16));
    $this->write(self::STATE_FILE, json_encode([
      'state' => $state,
      'created' => time(),
    ], JSON_THROW_ON_ERROR));

    return $state;
  }

  /**
   * Validates and consumes OpenID state value.
   *
   * @return bool
   *   TRUE if the state matches the stored state.
   */
  public function consumeState(string $state): bool {
    $content = $this->read(self::STATE_FILE);
    if (!$content) {
      return FALSE;
    }

    // State can be used only once.
    $this->filesystem->remove($this->path(self::STATE_FILE));

    try {
      $data = json_decode($content, TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return FALSE;
    }

    return is_array($data)
      && is_string($data['state'] ?? NULL)
      && is_int($data['created'] ?? NULL)
      && $data['created'] + self::STATE_TTL >= time()
      && hash_equals($data['state'], $state);
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
