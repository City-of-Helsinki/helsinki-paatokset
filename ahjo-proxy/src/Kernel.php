<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Ahjo proxy kernel.
 */
final class Kernel extends BaseKernel {

  use MicroKernelTrait;

  /**
   * {@inheritdoc}
   *
   * Runtime cache can be moved to a writable location (like /tmp) with
   * APP_CACHE_DIR, so that the application directory can stay read-only.
   */
  public function getCacheDir(): string {
    if (isset($_SERVER['APP_CACHE_DIR'])) {
      return $_SERVER['APP_CACHE_DIR'] . '/' . $this->environment;
    }

    return parent::getCacheDir();
  }

  /**
   * {@inheritdoc}
   *
   * The build dir (compiled container etc.) is generated at image build
   * time and is read-only at runtime, so it stays in the project dir.
   */
  public function getBuildDir(): string {
    return $this->getProjectDir() . '/var/cache/' . $this->environment;
  }

}
