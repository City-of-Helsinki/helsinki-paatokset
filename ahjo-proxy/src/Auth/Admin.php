<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\HttpFoundation\Request;

/**
 * An admin identified by a verified access token.
 */
final readonly class Admin {

  public function __construct(
    public string $sub,
    public string $azp,
    public ?string $email,
  ) {
  }

  /**
   * Gets the admin from the request.
   *
   * @throws \LogicException
   *   If the controller does not require an admin.
   *
   * @see \App\Auth\RequireAdmin
   */
  public static function fromRequest(Request $request): self {
    $admin = $request->attributes->get(self::class);
    if (!$admin instanceof self) {
      throw new \LogicException('The request has no admin. Is the controller missing #[RequireAdmin]?');
    }

    return $admin;
  }

}
