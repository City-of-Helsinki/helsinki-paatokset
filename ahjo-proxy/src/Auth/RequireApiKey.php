<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Requires a valid api key in the `api-key` header or query parameter.
 *
 * @see \App\EventSubscriber\AuthSubscriber
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RequireApiKey {

}
