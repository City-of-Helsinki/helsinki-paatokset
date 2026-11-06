<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check for OpenShift probes.
 */
final class HealthController {

  /**
   * Returns OK.
   */
  #[Route('/healthz', name: 'healthz', methods: ['GET'])]
  public function __invoke(): JsonResponse {
    return new JsonResponse(['status' => 'ok']);
  }

}
