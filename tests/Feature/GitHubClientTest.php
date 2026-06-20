<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GitHubClient\Exceptions\GitHubRateLimitException;
use JeffersonGoncalves\GitHubClient\GitHubClient;

it('reports a repo as existing on a 200', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response(['full_name' => 'owner/repo'], 200),
    ]);

    expect(GitHubClient::repoStatus('owner/repo'))->toBe(GitHubClient::REPO_EXISTS);
});

it('reports a repo as gone on a 404', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    expect(GitHubClient::repoStatus('owner/repo'))->toBe(GitHubClient::REPO_GONE);
});

it('reports a repo as unknown on a 500', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 500),
    ]);

    expect(GitHubClient::repoStatus('owner/repo'))->toBe(GitHubClient::REPO_UNKNOWN);
});

it('throws on a 403 with no remaining rate limit', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 300),
        ]),
    ]);

    expect(fn () => GitHubClient::repoStatus('owner/repo'))
        ->toThrow(GitHubRateLimitException::class);
});

it('throws on a 429 honouring the Retry-After header', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 429, [
            'Retry-After' => '120',
        ]),
    ]);

    try {
        GitHubClient::repoStatus('owner/repo');
        $this->fail('Expected GitHubRateLimitException to be thrown.');
    } catch (GitHubRateLimitException $e) {
        expect($e->retryAfter)->toBe(120);
    }
});

it('parses branch names', function () {
    Http::fake([
        'api.github.com/repos/*/branches*' => Http::response([
            ['name' => 'main'],
            ['name' => 'develop'],
            ['notname' => 'ignored'],
        ], 200),
    ]);

    expect(GitHubClient::fetchBranches('owner/repo'))->toBe(['main', 'develop']);
});

it('returns true when a file HEAD succeeds', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 200),
    ]);

    expect(GitHubClient::fileExists('owner/repo', 'main', 'composer.json'))->toBeTrue();
});

it('returns false when a file HEAD fails', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 404),
    ]);

    expect(GitHubClient::fileExists('owner/repo', 'main', 'missing.json'))->toBeFalse();
});
