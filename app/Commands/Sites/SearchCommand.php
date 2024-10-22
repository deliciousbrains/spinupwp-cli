<?php

namespace App\Commands\Sites;

use App\Commands\Sites\Sites;
use Illuminate\Support\Str;

class SearchCommand extends Sites
{
    public string|null $keyword;

    protected $signature = 'sites:search
                            {keyword? : Your search term}
                            {server_id? : Only list sites belonging to this server}
                            {--fields= : The fields to output}
                            {--format= : The output format (json or table)}
                            {--profile= : The SpinupWP configuration profile to use}';

    protected $description = 'Retrieves a list of sites';

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

        if ($this->displayFormat() !== 'table') {
            $this->info("Format not supported");
            return self::FAILURE;
        }

        $this->promptForSearch($sites);

        return self::SUCCESS;
    }

    protected function promptForSearch($sites)
    {
        while (true) {
            if (!$this->keyword) {
                $this->keyword = $this->ask('Enter a search term');
            }

            $filteredSites = $sites->filter(function ($site) {
                return Str::contains($site['Domain'], $this->keyword, true) || Str::contains($site['Site User'], $this->keyword, true);
            })->values();

            if ($filteredSites->isEmpty()) {
                $this->info('No matching sites found.');
                $this->keyword = $this->ask('Enter a search term');
                continue;
            }

            $this->info("Matching sites:");

            $filteredSites->each(function ($site, $key) {
                $this->line(sprintf("%d: %s (%s)", $key + 1, $site['Domain'], $site['Site User']));
            });

            if ($filteredSites->count() > 1) {
                $selection = $this->ask('Enter the number of the site to view');

                if ($selection === '') {
                    $this->keyword = null;
                    continue;
                }

                if (is_numeric($selection) && $selection > 0 && $selection <= $filteredSites->count()) {
                    $selectedSite = $filteredSites[$selection - 1];

                    $this->format(collect([$selectedSite]));

                    if ($this->confirm('Do you want to SSH into this site?')) {
                        $this->call('site:ssh', ['site_id' => $selectedSite['ID']]);
                    }

                    break;
                }
            }

            if ($filteredSites->count() === 1) {
                $selectedSite = $filteredSites[0];
                $this->format(collect([$selectedSite]));

                if ($this->confirm('Do you want to SSH into this site?')) {
                    $this->call('sites:ssh', ['site_id' => $selectedSite['ID']]);
                }

                break;
            }


            $this->error('Invalid selection. Please try again.');
        }
    }
}
