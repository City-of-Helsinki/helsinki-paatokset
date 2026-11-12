<?php

/**
 * @file
 * Enabled bundles.
 */

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;

return [
  FrameworkBundle::class => ['all' => TRUE],
  MonologBundle::class => ['all' => TRUE],
];
