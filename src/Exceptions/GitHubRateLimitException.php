<?php

namespace JeffersonGoncalves\GitHubClient\Exceptions;

use RuntimeException;

/**
 * Raised when GitHub answers a core-API call with a rate-limit response
 * (primary 403 with `X-RateLimit-Remaining: 0`, or a secondary 403/429 that
 * carries a `Retry-After`). Callers can catch this and back off for
 * `$retryAfter` seconds instead of hammering a limit that won't clear until
 * the window resets.
 */
class GitHubRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("GitHub API rate limit hit; retry in {$retryAfter}s");
    }
}
