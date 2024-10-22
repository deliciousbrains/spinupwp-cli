<?php

use App\Repositories\ConfigRepository as Configuration;
use App\Repositories\SpinupWpRepository;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Kernel;
use PHPUnit\Framework\ExpectationFailedException;

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


test('sites search prompts for search term', function () use ($response) {
    $this->clientMock->shouldReceive('request')->with('GET', 'sites?page=1&limit=100', [])->andReturn(
        new Response(200, [], listResponseJson($response))
    );

    $result = $this->artisan('sites:search --format table')
        ->expectsQuestion('Enter a search term', 'hellfish')
        ->expectsQuestion('Enter the number of the site to view', 1)
        ->expectsTable(
            ['ID', 'Server ID', 'Domain', 'Site User', 'PHP Version', 'Page Cache', 'HTTPS'],
            [
                [
                    1,
                    1,
                    'hellfishmedia.com',
                    'hellfish',
                    '8.0',
                    'Enabled',
                    'Enabled',
                ]
            ]
        )
        ->expectsConfirmation('Do you want to SSH into this site?', 'yes')

        // It's possible that since we're running another command, that we accept this one as working and relly on sites:ssh tests to pass.
        ->expectsOutput('Establishing a secure connection to [hellfishmedia] as [hellfish]...')
        ->assertExitCode(0);
});
