<?php

namespace Totocsa\UpdatePackagesJsCommand;

use Illuminate\Support\ServiceProvider;

class UpdatePackagesJsCommandServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Ha van konfigurációs fájl, azt itt töltheted be
        //$this->mergeConfigFrom(__DIR__.'/../config/destroy-confirm-modal.php', 'destroy-confirm-modal');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Totocsa\UpdatePackagesJsCommand\Console\Commands\UpdatePackagesJs::class,
            ]);
        }
    }
}
