<?php

namespace Omen;

use Illuminate\Support\ServiceProvider;
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
                ChatCommand::class,
            ]);
        }
    }
}