<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class MassiveStressTestSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_ES');
        $now = now();

        $this->command->info('Empezando generacion masiva...');

        // 1. Entities (Clientes y Proveedores)
        $this->command->info('Generando 10,000 Entidades...');
        $entities = [];
        for ($i = 1; $i <= 10000; $i++) {
            $entities[] = [
                'type' => $i <= 8000 ? 'cliente' : 'proveedor',
                'name' => $faker->company . ' ' . $faker->companySuffix,
                'legal_name' => $faker->company,
                'rfc_ruc' => strtoupper(Str::random(12)),
                'country' => 'Mexico',
                'city' => $faker->city,
                'address' => $faker->address,
                'phone_mobile' => $faker->phoneNumber,
                'email' => uniqid('test_') . '@example.com',
                'status' => 'activo',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($entities) >= 2000) {
                DB::table('entities')->insert($entities);
                $entities = [];
                $this->command->info("Insertados 2000 entities...");
            }
        }
        if (count($entities) > 0) DB::table('entities')->insert($entities);
        $clientIds = DB::table('entities')->where('type', 'cliente')->pluck('id')->toArray();
        $this->command->info('Entidades generadas.');

        // 2. Products
        $this->command->info('Generando 20,000 Productos...');
        $products = [];
        for ($i = 1; $i <= 20000; $i++) {
            $products[] = [
                'type' => 'insumo',
                'name' => 'Producto Masivo ' . $i . ' ' . Str::random(5),
                'part_number' => strtoupper(Str::random(8)),
                'sku' => 'SKU-MST-' . uniqid() . '-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'brand' => $faker->randomElement(['Caterpillar', 'Komatsu', 'Volvo', 'John Deere', 'Bosch']),
                'sales_price' => $faker->randomFloat(2, 20, 10000),
                'cost' => $faker->randomFloat(2, 10, 5000),
                'total_cost' => $faker->randomFloat(2, 10, 5000),
                'stock_on_hand' => $faker->numberBetween(0, 1000),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($products) >= 2000) {
                DB::table('products')->insert($products);
                $products = [];
                $this->command->info("Insertados 2000 productos...");
            }
        }
        if (count($products) > 0) DB::table('products')->insert($products);
        $this->command->info('Productos generados.');

        // 3. Client Machines
        $this->command->info('Generando 25,000 Maquinas de Clientes...');
        $machines = [];
        for ($i = 1; $i <= 25000; $i++) {
            $machines[] = [
                'entity_id' => $faker->randomElement($clientIds),
                'name' => 'Maquina ' . $faker->word,
                'machine_type' => $faker->randomElement(['Excavadora', 'Cargador', 'Tractor', 'Grua', 'Generador']),
                'brand' => $faker->randomElement(['Caterpillar', 'Komatsu', 'Volvo']),
                'model' => strtoupper(Str::random(4)) . '-' . rand(100, 999),
                'serial_number' => strtoupper(Str::random(10)),
                'status' => $faker->randomElement(['activo', 'inactivo', 'en_reparacion']),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($machines) >= 2000) {
                DB::table('client_machines')->insert($machines);
                $machines = [];
                $this->command->info("Insertadas 2000 maquinas...");
            }
        }
        if (count($machines) > 0) DB::table('client_machines')->insert($machines);
        $this->command->info('Maquinas generadas.');

        // 4. Tickets
        $this->command->info('Generando 5,000 Tickets...');
        $tickets = [];
        for ($i = 1; $i <= 5000; $i++) {
            $tickets[] = [
                'code' => 'TKM-' . str_pad($i, 5, '0', STR_PAD_LEFT) . Str::random(2),
                'customer_id' => 1, // User ID
                'subject' => 'Falla masiva ' . $i,
                'description' => 'Descripcion detallada de la falla generada masivamente',
                'priority' => $faker->randomElement(['baja', 'media', 'alta', 'urgente']),
                'status' => $faker->randomElement(['abierto', 'en_proceso', 'resuelto', 'cerrado']),
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(1, 30)),
                'type' => 'soporte_tecnico',
            ];

            if (count($tickets) >= 2000) {
                DB::table('tickets')->insert($tickets);
                $tickets = [];
                $this->command->info("Insertados 2000 tickets...");
            }
        }
        if (count($tickets) > 0) DB::table('tickets')->insert($tickets);
        $this->command->info('Tickets generados.');

        $this->command->info('INSERCION MASIVA COMPLETADA CON EXITO.');
    }
}
