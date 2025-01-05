<?php

use GuzzleHttp\Psr7\Response;

$response = [
    [
        'id'          => 1,
        'server_id'   => 1,
        'domain'      => 'hellfishmedia.com',
        'site_user'   => 'hellfish',
        'php_version' => '8.0',
        'page_cache'  => [
            'enabled' => true,
        ],
        'https'       => [
            'enabled' => true,
        ],
    ],
    [
        'id'          => 2,
        'server_id'   => 2,
        'domain'      => 'staging.hellfishmedia.com',
        'site_user'   => 'staging-hellfish',
        'php_version' => '8.0',
        'page_cache'  => [
            'enabled' => false,
        ],
        'https'       => [
            'enabled' => false,
        ],
    ],
];
beforeEach(function () use ($response) {
    setTestConfigFile();
});

afterEach(function () {
    deleteTestConfigFile();
});

it('list command with no api token configured', function () {
    $this->spinupwp->setApiKey('');
    $this->artisan('sites:search --profile=johndoe')
        ->assertExitCode(1);
});

test('sites search does not support json formatting', function () use ($response) {
    $this->clientMock->shouldReceive('request')->with('GET', 'sites?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson($response))
    );
    $this->artisan('sites:search --format json')->expectsOutput('Format not supported');
});

test('empty sites search', function () {
    $this->clientMock->shouldReceive('request')->with('GET', 'sites?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson([]))
    );
    $this->artisan('sites:search')->expectsOutput('No sites found.');
});


test('search prompts for term and site selection', function () use ($response) {
    $this->clientMock->shouldReceive('request')->with('GET', 'sites?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson($response))
    );

    $this->artisan('sites:search --format table')
        ->expectsQuestion('Enter a search term', 'hellfish')
        ->expectsQuestion('Enter the number of the site to view', 1)
        ->expectsConfirmation('Do you want to SSH into: hellfishmedia.com (hellfish)', 'yes');
});
