<?php

namespace DummyNamespace;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Jdion84\Lucid\Table;

class DummyClass extends Pivot
{
    public function schema(Table $table)
    {
        $table->id();
        $table->integer('first_id')->index();
        $table->integer('second_id')->index();
        $table->timestamp('created_at');
        $table->timestamp('updated_at');
    }
}
