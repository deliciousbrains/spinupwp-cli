<?php

namespace App\Commands\Servers;

use App\Commands\Servers\Servers;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchCommand extends Servers
{
    public string|null $keyword;

    protected $signature = 'servers:search
                            {keyword? : Your search term}
                            {--fields= : The fields to output}
                            {--format= : The output format (list or table)}
                            {--profile= : The SpinupWP configuration profile to use}';

    protected $description = 'Search for a server';

    private const SUPPORTED_FORMATS = ['table', 'list'];

    protected function action(): int
    {
        $this->keyword = $this->argument('keyword');

        $servers = collect($this->spinupwp->listServers());

        if ($servers->isEmpty()) {
            $this->warn('No servers found.');
            return self::SUCCESS;
        }

        $servers->transform(
            fn ($server) => $this->specifyFields($server, [
                'id',
                'name',
                'ip_address',
                'ubuntu_version',
                'database.server',
            ]));

        if (! in_array($this->displayFormat(), self::SUPPORTED_FORMATS)) {
            $this->info("Format not supported");
            return self::FAILURE;
        }

        $this->promptForSearch($servers);

        return self::SUCCESS;
    }

    protected function promptForSearch(Collection $servers): void
    {
        if (! $this->keyword) {
            $this->keyword = $this->ask('Enter a search term');
        }

        while (true) {
            $filteredServers = $this->filterServers($servers);

            if ($filteredServers->isEmpty()) {
                $this->info('No matching servers found.');
                $this->keyword = $this->ask('Enter a search term');
                continue;
            }

            $this->info("Matching servers:");

            if ($this->displayFormat() === 'table') {
                $this->format($filteredServers);
            } else {
                $filteredServers->each(function ($server, $key) {
                    $this->line(sprintf("%d: %s (%s)", $key + 1, $server['Name'], $server['IP Address']));
                });
            }

            if ($filteredServers->count() > 1) {
                $selection = $this->ask('Enter the number of the server to view');

                if ($selection === '') {
                    $this->keyword = null;
                    continue;
                }

                if (is_numeric($selection) && $selection > 0 && $selection <= $filteredServers->count()) {
                    $selectedServer = $filteredServers[$selection - 1];

                    $this->handleServerSelection($selectedServer);

                    break;
                }
            }

            if ($filteredServers->count() === 1) {
                $selectedServer = $filteredServers[0];

                $this->handleServerSelection($selectedServer);

                break;
            }

            $this->error('Invalid selection. Please try again.');
        }
    }

    private function filterServers(Collection $servers): Collection
    {
        return $servers->filter(function ($server) {
            return Str::contains($server['Name'], $this->keyword, true) ||
                Str::contains($server['IP Address'], $this->keyword, true) ||
                Str::contains($server['Server ID'], $this->keyword, true);
        })->values()->map(function ($site, $index) {
            return array_merge(['Match' => $index + 1], $site);
        });
    }

    private function handleServerSelection($selectedServer): void
    {
        if ($this->confirm(sprintf("Do you want to SSH into: %s (%s)",
            $selectedServer['Name'],
            $selectedServer['IP Address']
        ))) {
            $this->call('servers:ssh', ['server_id' => $selectedServer['Server ID']]);
        }
    }
}
