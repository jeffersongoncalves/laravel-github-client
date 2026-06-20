## Laravel GitHub Client

### Overview

A lightweight GitHub REST API client for Laravel. It wraps `api.github.com` and
`raw.githubusercontent.com` calls behind a small static `GitHubClient`, threads
the API token, and centralises rate-limit detection so callers get a thrown
`GitHubRateLimitException` rather than a silent null during a limit window.

### Key Concepts

- **GitHubClient**: Static client exposing the repo, branch, README, manifest, and file-existence helpers
- **GitHubRateLimitException**: Thrown when GitHub signals a primary or secondary rate limit, carrying `retryAfter` seconds
- **Tri-state repo status**: `repoStatus()` returns `REPO_EXISTS`, `REPO_GONE`, or `REPO_UNKNOWN` so callers never prune on doubt

### Public API

@verbatim
<code-snippet name="github-client" lang="php">
use JeffersonGoncalves\GitHubClient\GitHubClient;

GitHubClient::repoStatus('owner/repo');                  // 'exists' | 'gone' | 'unknown'
GitHubClient::fetchRepo('owner/repo');                   // array<string,mixed>|null
GitHubClient::fetchDefaultBranchForSlug('owner/repo');   // ?string
GitHubClient::subdirectoryHasReadme('owner/repo', 'dir'); // bool
GitHubClient::fetchManifest('owner/repo', 'main', 'composer.json'); // array<string,mixed>|null
GitHubClient::fetchBranches('owner/repo');               // list<string>
GitHubClient::fileExists('owner/repo', 'main', 'Dockerfile'); // bool
</code-snippet>
@endverbatim

### Rate Limit Handling

@verbatim
<code-snippet name="rate-limit" lang="php">
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;

try {
    $status = GitHubClient::repoStatus('owner/repo');
} catch (GitHubRateLimitException $e) {
    // Primary limit: 403 + X-RateLimit-Remaining: 0
    // Secondary limit: 403/429 + Retry-After
    $job->release($e->retryAfter); // back off, minimum 60s
}
</code-snippet>
@endverbatim

### Configuration

@verbatim
<code-snippet name="config-keys" lang="php">
// config/github-client.php
'token'      => env('GITHUB_TOKEN'),                       // falls back to services.github.token
'user_agent' => env('GITHUB_USER_AGENT', 'laravel-github-client'),
'timeout'    => (int) env('GITHUB_TIMEOUT', 8),
</code-snippet>
@endverbatim

### Conventions

- All methods are static — there is no facade or container binding
- `repoStatus()` only returns `REPO_GONE` on a definitive 404; 5xx/451/network failures are `REPO_UNKNOWN`
- Network/timeout/DNS failures are logged and surfaced as `null`/`[]`/`false`, never thrown — only rate limits throw
- Rate-limit detection runs outside the network try/catch so the exception propagates to the caller
