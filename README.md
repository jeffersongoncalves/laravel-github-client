<div class="filament-hidden">

![Laravel GitHub Client](https://raw.githubusercontent.com/jeffersongoncalves/laravel-github-client/master/art/jeffersongoncalves-laravel-github-client.png)

</div>

# Laravel GitHub Client

[![Tests](https://github.com/jeffersongoncalves/laravel-github-client/actions/workflows/run-tests.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-github-client/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/jeffersongoncalves/laravel-github-client/actions/workflows/phpstan.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-github-client/actions/workflows/phpstan.yml)
[![Code Style](https://github.com/jeffersongoncalves/laravel-github-client/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-github-client/actions/workflows/fix-php-code-style-issues.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-github-client.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-github-client)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-github-client.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-github-client)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/laravel-github-client.svg?style=flat-square)](LICENSE.md)

A lightweight GitHub REST API client for Laravel. It wraps the `api.github.com` and `raw.githubusercontent.com` calls behind a small static client, threads your API token, and centralises rate-limit detection so callers get a thrown `GitHubRateLimitException` rather than a silent failure during a limit window.

## Features

- **Tri-state repo status** — `repoStatus()` distinguishes a definitive 404 (`gone`) from a transient/unknown failure (`unknown`) so you never prune on doubt
- **Repository metadata** — `fetchRepo()` and `fetchDefaultBranchForSlug()`
- **README detection** — `subdirectoryHasReadme()` for monorepo subfolders
- **Raw content** — `fetchManifest()` to read JSON files and `fileExists()` to HEAD a path without downloading it
- **Branch listing** — `fetchBranches()` returns the branch names
- **Rate-limit aware** — throws `GitHubRateLimitException` on a primary (403 + `X-RateLimit-Remaining: 0`) or secondary (403/429 + `Retry-After`) limit, carrying the `retryAfter` seconds

## Installation

```bash
composer require jeffersongoncalves/laravel-github-client
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="github-client-config"
```

## Configuration

Add to your `.env`:

```env
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxx
```

Create a [Personal Access Token](https://github.com/settings/tokens/new) to lift the unauthenticated rate limit and access private repositories.

### Config Options

```php
// config/github-client.php
return [
    'token' => env('GITHUB_TOKEN'),
    'user_agent' => env('GITHUB_USER_AGENT', 'laravel-github-client'),
    'timeout' => (int) env('GITHUB_TIMEOUT', 8),
];
```

When `github-client.token` is null the client falls back to `config('services.github.token')`.

## Usage

```php
use JeffersonGoncalves\GitHubClient\GitHubClient;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;

// Tri-state existence probe
$status = GitHubClient::repoStatus('jeffersongoncalves/laravel-github-client');
// GitHubClient::REPO_EXISTS | REPO_GONE | REPO_UNKNOWN

// Repository metadata
$repo = GitHubClient::fetchRepo('jeffersongoncalves/laravel-github-client');
$branch = GitHubClient::fetchDefaultBranchForSlug('jeffersongoncalves/laravel-github-client');

// Branches
$branches = GitHubClient::fetchBranches('jeffersongoncalves/laravel-github-client');

// Raw content
$composer = GitHubClient::fetchManifest('jeffersongoncalves/laravel-github-client', 'main', 'composer.json');
$hasDockerfile = GitHubClient::fileExists('jeffersongoncalves/laravel-github-client', 'main', 'Dockerfile');

// Monorepo README detection
$hasReadme = GitHubClient::subdirectoryHasReadme('alpinejs/alpine', 'packages/anchor');
```

### Handling rate limits

```php
try {
    $status = GitHubClient::repoStatus('owner/repo');
} catch (GitHubRateLimitException $e) {
    // Back off for $e->retryAfter seconds (queue jobs can release($e->retryAfter)).
}
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Code Formatting

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
