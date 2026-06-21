# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/jeffersongoncalves/laravel-github-client/commits/master/compare/v1.1.0...master)

### Added

- Initial release.
- `GitHubClient` REST wrapper with tri-state `repoStatus()` (exists / gone / unknown).
- `fetchRepo()`, `fetchDefaultBranchForSlug()`, `subdirectoryHasReadme()`, `fetchManifest()`, `fetchBranches()`, and `fileExists()` helpers.
- Built-in rate-limit detection that throws `GitHubRateLimitException` on a primary (403 + `X-RateLimit-Remaining: 0`) or secondary (403/429 + `Retry-After`) limit.
- Configurable token, user agent, and timeout via `config/github-client.php`.

## [v1.1.0](https://github.com/jeffersongoncalves/laravel-github-client/commits/master/compare/master...v1.1.0) - 2026-06-21

### What's Changed

* build(deps): Bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/jeffersongoncalves/laravel-github-client/pull/1

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/jeffersongoncalves/laravel-github-client/pull/1

**Full Changelog**: https://github.com/jeffersongoncalves/laravel-github-client/compare/v1.0.0...v1.1.0
