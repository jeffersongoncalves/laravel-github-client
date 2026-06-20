<?php

namespace JeffersonGoncalves\GitHubClient\Tests;

use JeffersonGoncalves\GitHubClient\GitHubClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GitHubClientServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('github-client.token', 'fake-token');
        $app['config']->set('github-client.user_agent', 'laravel-github-client-tests');
        $app['config']->set('github-client.timeout', 5);
    }
}
