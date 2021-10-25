<?php

namespace App\Commands;

use App\Helpers\Configuration;
use DeliciousBrains\SpinupWp\SpinupWp;
use Exception;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

abstract class BaseCommand extends Command
{
    protected Configuration $config;

    protected SpinupWp $spinupwp;

    protected bool $requiresToken = true;

    public function __construct(Configuration $configuration)
    {
        parent::__construct();

        $this->config = $configuration;
    }

    public function handle(): int
    {
        if ($this->requiresToken && !$this->config->isConfigured()) {
            $this->error("You must first run 'spinupwp configure' in order to set up your API token.");
            return 1;
        }

        try {
            $this->setUpSpinupWP();

            $payload = $this->action();

            $this->format($payload);

            return 0;
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function setUpSpinupWP(): void
    {
        $client = null;

        if (config('app.env') !== 'production') {
            $client = new HttpClient([
                'base_uri'    => config('app.api_url'),
                'http_errors' => false,
                'headers'     => [
                    'Authorization' => "Bearer {$this->apiToken()}",
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
            ]);
        }

        if ($this->config->isConfigured()) {
            $this->spinupwp = $this->app->makeWith(SpinupWp::class, ['apiKey' => $this->apiToken(), 'client' => $client]);
        }
    }

    protected function apiToken(): string
    {
        $apiToken = $this->config->get('api_token', $this->profile());
        if (!$apiToken) {
            throw new Exception("The API token for the profile {$this->profile()} is not yet configured");
        }
        return $apiToken;
    }

    protected function profile(): string
    {
        if (is_string($this->option('profile'))) {
            return $this->option('profile');
        }
        return 'default';
    }

    protected function format($resource)
    {
        if ($this->displayFormat() === 'table') {
            return $this->toTable($resource);
        }
        return $this->toJson($resource);
    }

    protected function displayFormat(): string
    {
        if (is_string($this->option('format'))) {
            return $this->option('format');
        }
        return $this->config->get('format', $this->profile());
    }

    protected function toJson($resource): void
    {
        $this->line(json_encode($resource->toArray(), JSON_PRETTY_PRINT));
    }

    protected function toTable($resource): void
    {
        $tableHeaders = [];

        if ($resource instanceof Collection) {
            $firstElement = $resource->first();

            if (!is_array($firstElement)) {
                $firstElement = $firstElement->toArray();
            }

            $tableHeaders = array_keys($firstElement);

            $rows = [];

            $resource->each(function ($item) use (&$rows) {
                if (!is_array($item)) {
                    $item->toArray();
                }

                $row = array_map(function ($value) {
                    if (is_array($value)) {
                        $value = '';
                    }
                    if (is_bool($value)) {
                        $value = $value ? 'yes' : 'no';
                    }
                    return $value;
                }, array_values($item));

                $rows[] = $row;
            });
        }

        $this->table(
            $tableHeaders,
            $rows,
        );
    }

    abstract protected function action();
}