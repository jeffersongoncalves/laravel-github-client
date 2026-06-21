<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

it('fetches the repo payload as an array', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response(['full_name' => 'owner/repo', 'default_branch' => 'main'], 200),
    ]);

    expect(GitHubClient::fetchRepo('owner/repo'))
        ->toBe(['full_name' => 'owner/repo', 'default_branch' => 'main']);
});

it('returns null from fetchRepo on a non-2xx', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 404),
    ]);

    expect(GitHubClient::fetchRepo('owner/repo'))->toBeNull();
});

it('resolves the default branch for a slug', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response(['default_branch' => 'develop'], 200),
    ]);

    expect(GitHubClient::fetchDefaultBranchForSlug('owner/repo'))->toBe('develop');
});

it('returns null for the default branch when the repo is missing', function () {
    Http::fake([
        'api.github.com/repos/*' => Http::response('', 404),
    ]);

    expect(GitHubClient::fetchDefaultBranchForSlug('owner/repo'))->toBeNull();
});

it('fetches and decodes a manifest', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response(['name' => 'owner/repo'], 200),
    ]);

    expect(GitHubClient::fetchManifest('owner/repo', 'main', 'composer.json'))
        ->toBe(['name' => 'owner/repo']);
});

it('returns null from fetchManifest on a non-2xx', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 404),
    ]);

    expect(GitHubClient::fetchManifest('owner/repo', 'main', 'composer.json'))->toBeNull();
});

it('reports a subdirectory README as present on a 200', function () {
    Http::fake([
        'api.github.com/repos/*/readme/*' => Http::response(['name' => 'README.md'], 200),
    ]);

    expect(GitHubClient::subdirectoryHasReadme('owner/repo', 'packages/foo'))->toBeTrue();
});

it('reports a subdirectory README as absent on a 404', function () {
    Http::fake([
        'api.github.com/repos/*/readme/*' => Http::response('', 404),
    ]);

    expect(GitHubClient::subdirectoryHasReadme('owner/repo', 'packages/foo'))->toBeFalse();
});

it('sends the bearer token on raw content fetches', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response(['ok' => true], 200),
    ]);

    GitHubClient::fetchManifest('owner/repo', 'main', 'composer.json');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fake-token'));
});

it('applies rate-limit detection to raw content fetches', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 429, ['Retry-After' => '90']),
    ]);

    expect(fn () => GitHubClient::fetchManifest('owner/repo', 'main', 'composer.json'))
        ->toThrow(GitHubRateLimitException::class);
});

it('rawurlencodes path segments while preserving separators', function () {
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response('', 200),
    ]);

    GitHubClient::fileExists('owner/repo', 'feature/x', 'src/My File.php');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'feature/x/src/My%20File.php'));
});

it('rejects an invalid repo slug', function () {
    expect(fn () => GitHubClient::repoStatus('not-a-slug'))
        ->toThrow(InvalidArgumentException::class);
});

it('falls back to the services.github.token config value', function () {
    config()->set('github-client.token', null);
    config()->set('services.github.token', 'services-token');

    Http::fake([
        'api.github.com/repos/*' => Http::response(['full_name' => 'owner/repo'], 200),
    ]);

    GitHubClient::repoStatus('owner/repo');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer services-token'));
});

it('returns REPO_UNKNOWN and logs when the request throws', function () {
    Log::spy();

    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(GitHubClient::repoStatus('owner/repo'))->toBe(GitHubClient::REPO_UNKNOWN);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'GitHubClient outbound fetch failed'
            && $context['context'] === 'github_api')
        ->once();
});
