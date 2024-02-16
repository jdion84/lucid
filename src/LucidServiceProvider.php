<?php

namespace Jdion84\Lucid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Jdion84\Lucid\Console\MakeSchemaCommand;
use Jdion84\Lucid\Console\MigrateSchemasCommand;

class LucidServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSchemaCommand::class,
                MigrateSchemasCommand::class,
            ]);
        }

        Model::unguard();
    }
}
