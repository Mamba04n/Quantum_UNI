<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use LogicException;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (
            $app->environment('testing')
            && $database !== ':memory:'
            && ! str_ends_with($database, '_testing')
        ) {
            throw new LogicException(
                "La suite se nego a usar la base [{$database}]. "
                .'Configure una base exclusiva cuyo nombre termine en _testing.'
            );
        }

        return $app;
    }
}
