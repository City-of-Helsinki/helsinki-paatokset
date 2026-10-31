<?php

declare(strict_types=1);

namespace App\Tests;

use App\AhjoOpenId\AhjoAuthToken;
use App\AhjoOpenId\TokenStorage;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Resets test state and provides helpers for the token storage.
 */
trait StorageTestTrait {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    self::clearStorage();
    MockResponseFactory::reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    self::clearStorage();
    MockResponseFactory::reset();
    parent::tearDown();
  }

  /**
   * Gets the storage directory used in tests.
   */
  protected static function getStorageDir(): string {
    return dirname(__DIR__) . '/var/test-storage';
  }

  /**
   * Clears the storage directory.
   */
  protected static function clearStorage(): void {
    (new Filesystem())->remove(self::getStorageDir());
  }

  /**
   * Stores a token.
   */
  protected static function setToken(string $token = 'access-token', int $expiresIn = 3600): void {
    (new TokenStorage(self::getStorageDir()))->setToken(new AhjoAuthToken($token, time() + $expiresIn, 'refresh-token'));
  }

}
