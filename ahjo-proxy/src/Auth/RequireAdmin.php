<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Requires a Helsinki profiili access token in the `X-Access-Token` header.
 *
 * @see \App\Auth\AccessTokenVerifier
 * @see \App\EventSubscriber\AuthSubscriber
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RequireAdmin {

}
