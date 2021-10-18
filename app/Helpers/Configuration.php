<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Arr;

class Configuration
{
    protected array $config;

    public function __construct()
    {
        $this->config = $this->readConfig();
    }

    public function isConfigured(): bool
    {
        if (!file_exists($this->configFilePath())) {
            return false;
        }
        return true;
    }

    public function get(string $key, $profile = 'default'): string
    {
        if (empty($this->config)) {
            return '';
        }

        if (!$this->teamExists($profile)) {
            return '';
        }

        $profilenConfig = $this->config[$profile];

        if (!isset($profilenConfig[$key])) {
            throw new Exception("The key {$key} doesn't exist in the configuration");
        }

        return $profilenConfig[$key];
    }

    public function set(string $key, string $value, $profile = 'default')
    {
        $config = $this->config;
        Arr::set($config, "{$profile}.{$key}", $value);
        file_put_contents($this->configFilePath(), json_encode($config, JSON_PRETTY_PRINT));
        $this->config = $config;
    }

    public function teamExists(string $profile): bool
    {
        return isset($this->config[$profile]);
    }

    protected function readConfig(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $configFile = file_get_contents($this->configFilePath());
        return json_decode($configFile, true);
    }

    public function configFilePath(): string
    {
        return $this->getConfigPath() . 'config.json';
    }

    protected function getConfigPath(): string
    {
        $userHome = config('app.config_path') . '/.spinupwp/';
        if (!file_exists($userHome)) {
            mkdir($userHome);
        }
        return $userHome;
    }
}
