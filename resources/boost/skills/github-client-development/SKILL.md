---
name: github-client-development
description: Development guide for the Laravel GitHub Client package - a lightweight GitHub REST API wrapper with tri-state repository status detection and rate-limit handling
---

## When to use this skill

- Adding new GitHub REST or raw-content helpers to `GitHubClient`
- Adjusting rate-limit detection or the `GitHubRateLimitException` contract
- Tuning the token / user-agent / timeout configuration
- Writing tests for GitHub API interactions with `Http::fake()`
- Understanding the tri-state `repoStatus()` semantics

## Setup

### Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- spatie/laravel-package-tools ^1.14.0
- A GitHub Personal Access Token (optional, raises rate limits and unlocks private repos)

### Installation

```bash
composer require jeffersongoncalves/laravel-github-client
```

### Publish Config

```bash
php artisan vendor:publish --tag=github-client-config
```

### Environment Variables

```env
GITHUB_TOKEN=ghp_your_personal_access_token
GITHUB_USER_AGENT=laravel-github-client
GITHUB_TIMEOUT=8
```

## Architecture

### Namespace Structure

```
JeffersonGoncalves\GitHubClient\
    GitHubClientServiceProvider     # Registers the config file only
    GitHubClient                    # Static REST / raw-content client
    Exceptions\
        GitHubRateLimitException    # Thrown on a primary or secondary rate limit
```

### Service Provider

The provider is intentionally minimal — it only registers the config file via
`spatie/laravel-package-tools`:

```php
public function configurePackage(Package $package): void
{
    $package
        ->name('laravel-github-client')
        ->hasConfigFile();
}
```

The package short name is `github-client`, so the published config lives at
`config/github-client.php` and the publish tag is `github-client-config`.

## Public API

```php
use JeffersonGoncalves\GitHubClient\GitHubClient;

// Tri-state existence probe — distinguishes a definitive 404 from doubt
GitHubClient::repoStatus('owner/repo');
// GitHubClient::REPO_EXISTS  — 200
// GitHubClient::REPO_GONE    — 404 (deleted/renamed/private)
// GitHubClient::REPO_UNKNOWN — network error, 5xx, 451, or other refusal

GitHubClient::fetchRepo('owner/repo');                    // array<string,mixed>|null
GitHubClient::fetchDefaultBranchForSlug('owner/repo');    // ?string
GitHubClient::subdirectoryHasReadme('owner/repo', 'dir'); // bool
GitHubClient::fetchManifest('owner/repo', 'main', 'composer.json'); // array<string,mixed>|null
GitHubClient::fetchBranches('owner/repo');                // list<string>
GitHubClient::fileExists('owner/repo', 'main', 'Dockerfile'); // bool
```

## Rate-Limit Detection

Detection lives in `throwIfRateLimited()` and runs **outside** the network
try/catch so the exception propagates to the caller instead of being swallowed
and logged as a generic fetch failure.

```php
private static function throwIfRateLimited(Response $response): void
{
    $status = $response->status();

    if ($status !== 403 && $status !== 429) {
        return;
    }

    $retryAfterHeader = $response->header('Retry-After');
    $remaining = $response->header('X-RateLimit-Remaining');

    // Not a rate limit: a plain 403 with remaining > 0 and no Retry-After.
    if ($remaining !== '0' && $retryAfterHeader === '') {
        return;
    }

    $retryAfter = $retryAfterHeader !== ''
        ? (int) $retryAfterHeader
        : ((int) $response->header('X-RateLimit-Reset')) - time();

    throw new GitHubRateLimitException(max(60, $retryAfter));
}
```

- **Primary limit**: 403 + `X-RateLimit-Remaining: 0` → `retryAfter` from `X-RateLimit-Reset - time()`
- **Secondary/abuse limit**: 403/429 + `Retry-After` → `retryAfter` from the header
- `retryAfter` is floored at 60 seconds

Catch it in a queued job and `release($e->retryAfter)` rather than burning attempts.

## Configuration

```php
// config/github-client.php
return [
    'token' => env('GITHUB_TOKEN'),                       // falls back to config('services.github.token')
    'user_agent' => env('GITHUB_USER_AGENT', 'laravel-github-client'),
    'timeout' => (int) env('GITHUB_TIMEOUT', 8),
];
```

The token, user agent, and timeout are read lazily via private helpers
(`token()`, `userAgent()`, `timeout()`) so config can be overridden at runtime
(e.g. in tests).

## Testing Patterns

### Mocking GitHub responses

```php
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GitHubClient\GitHubClient;

it('reports a repo as gone on a 404', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    expect(GitHubClient::repoStatus('owner/repo'))->toBe(GitHubClient::REPO_GONE);
});
```

### Asserting a thrown rate limit

```php
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;

it('throws on a 429 honouring Retry-After', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 429, ['Retry-After' => '120']),
    ]);

    try {
        GitHubClient::repoStatus('owner/repo');
        $this->fail('Expected GitHubRateLimitException.');
    } catch (GitHubRateLimitException $e) {
        expect($e->retryAfter)->toBe(120);
    }
});
```

### Testing raw-content HEADs

```php
it('returns false when a file HEAD fails', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 404),
    ]);

    expect(GitHubClient::fileExists('owner/repo', 'main', 'missing.json'))->toBeFalse();
});
```

## Dev Commands

```bash
# Run tests
vendor/bin/pest

# Run static analysis (PHPStan level 5 + Larastan)
vendor/bin/phpstan analyse

# Format code (Pint, Laravel preset)
vendor/bin/pint
```
