<?php

namespace Database\Seeders;

use App\Models\ClientMachine;
use App\Models\CustomerMachineComponent;
use App\Models\Entity;
use App\Models\EntityContact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerProfilesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment('local')) {
            throw new RuntimeException('Este seeder de perfiles solo puede ejecutarse en APP_ENV=local.');
        }

        $clients = Entity::query()->whereIn('type', ['cliente', 'customer'])->orderBy('id')->get();

        foreach ($clients as $index => $client) {
            DB::transaction(fn () => $this->completeProfile($client, $index + 1));
        }

        $this->command?->info("Perfiles completados: {$clients->count()} clientes, contactos, maquinaria y componentes.");
    }

    private function completeProfile(Entity $client, int $number): void
    {
        $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $client->update([
            'legal_name' => $client->legal_name ?: "{$client->name}, S.A.",
            'rfc_ruc' => $client->rfc_ruc ?: 'J031'.str_pad((string) $client->id, 10, '0', STR_PAD_LEFT),
            'email' => $client->email ?: "administracion{$suffix}@cliente.demo.local",
            'phone_home' => $client->phone_home ?: '+505 2255 '.str_pad((string) (1000 + $number), 4, '0', STR_PAD_LEFT),
            'phone_mobile' => $client->phone_mobile ?: '+505 8888 '.str_pad((string) (1000 + $number), 4, '0', STR_PAD_LEFT),
            'website' => $client->website ?: 'https://cliente-'.$suffix.'.demo.local',
            'country' => $client->country ?: 'Nicaragua',
            'department' => $client->department ?: ['Managua', 'Masaya', 'Chinandega', 'Matagalpa'][$number % 4],
            'municipality' => $client->municipality ?: ['Managua', 'Masaya', 'El Viejo', 'Matagalpa'][$number % 4],
            'exact_address' => $client->exact_address ?: "Zona industrial, edificio {$suffix}, referencia operativa registrada.",
            'address' => $client->address ?: "Zona industrial, edificio {$suffix}, referencia operativa registrada.",
            'machinery' => 'Excavadoras, cargadores frontales y generadores eléctricos',
            'part_types' => 'Filtros, sellos, rodamientos, sensores y componentes hidráulicos',
            'status' => 'activo',
        ]);

        $contacts = [
            ['name' => "Ana Martínez {$suffix}", 'position' => 'Gerente de compras', 'department' => 'Compras', 'is_primary' => true],
            ['name' => "Carlos López {$suffix}", 'position' => 'Jefe de mantenimiento', 'department' => 'Mantenimiento', 'is_primary' => false],
            ['name' => "María González {$suffix}", 'position' => 'Responsable administrativa', 'department' => 'Administración', 'is_primary' => false],
        ];

        foreach ($contacts as $contactIndex => $contact) {
            EntityContact::updateOrCreate(
                ['entity_id' => $client->id, 'name' => $contact['name']],
                $contact + [
                    'email' => ['compras', 'mantenimiento', 'administracion'][$contactIndex]."{$suffix}@cliente.demo.local",
                    'mobile' => '+505 87'.str_pad((string) ($number * 10 + $contactIndex), 6, '0', STR_PAD_LEFT),
                    'office_phone' => $client->phone_home,
                    'whatsapp' => '+505 87'.str_pad((string) ($number * 10 + $contactIndex), 6, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'notes' => ['Autoriza cotizaciones y órdenes.', 'Coordina especificaciones técnicas y entregas.', 'Gestiona facturas y estados de cuenta.'][$contactIndex],
                ],
            );
        }

        $client->update(['contact_name' => $contacts[0]['name'], 'contact_position' => $contacts[0]['position']]);

        $machineDefinitions = [
            ['type' => 'Excavadora hidráulica', 'brand' => 'Caterpillar', 'model' => '320D', 'life' => 5000],
            ['type' => 'Cargador frontal', 'brand' => 'Komatsu', 'model' => 'WA200', 'life' => 4500],
            ['type' => 'Generador eléctrico', 'brand' => 'Cummins', 'model' => 'C150D5', 'life' => 3000],
        ];

        foreach ($machineDefinitions as $machineIndex => $definition) {
            $hourmeter = 1200 + ($number * 73) + ($machineIndex * 410);
            $machine = ClientMachine::updateOrCreate(
                ['entity_id' => $client->id, 'internal_code' => "EQ-{$suffix}-".($machineIndex + 1)],
                [
                    'machine_type' => $definition['type'],
                    'name' => "{$definition['brand']} {$definition['model']}",
                    'brand' => $definition['brand'],
                    'model' => $definition['model'],
                    'serial_number' => "SER-{$suffix}-".str_pad((string) ($machineIndex + 1), 3, '0', STR_PAD_LEFT),
                    'engine_serial' => "MOT-{$suffix}-".str_pad((string) ($machineIndex + 1), 3, '0', STR_PAD_LEFT),
                    'year' => 2018 + (($number + $machineIndex) % 7),
                    'hourmeter' => $hourmeter,
                    'hourmeter_date' => now()->subDays($machineIndex)->toDateString(),
                    'status' => $machineIndex === 1 && $number % 4 === 0 ? 'en_mantenimiento' : 'activa',
                    'technical_details' => 'Equipo incluido en el programa de mantenimiento preventivo.',
                    'notes' => 'Ficha técnica y números de serie verificados para demostración local.',
                ],
            );

            foreach (['Filtro de aceite', 'Filtro hidráulico', 'Bomba de agua', 'Kit de sellos'] as $componentIndex => $componentName) {
                $estimatedLife = [500, 1000, 2500, 1800][$componentIndex];
                $currentUsage = min($estimatedLife, 180 + ($number * 19) + ($componentIndex * 170));
                $percentage = round(($currentUsage / $estimatedLife) * 100, 2);
                $status = $percentage >= 100 ? 'vencido' : ($percentage >= 90 ? 'critico' : ($percentage >= 70 ? 'atencion_proxima' : 'bueno'));

                CustomerMachineComponent::updateOrCreate(
                    ['client_machine_id' => $machine->id, 'part_number' => "CMP-{$suffix}-".($machineIndex + 1).'-'.($componentIndex + 1)],
                    [
                        'component_name' => $componentName,
                        'component_brand' => ['Donaldson', 'Fleetguard', 'Gates', 'NOK'][$componentIndex],
                        'component_model' => 'Serie industrial',
                        'quantity' => 1,
                        'counter_type' => 'horas',
                        'last_replacement_hourmeter' => max(0, $hourmeter - $currentUsage),
                        'current_usage' => $currentUsage,
                        'estimated_life' => $estimatedLife,
                        'residual_life' => max(0, $estimatedLife - $currentUsage),
                        'consumed_percentage' => $percentage,
                        'last_installation_date' => now()->subDays(120 + $componentIndex * 30)->toDateString(),
                        'last_updated_at' => now(),
                        'status' => $status,
                        'notes' => 'Componente registrado para seguimiento preventivo.',
                    ],
                );
            }
        }
    }
}
