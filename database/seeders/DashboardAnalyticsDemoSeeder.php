<?php

namespace Database\Seeders;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ClientMachine;
use App\Models\CustomerMachineComponent;
use App\Models\Entity;
use App\Models\Payment;
use App\Models\SaleOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $clients = $this->clients();
            $suppliers = $this->suppliers();
            $statuses = ['cotizacion', 'pending_purchase', 'solicitado_proveedor', 'en_transito_aduanas', 'en_inventario_local', 'entregado'];
            $orders = collect();

            for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
                $month = now()->startOfMonth()->subMonths($monthOffset);

                for ($sequence = 1; $sequence <= 7; $sequence++) {
                    $serial = (($monthOffset * 7) + $sequence);
                    $client = $clients[$serial % $clients->count()];
                    $status = $statuses[($serial + $monthOffset) % count($statuses)];
                    $subtotal = 42000 + (($serial * 7913) % 185000);
                    $tax = round($subtotal * 0.15, 2);
                    $date = $month->copy()->day(min(25, 3 + ($sequence * 3)));

                    $order = SaleOrder::withoutEvents(fn () => SaleOrder::updateOrCreate(
                        ['reference' => 'DASH-OV-'.$month->format('Ym').'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT)],
                        [
                            'date' => $date,
                            'customer_id' => $client->id,
                            'client_name' => $client->legal_name ?: $client->name,
                            'client_email' => $client->email,
                            'client_ruc' => $client->rfc_ruc,
                            'shipping_address' => $client->address ?: $client->exact_address,
                            'payment_type' => $sequence % 3 === 0 ? 'Crédito' : 'Contado',
                            'status' => $status,
                            'subtotal' => $subtotal,
                            'tax_percent' => 15,
                            'tax_amount' => $tax,
                            'total_amount' => $subtotal + $tax,
                            'currency' => 'NIO',
                            'notes' => 'Registro demostrativo para análisis del dashboard.',
                        ]
                    ));
                    $orders->push($order);
                }
            }

            $this->receivablesAndCollections($orders, $clients);
            $this->payables($suppliers);
            $this->maintenance();
        });
    }

    private function clients()
    {
        $clients = Entity::query()->where('type', 'cliente')->orderBy('id')->limit(10)->get();
        if ($clients->isNotEmpty()) {
            return $clients;
        }

        return collect(range(1, 5))->map(fn (int $number) => Entity::create([
            'name' => "Cliente Dashboard {$number}",
            'legal_name' => "Cliente Dashboard {$number}, S.A.",
            'type' => 'cliente',
            'email' => "dashboard-cliente-{$number}@demo.local",
            'rfc_ruc' => 'DASH-CLI-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'status' => 'activo',
        ]));
    }

    private function suppliers()
    {
        $suppliers = Entity::query()->where('type', 'proveedor')->orderBy('id')->limit(8)->get();
        if ($suppliers->isNotEmpty()) {
            return $suppliers;
        }

        return collect(range(1, 4))->map(fn (int $number) => Entity::create([
            'name' => "Proveedor Dashboard {$number}",
            'legal_name' => "Proveedor Dashboard {$number}, S.A.",
            'type' => 'proveedor',
            'email' => "dashboard-proveedor-{$number}@demo.local",
            'rfc_ruc' => 'DASH-PRV-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'status' => 'activo',
        ]));
    }

    private function receivablesAndCollections($orders, $clients): void
    {
        $dueOffsets = [-24, 3, 6, 11, 14, 19, 27, 38, 52];

        foreach ($orders->values() as $index => $order) {
            $client = $clients[$index % $clients->count()];
            $total = (float) $order->total_amount;
            $isOpen = $index >= $orders->count() - 27;
            $paid = $isOpen ? ($index % 3 === 0 ? round($total * 0.35, 2) : 0) : $total;
            $status = $paid >= $total ? 'pagado' : ($paid > 0 ? 'parcial' : 'pendiente');
            $dueDate = $isOpen
                ? now()->startOfDay()->addDays($dueOffsets[$index % count($dueOffsets)])
                : $order->date->copy()->addDays(30);

            $receivable = AccountReceivable::updateOrCreate(
                ['receivable_code' => 'DASH-CXC-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)],
                [
                    'entity_id' => $client->id,
                    'customer_id' => $client->id,
                    'sales_order_id' => $order->id,
                    'concept' => 'Factura demostrativa '.$order->reference,
                    'invoice_number' => 'DASH-FAC-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                    'invoice_date' => $order->date,
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'currency' => 'NIO',
                    'collected_at' => $status === 'pagado' ? $order->date->copy()->addDays(18) : null,
                ]
            );

            if ($paid > 0) {
                Payment::updateOrCreate(
                    ['receipt_reference' => 'DASH-REC-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT)],
                    [
                        'payable_id' => $receivable->id,
                        'payable_type' => AccountReceivable::class,
                        'accounts_receivable_id' => $receivable->id,
                        'amount' => $paid,
                        'payment_date' => $order->date->copy()->addDays(18),
                        'payment_method' => $index % 2 === 0 ? 'Transferencia' : 'Cheque',
                        'payment_code' => 'DASH-COB-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                        'payment_type' => 'collection',
                        'currency' => 'NIO',
                    ]
                );
            }
        }
    }

    private function payables($suppliers): void
    {
        $dueOffsets = [-18, 2, 5, 9, 13, 20, 29, 41, 58];

        foreach (range(1, 36) as $number) {
            $supplier = $suppliers[$number % $suppliers->count()];
            $total = 18000 + (($number * 6137) % 95000);
            $isOpen = $number > 18;
            $paid = $isOpen ? ($number % 4 === 0 ? round($total * 0.25, 2) : 0) : $total;
            $status = $paid >= $total ? 'pagado' : ($paid > 0 ? 'parcial' : 'pendiente');

            $payable = AccountPayable::updateOrCreate(
                ['payable_code' => 'DASH-CXP-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT)],
                [
                    'entity_id' => $supplier->id,
                    'supplier_id' => $supplier->id,
                    'concept' => "Compra demostrativa de inventario {$number}",
                    'invoice_number' => 'DASH-PRV-FAC-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                    'invoice_date' => now()->subMonths($number % 12)->startOfMonth()->addDays(5),
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'due_date' => $isOpen ? now()->startOfDay()->addDays($dueOffsets[$number % count($dueOffsets)]) : now()->subMonths($number % 12)->addDays(20),
                    'status' => $status,
                    'currency' => 'NIO',
                    'paid_at' => $status === 'pagado' ? now()->subMonths($number % 12)->addDays(18) : null,
                ]
            );

            if ($paid > 0) {
                Payment::updateOrCreate(
                    ['receipt_reference' => 'DASH-PAY-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT)],
                    [
                        'payable_id' => $payable->id,
                        'payable_type' => AccountPayable::class,
                        'accounts_payable_id' => $payable->id,
                        'amount' => $paid,
                        'payment_date' => now()->subMonths($number % 12)->addDays(18),
                        'payment_method' => 'Transferencia',
                        'payment_code' => 'DASH-PAG-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                        'payment_type' => 'payment',
                        'currency' => 'NIO',
                    ]
                );
            }
        }
    }

    private function maintenance(): void
    {
        $machines = ClientMachine::query()->orderBy('id')->limit(12)->get();
        $statuses = ['bueno', 'bueno', 'atencion_proxima', 'critico', 'vencido'];

        foreach ($machines as $machineIndex => $machine) {
            foreach (range(1, 3) as $componentIndex) {
                $status = $statuses[($machineIndex + $componentIndex) % count($statuses)];
                $percentage = match ($status) {
                    'vencido' => 108,
                    'critico' => 96,
                    'atencion_proxima' => 82,
                    default => 48,
                };

                CustomerMachineComponent::updateOrCreate(
                    ['client_machine_id' => $machine->id, 'part_number' => 'DASH-CMP-'.$machine->id.'-'.$componentIndex],
                    [
                        'component_name' => ['Filtro hidráulico', 'Kit de sellos', 'Rodamiento principal'][$componentIndex - 1],
                        'quantity' => 1,
                        'counter_type' => 'horas',
                        'current_usage' => $percentage * 10,
                        'estimated_life' => 1000,
                        'residual_life' => 1000 - ($percentage * 10),
                        'consumed_percentage' => $percentage,
                        'status' => $status,
                        'last_updated_at' => now(),
                        'notes' => 'Componente demostrativo para el dashboard.',
                    ]
                );
            }
        }
    }
}
