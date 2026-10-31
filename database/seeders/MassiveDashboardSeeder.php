<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class MassiveDashboardSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_ES');
        $now = now();

        $this->command->info('Empezando generacion financiera para el Dashboard...');

        $clientIds = DB::table('entities')->where('type', 'cliente')->pluck('id')->toArray();
        $supplierIds = DB::table('entities')->where('type', 'proveedor')->pluck('id')->toArray();
        $machineIds = DB::table('client_machines')->pluck('id')->toArray();

        if (empty($clientIds) || empty($supplierIds) || empty($machineIds)) {
            $this->command->error('No hay suficientes entidades o maquinas. Ejecuta MassiveStressTestSeeder primero.');
            return;
        }

        // 1. Sale Orders
        $this->command->info('Generando 1,000 Sale Orders...');
        $orders = [];
        for ($i = 1; $i <= 1000; $i++) {
            $orders[] = [
                'reference' => 'ORD-M-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'customer_id' => $faker->randomElement($clientIds),
                'status' => $faker->randomElement(['pendiente_compra', 'en_transito_aduanas', 'entregado', 'solicitado_proveedor']),
                'source_type' => 'manual',
                'subtotal' => $faker->randomFloat(2, 500, 50000),
                'tax_percent' => 15.00,
                'tax_amount' => 100.00,
                'total_amount' => $faker->randomFloat(2, 500, 50000),
                'date' => now()->subDays(rand(1, 15))->format('Y-m-d'),
                'payment_type' => 'Contado',
                'client_name' => 'Cliente Masivo',
                'currency' => 'USD',
                'created_at' => now()->subDays(rand(1, 15)),
                'updated_at' => now()->subDays(rand(1, 15)),
            ];

            if (count($orders) >= 1000) {
                DB::table('sale_orders')->insert($orders);
                $orders = [];
                $this->command->info("Insertadas ordenes...");
            }
        }
        if (count($orders) > 0) DB::table('sale_orders')->insert($orders);
        $this->command->info('Ordenes generadas.');

        // 2. Account Receivables (Cuentas por Cobrar)
        $this->command->info('Generando 2,000 Cuentas por Cobrar...');
        $receivables = [];
        for ($i = 1; $i <= 2000; $i++) {
            $total = $faker->randomFloat(2, 1000, 20000);
            $paid = $faker->randomElement([0, $total * 0.5, $total]);
            $status = $paid == $total ? 'pagado' : ($paid > 0 ? 'parcial' : 'pendiente');
            
            $receivables[] = [
                'receivable_code' => 'CXC-M-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'entity_id' => $faker->randomElement($clientIds),
                'concept' => 'Factura masiva ' . $i,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'due_date' => now()->addDays(rand(-30, 30)),
                'status' => $status,
                'created_at' => now()->subDays(rand(1, 15)),
                'updated_at' => now()->subDays(rand(1, 15)),
            ];

            if (count($receivables) >= 1000) {
                DB::table('account_receivables')->insert($receivables);
                $receivables = [];
                $this->command->info("Insertadas CXC...");
            }
        }
        if (count($receivables) > 0) DB::table('account_receivables')->insert($receivables);
        $this->command->info('Cuentas por cobrar generadas.');

        // 3. Account Payables (Cuentas por Pagar)
        $this->command->info('Generando 2,000 Cuentas por Pagar...');
        $payables = [];
        for ($i = 1; $i <= 2000; $i++) {
            $total = $faker->randomFloat(2, 500, 15000);
            $paid = $faker->randomElement([0, $total * 0.5, $total]);
            $status = $paid == $total ? 'pagado' : ($paid > 0 ? 'parcial' : 'pendiente');
            
            $payables[] = [
                'payable_code' => 'CXP-M-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'entity_id' => $faker->randomElement($supplierIds),
                'concept' => 'Compra a proveedor masiva ' . $i,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'due_date' => now()->addDays(rand(-30, 30)),
                'status' => $status,
                'created_at' => now()->subDays(rand(1, 15)),
                'updated_at' => now()->subDays(rand(1, 15)),
            ];

            if (count($payables) >= 1000) {
                DB::table('account_payables')->insert($payables);
                $payables = [];
                $this->command->info("Insertadas CXP...");
            }
        }
        if (count($payables) > 0) DB::table('account_payables')->insert($payables);
        $this->command->info('Cuentas por pagar generadas.');

        // 4. Customer Machine Components (Mantenimientos)
        $this->command->info('Generando 5,000 Componentes para mantenimientos...');
        $components = [];
        for ($i = 1; $i <= 5000; $i++) {
            $components[] = [
                'client_machine_id' => $faker->randomElement($machineIds),
                'component_name' => 'Filtro de Aceite M-' . $i,
                'part_number' => strtoupper(Str::random(8)),
                'quantity' => 1,
                'counter_type' => 'horas',
                'current_usage' => $faker->numberBetween(500, 5000),
                'estimated_life' => 5000,
                'residual_life' => $faker->numberBetween(-100, 2000),
                'consumed_percentage' => $faker->randomFloat(2, 50, 110),
                'status' => $faker->randomElement(['normal', 'atencion_proxima', 'critico', 'vencido']),
                'created_at' => now()->subDays(rand(1, 15)),
                'updated_at' => now()->subDays(rand(1, 15)),
            ];

            if (count($components) >= 1000) {
                DB::table('customer_machine_components')->insert($components);
                $components = [];
                $this->command->info("Insertados componentes...");
            }
        }
        if (count($components) > 0) DB::table('customer_machine_components')->insert($components);
        $this->command->info('Componentes generados.');

        $this->command->info('INSERCION FINANCIERA COMPLETADA CON EXITO.');
    }
}
