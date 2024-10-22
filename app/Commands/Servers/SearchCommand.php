<?php

namespace App\Commands\Servers;

use App\Commands\Servers\Servers;
use Illuminate\Support\Str;

class SearchCommand extends Servers
{
    public string|null $keyword;

    protected $signature = 'servers:search
                            {keyword? : Your search term}
                            {--fields= : The fields to output}
                            {--format= : The output format (json or table)}
                            {--profile= : The SpinupWP configuration profile to use}';

    protected $description = 'List all servers';

    protected function action(): int
    {
        $this->keyword = $this->argument('keyword');

        $servers = collect($this->spinupwp->listServers());

        if ($servers->isEmpty()) {
            $this->warn('No servers found.');
            return self::SUCCESS;
        }

        $servers->transform(fn($server) => $this->specifyFields($server, [
            'id',
            'name',
            'ip_address',
            'ubuntu_version',
            'database.server',
        ]));

        if ($this->displayFormat() !== 'table') {
            $this->info("Format not supported");
            return self::FAILURE;
        }

        $this->promptForSearch($servers);

        return self::SUCCESS;
    }

    protected function promptForSearch($servers)
    {
        if (!$this->keyword) {
            $this->keyword = $this->ask('Enter a search term');
        }

        while (true) {
            $filteredServers = $servers->filter(function ($server) {
                return Str::contains($server['Name'], $this->keyword, true) ||
                    Str::contains($server['IP Address'], $this->keyword, true);
            })->values();

            if ($filteredServers->isEmpty()) {
                $this->info('No matching servers found.');
                $this->keyword = $this->ask('Enter a search term');
                continue;
            }

            $this->info("Matching servers:");

            $filteredServers->each(function ($server, $key) {
                $this->line(sprintf("%d: %s (%s)", $key + 1, $server['Name'], $server['IP Address']));
            });

            if ($filteredServers->count() > 1) {
                $selection = $this->ask('Enter the number of the server to view');

                if ($selection === '') {
                    $this->keyword = null;
                    continue;
                }

                if (is_numeric($selection) && $selection > 0 && $selection <= $filteredServers->count()) {
                    $selectedServer = $filteredServers[$selection - 1];
                    $this->format(collect([$selectedServer]));

                    if ($this->confirm('Do you want to SSH into this server?')) {
                        $this->call('servers:ssh', ['server_id' => $selectedServer['ID']]);
                    }

                    break;
                }
            }

            if ($filteredServers->count() === 1) {
                $selectedServer = $filteredServers[0];
                $this->format(collect([$selectedServer]));

                if ($this->confirm('Do you want to SSH into this server?')) {
                    $this->call('servers:ssh', ['server_id' => $selectedServer['ID']]);
                }

                break;
            }

            $this->error('Invalid selection. Please try again.');
        }
    }
}
