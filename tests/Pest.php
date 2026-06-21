<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GitHubClient\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');
