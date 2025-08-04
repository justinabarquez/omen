<?php

namespace Omen;

use Illuminate\Support\ServiceProvider;
use Omen\Console\InstallCommand;
use Omen\Console\ChatCommand;

class OmenServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ChatCommand::class,
            ]);
        }
    }
}