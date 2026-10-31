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

}
