<?php

namespace App\Commands\Sites;

use App\Commands\Sites\Sites;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchCommand extends Sites
{
    public string|null $keyword;

    protected $signature = 'sites:search
                            {keyword? : Your search term}
                            {server_id? : Only list sites belonging to this server}
                            {--fields= : The fields to output}
                            {--format= : The output format (list or table)}
                            {--profile= : The SpinupWP configuration profile to use}';

    protected $description = 'Search for sites in your account on in a specific server';

    private const SUPPORTED_FORMATS = ['table', 'list'];

    protected function action(): int
    {
        $this->keyword = $this->argument('keyword');

        $serverId = $this->argument('server_id');

        if ($serverId) {
            $sites = $this->spinupwp->listSites((int) $serverId);
        } else {
            $sites = $this->spinupwp->listSites();
        }

        if ($sites->isEmpty()) {
            $this->warn('No sites found.');
            return self::SUCCESS;
        }

        $sites->transform(
            fn($site) => $this->specifyFields($site, [
                'id',
                'server_id',
                'domain',
                'site_user',
                'php_version',
                'page_cache',
                'https',
            ])
        );

        if (!in_array($this->displayFormat(), self::SUPPORTED_FORMATS)) {
            $this->info("Format not supported");
            return self::FAILURE;
        }

        $this->promptForSearch($sites);

        return self::SUCCESS;
    }

    protected function promptForSearch(Collection $sites): void
    {
        if (!$this->keyword) {
            $this->keyword = $this->ask('Enter a search term');
        }

        while (true) {
            $filteredSites = $this->filterSites($sites);

            if ($filteredSites->isEmpty()) {
                $this->info('No matching sites found.');
                $this->keyword = $this->ask('Enter a search term');
                continue;
            }

            $this->info("Matching sites:");

            if ($this->displayFormat() === 'table') {
                $this->format($filteredSites);
            } else {
                $filteredSites->each(function ($site, $key) {
                    $this->line(sprintf("%d: %s (%s)", $key + 1, $site['Domain'], $site['Site User']));
                });
            }

            if ($filteredSites->count() > 1) {
                $selection = $this->ask('Enter the number of the site to view');

                if ($selection === '') {
                    $this->keyword = null;
                    continue;
                }

                if (is_numeric($selection) && $selection > 0 && $selection <= $filteredSites->count()) {
                    $selectedSite = $filteredSites[$selection - 1];

                    $this->handleServerSelection($selectedSite);

                    break;
                }
            }

            if ($filteredSites->count() === 1) {
                $selectedSite = $filteredSites[0];

                $this->handleServerSelection($selectedSite);

                break;
            }

            $this->error('Invalid selection. Please try again.');
        }
    }

    private function filterSites(Collection $sites): Collection
    {
        return $sites->filter(function ($site) {
            return Str::contains($site['Domain'], $this->keyword, true) ||
                Str::contains($site['Site User'], $this->keyword, true) ||
                Str::contains($site['Site ID'], $this->keyword, true);
        })->values()->map(function ($site, $index) {
            return array_merge(['Match' => $index + 1], $site);
        });
    }

    private function handleServerSelection($selectedSite): void
    {
        if ($this->confirm(sprintf("Do you want to SSH into: %s (%s)",
            $selectedSite['Domain'],
            $selectedSite['Site User']
        ))) {
            $this->call('site:ssh', ['site_id' => $selectedSite['Site ID']]);
        }
    }

}
