<?php

use GuzzleHttp\Psr7\Response;

$response = [
    [
        'id'             => 1,
        'name'           => 'hellfish-media',
        'provider_name'  => 'DigitalOcean',
        'ubuntu_version' => '20.04',
        'ip_address'     => '127.0.0.1',
        'disk_space'     => [
            'total'      => 25210576000,
            'available'  => 17549436000,
            'used'       => 7661140000,
            'updated_at' => '2021-11-03T16:52:48.000000Z',
        ],
        'database'       => [
            'server' => 'mysql-8.0',
        ],
    ],
    [
        'id'             => 2,
        'name'           => 'staging.hellfish-media',
        'provider_name'  => 'DigitalOcean',
        'ubuntu_version' => '20.04',
        'ip_address'     => '127.0.0.1',
        'disk_space'     => [
            'total'      => 25210576000,
            'available'  => 17549436000,
            'used'       => 7661140000,
            'updated_at' => '2021-11-03T16:52:48.000000Z',
        ],
        'database'       => [
            'server' => 'mysql-8.0',
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
    $this->artisan('servers:search --profile=johndoe')
        ->assertExitCode(1);
});

test('sites search does not support json formatting', function () use ($response) {
    $this->clientMock->shouldReceive('request')->with('GET', 'servers?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson($response))
    );
    $this->artisan('servers:search --format json')->expectsOutput('Format not supported');
});

test('empty server search', function () {
    $this->clientMock->shouldReceive('request')->with('GET', 'servers?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson([]))
    );
    $this->artisan('servers:search')->expectsOutput('No servers found.');
});


test('search prompts for term and server selection', function () use ($response) {
    $this->clientMock->shouldReceive('request')->with('GET', 'servers?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson($response))
    );

    $this->artisan('servers:search --format table')
        ->expectsQuestion('Enter a search term', 'hellfish-media')
        ->expectsQuestion('Enter the number of the server to view', 1)
        ->expectsConfirmation('Do you want to SSH into: hellfish-media (127.0.0.1)', 'yes');
});
