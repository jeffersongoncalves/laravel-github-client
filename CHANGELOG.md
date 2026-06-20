# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/jeffersongoncalves/laravel-github-client/commits/main)

### Added

- Initial release.
- `GitHubClient` REST wrapper with tri-state `repoStatus()` (exists / gone / unknown).
- `fetchRepo()`, `fetchDefaultBranchForSlug()`, `subdirectoryHasReadme()`, `fetchManifest()`, `fetchBranches()`, and `fileExists()` helpers.
- Built-in rate-limit detection that throws `GitHubRateLimitException` on a primary (403 + `X-RateLimit-Remaining: 0`) or secondary (403/429 + `Retry-After`) limit.
- Configurable token, user agent, and timeout via `config/github-client.php`.
