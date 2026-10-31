<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallAcademic extends Command
{
    protected $signature = 'quantum-uni:install';

    protected $description = 'Prepara exclusivamente la base académica local quantum_uni, sin eliminar tablas.';

    public function handle(): int
    {
        if (! app()->environment('local') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'quantum_uni'
            || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)) {
            $this->error('Requiere entorno local, MySQL local y base quantum_uni.');

            return self::FAILURE;
        }
        config(['database.connections.mysql.database' => null]);
        DB::purge('mysql');
        try {
            DB::connection('mysql')->statement('CREATE DATABASE IF NOT EXISTS `quantum_uni` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } finally {
            DB::purge('mysql');
            config(['database.connections.mysql.database' => 'quantum_uni']);
        }

        return $this->call('migrate', ['--seed' => true]);
    }
}
