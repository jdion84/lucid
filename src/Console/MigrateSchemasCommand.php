<?php

namespace Jdion84\Lucid\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;
use Jdion84\Lucid\Table;
use Symfony\Component\Finder\Finder;

class MigrateSchemasCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'migrate:schemas {--f|fresh} {--s|seed} {--force}';

    protected $description = 'Migrate & sync model schema methods with the database';

    public function handle()
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->migrate();

        $this->syncModelSchemaMethods();

        if ($this->option('seed')) {
            $this->seed();
        }

        return 0;
    }

    protected function migrate()
    {
        $command = $this->option('fresh')
            ? 'migrate:fresh'
            : 'migrate';

        $this->call($command, [
            '--force' => true,
        ]);
    }

    protected function syncModelSchemaMethods()
    {
        $this->components->info('Syncing model schema methods.');

        $path = app_path('Models');

        $namespace = app()->getNamespace();

        foreach ((new Finder)->in($path)->files() as $file) {
            $model = $namespace . str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($file->getRealPath(), realpath(app_path()) . DIRECTORY_SEPARATOR)
            );

            if (method_exists($model, 'schema')) {
                $this->syncModelSchemaMethod(app($model));
            }
        }

        $this->newLine();
    }

    protected function syncModelSchemaMethod(Model|Pivot $model)
    {
        $builder = $model->getConnection()->getSchemaBuilder();

        $temporaryTable = $this->createTemporaryTable($model, $builder);

        if (!$builder->hasTable($model->getTable())) {
            $this->createTable($model, $builder, $temporaryTable);
        } else {
            $this->updateTable($model, $builder, $temporaryTable);
        }
    }

    protected function createTemporaryTable(Model|Pivot $model, Builder $builder)
    {
        $temporaryTable = "{$model->getTable()}_table";

        $builder->dropIfExists($temporaryTable);

        $builder->create($temporaryTable, function (Blueprint $table) use ($model) {
            $model->schema(new Table($table));
        });

        return $temporaryTable;
    }

    protected function createTable(Model|Pivot $model, Builder $builder, $temporaryTable)
    {
        $this->components->task(
            "Creating {$model->getTable()} table",
            function () use ($builder, $temporaryTable, $model) {
                $builder->rename($temporaryTable, $model->getTable());
            }
        );
    }

    protected function updateTable(Model|Pivot $model, Builder $builder, $temporaryTable)
    {
        $manager = $model->getConnection()->getDoctrineSchemaManager();

        $tableDifference = $manager->createComparator()->compareTables(
            $manager->introspectTable($model->getTable()),
            $manager->introspectTable($temporaryTable),
        );

        if (!$tableDifference->isEmpty()) {
            $this->components->task(
                "Updating {$model->getTable()} table",
                function () use ($manager, $tableDifference) {
                    $manager->alterTable($tableDifference);
                }
            );
        }

        $builder->drop($temporaryTable);
    }

    protected function seed()
    {
        $this->call('db:seed', [
            '--force' => true,
        ]);
    }
}
