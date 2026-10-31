<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeInspectBankCurrencies extends Command
{
    protected $signature = 'normalization:inspect-bank-currencies';
    protected $description = 'Reporta todas las cuentas bancarias que tienen moneda MXN por defecto (Fase 8)';

    public function handle()
    {
        $this->info('Inspeccionando cuentas bancarias con moneda MXN...');
        
        if (!DB::getSchemaBuilder()->hasTable('entity_bank_accounts')) {
            $this->error('No existe la tabla entity_bank_accounts.');
            return;
        }

        $accounts = DB::table('entity_bank_accounts')
            ->where('currency', 'MXN')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('✅ Ninguna cuenta bancaria tiene moneda MXN.');
            return;
        }

        $this->warn("⚠️ Encontradas {$accounts->count()} cuentas bancarias con moneda MXN.");
        
        $headers = ['ID', 'Entity ID', 'Bank Name', 'Account Number', 'Currency'];
        $data = $accounts->map(function ($account) {
            return [
                $account->id,
                $account->entity_id,
                $account->bank_name,
                $account->account_number,
                $account->currency
            ];
        })->toArray();

        $this->table($headers, $data);
        $this->info('Por favor, rectifique manualmente si estas cuentas operan en Pesos Mexicanos o si fue un error del default anterior.');
    }
}
