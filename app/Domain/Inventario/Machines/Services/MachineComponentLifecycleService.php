<?php

namespace App\Domain\Inventario\Machines\Services;

use App\Models\ActivityLog;
use App\Models\ClientMachine;
use App\Models\ComponentUsageLog;
use App\Models\CustomerMachineComponent;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use Illuminate\Support\Facades\DB;

class MachineComponentLifecycleService
{
    public function metrics(int $currentUsage, int $estimatedLife): array
    {
        $estimatedLife = max(1, $estimatedLife);
        $currentUsage = max(0, $currentUsage);
        $residual = $estimatedLife - $currentUsage;
        $percentage = round(($currentUsage / $estimatedLife) * 100, 2);

        $status = match (true) {
            $currentUsage >= $estimatedLife => CustomerMachineComponent::STATUS_EXPIRED,
            $percentage >= 90 => CustomerMachineComponent::STATUS_CRITICAL,
            $percentage >= 70 => CustomerMachineComponent::STATUS_ATTENTION,
            default => CustomerMachineComponent::STATUS_GOOD,
        };

        return [
            'residual_life' => $residual,
            'consumed_percentage' => min(999.99, $percentage),
            'status' => $status,
        ];
    }

    public function create(array $data): CustomerMachineComponent
    {
        return DB::transaction(function () use ($data) {
            $data = $this->normalizeHourBasedUsage($data);
            $metrics = $this->metrics((int) $data['current_usage'], (int) $data['estimated_life']);

            $component = CustomerMachineComponent::create(array_merge($data, $metrics, [
                'last_updated_at' => now(),
            ]));

            $this->logUsage($component, 0, (int) $component->current_usage, null, $component->status, 'Registro inicial del componente.');

            ActivityLog::log('crm', 'machine_component_added', "Componente {$component->component_name} agregado a maquinaria", [
                'component_id' => $component->id,
                'client_machine_id' => $component->client_machine_id,
                'status' => $component->status,
            ]);

            return $component;
        });
    }

    public function updateUsage(CustomerMachineComponent $component, int $newUsage, ?string $notes = null): CustomerMachineComponent
    {
        return DB::transaction(function () use ($component, $newUsage, $notes) {
            $newUsage = $this->usageFromReading($component, $newUsage);
            $previousUsage = (int) $component->current_usage;
            $previousResidual = (int) $component->residual_life;
            $previousStatus = $component->status;
            $metrics = $this->metrics($newUsage, (int) $component->estimated_life);

            $component->update(array_merge($metrics, [
                'current_usage' => max(0, $newUsage),
                'last_updated_at' => now(),
                'notes' => $notes ?: $component->notes,
            ]));

            $this->logUsage($component, $previousUsage, (int) $component->current_usage, $previousStatus, $component->status, $notes);

            ActivityLog::log('crm', 'component_usage_updated', "Uso actualizado para componente {$component->component_name}", [
                'component_id' => $component->id,
                'previous_usage' => $previousUsage,
                'new_usage' => $component->current_usage,
                'previous_status' => $previousStatus,
                'new_status' => $component->status,
            ]);

            if ($previousStatus !== $component->status && in_array($component->status, [
                CustomerMachineComponent::STATUS_ATTENTION,
                CustomerMachineComponent::STATUS_CRITICAL,
                CustomerMachineComponent::STATUS_EXPIRED,
            ], true)) {
                ActivityLog::log('crm', 'component_status_alert', "Componente {$component->component_name} cambio a {$component->status}", [
                    'component_id' => $component->id,
                    'status' => $component->status,
                ]);
            }

            return $component->fresh(['machine.entity', 'usageLogs.updater']);
        });
    }

    public function generateQuotation(CustomerMachineComponent $component)
    {
        return DB::transaction(function () use ($component) {
            $component->loadMissing('machine.entity');
            $client = $component->machine->entity;
            $next = QuotationRequest::count() + 1;
            $reference = 'SC'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);

            $quote = QuotationRequest::create([
                'reference' => $reference,
                'date' => now()->toDateString(),
                'quote_to_name' => $client->name,
                'quote_to_email' => $client->email ?: null,
                'consignee_name' => $client->name,
                'consignee_address' => $client->address ?: $client->exact_address,
                'department' => 'ventas',
                'client_machine_id' => $component->client_machine_id,
                'subtotal' => 0,
                'total_amount' => 0,
                'status' => QuotationRequest::STATUS_RECEIVED,
                'user_id' => auth()->id(),
                'source_channel' => 'maintenance_component',
                'notes' => "Recomendacion: {$component->component_name} en estado {$component->status}. Uso {$component->current_usage}/{$component->estimated_life} {$component->counter_type}.",
            ]);

            QuotationRequestItem::create([
                'quotation_request_id' => $quote->id,
                'part_number' => $component->part_number,
                'description' => "Repuesto/servicio para {$component->component_name} ({$component->machine->name})",
                'quantity' => max(1, (int) $component->quantity),
                'unit_cost' => 0,
                'unit_price' => 0,
                'line_total' => 0,
                'internal_request_ref' => '1',
                'brand' => $component->component_brand,
            ]);

            ActivityLog::log('ventas', 'component_quote_created', "Cotizacion {$quote->reference} generada desde componente {$component->component_name}", [
                'quote_id' => $quote->id,
                'component_id' => $component->id,
            ]);

            return $quote;
        });
    }

    private function logUsage(CustomerMachineComponent $component, int $previousUsage, int $newUsage, ?string $previousStatus, string $newStatus, ?string $notes): void
    {
        ComponentUsageLog::create([
            'component_id' => $component->id,
            'previous_usage' => $previousUsage,
            'new_usage' => $newUsage,
            'counter_type' => $component->counter_type,
            'previous_residual_life' => (int) ($component->getOriginal('residual_life') ?? 0),
            'new_residual_life' => (int) $component->residual_life,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'updated_by' => auth()->id(),
            'notes' => $notes,
        ]);
    }

    private function normalizeHourBasedUsage(array $data): array
    {
        if (($data['counter_type'] ?? null) !== 'horas') {
            return $data;
        }

        $machine = ClientMachine::find($data['client_machine_id'] ?? null);
        $machineHourmeter = (int) ($machine?->hourmeter ?? 0);
        $replacementHourmeter = array_key_exists('last_replacement_hourmeter', $data)
            ? (int) $data['last_replacement_hourmeter']
            : max(0, $machineHourmeter - (int) ($data['current_usage'] ?? 0));

        $data['last_replacement_hourmeter'] = max(0, $replacementHourmeter);
        $data['current_usage'] = max(0, $machineHourmeter - $data['last_replacement_hourmeter']);

        return $data;
    }

    private function usageFromReading(CustomerMachineComponent $component, int $reading): int
    {
        if ($component->counter_type !== 'horas') {
            return max(0, $reading);
        }

        $machine = $component->machine;
        $currentHourmeter = max(0, $reading);

        if ($machine && (int) ($machine->hourmeter ?? 0) !== $currentHourmeter) {
            $machine->update([
                'hourmeter' => $currentHourmeter,
                'hourmeter_date' => now()->toDateString(),
            ]);
        }

        return max(0, $currentHourmeter - (int) ($component->last_replacement_hourmeter ?? 0));
    }
}
