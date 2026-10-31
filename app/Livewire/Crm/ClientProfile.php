<?php

namespace App\Livewire\Crm;

use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\ClientMachine;
use App\Models\CustomerMachineComponent;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\Ticket;
use App\Services\CustomerService;
use App\Services\MachineComponentLifecycleService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class ClientProfile extends Component
{
    use WithFileUploads;

    public $clientId;

    public $client;

    public $activeTab = 'profile';

    public bool $editingProfile = false;

    public bool $showLocationEditor = false;

    public bool $showMachineForm = false;

    public bool $showComponentForm = false;

    public bool $showContactForm = false;

    public string $profileName = '';

    public string $profileLegalName = '';

    public string $profileEmail = '';

    public string $profileRuc = '';

    public string $profileMobile = '';

    public string $profilePhone = '';

    public string $profileWebsite = '';

    public string $contactName = '';

    public string $contactPosition = '';

    public string $contactDepartment = '';

    public string $contactEmail = '';

    public string $contactMobile = '';

    public string $contactOfficePhone = '';

    public string $contactWhatsapp = '';

    public bool $contactIsPrimary = false;

    public string $contactNotes = '';

    public string $country = 'Nicaragua';

    public string $department = '';

    public string $municipality = '';

    public string $exactAddress = '';

    public string $machineType = '';

    public string $machineBrand = '';

    public string $machineModel = '';

    public string $machineYear = '';

    public string $machineSerial = '';

    public string $machineInternalCode = '';

    public $machineHourmeter = null;

    public ?string $machineHourmeterDate = null;

    public string $machineStatus = 'activa';

    public string $machineNotes = '';

    public $machineImage;

    public ?int $selectedMachineForComponents = null;

    public ?int $selectedComponentId = null;

    public string $componentName = '';

    public string $componentPartNumber = '';

    public string $componentSerialNumber = '';

    public string $componentModel = '';

    public string $componentBrand = '';

    public int $componentQuantity = 1;

    public string $componentCounterType = 'horas';

    public ?int $componentLastReplacementHourmeter = null;

    public int $componentCurrentUsage = 0;

    public int $componentEstimatedLife = 1;

    public ?string $componentInstallationDate = null;

    public string $componentNotes = '';

    public $componentImage;

    public int $newComponentUsage = 0;

    public string $usageUpdateNotes = '';

    public array $nicaraguaDepartments = [
        'Managua', 'Leon', 'Chinandega', 'Matagalpa', 'Esteli', 'Masaya', 'Granada',
        'Carazo', 'Rivas', 'Boaco', 'Chontales', 'Jinotega', 'Madriz', 'Nueva Segovia',
        'Rio San Juan', 'RACCN', 'RACCS',
    ];

    public array $counterTypes = [
        'horas' => 'Horas',
        'kilometros' => 'Kilometros',
        'dias' => 'Dias calendario',
        'meses' => 'Meses',
    ];

    public function mount($id)
    {
        $this->clientId = $id;
        $this->client = Entity::findOrFail($id);
        $this->country = $this->client->country ?: 'Nicaragua';
        $this->department = $this->client->department ?? '';
        $this->municipality = $this->client->municipality ?? '';
        $this->exactAddress = $this->client->exact_address ?? $this->client->address ?? '';
        $this->loadProfileForm();
    }

    public function editProfile(): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);
        $this->loadProfileForm();
        $this->editingProfile = true;
    }

    public function cancelProfileEdit(): void
    {
        $this->editingProfile = false;
        $this->resetValidation();
    }

    public function saveProfile(CustomerService $customerService): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $data = $this->validate([
            'profileName' => ['required', 'string', 'max:255'],
            'profileLegalName' => ['nullable', 'string', 'max:255'],
            'profileEmail' => ['nullable', 'email', 'max:255'],
            'profileRuc' => ['nullable', 'string', 'max:50'],
            'profileMobile' => ['nullable', 'string', 'max:20'],
            'profilePhone' => ['nullable', 'string', 'max:20'],
            'profileWebsite' => ['nullable', 'url', 'max:255'],
        ]);

        $this->client = $customerService->updateFromAdmin($this->client, [
            'name' => $data['profileName'],
            'legal_name' => $data['profileLegalName'] ?: $data['profileName'],
            'email' => $data['profileEmail'] ?: null,
            'rfc_ruc' => $data['profileRuc'] ?: null,
            'phone_mobile' => $data['profileMobile'] ?: null,
            'phone_home' => $data['profilePhone'] ?: null,
            'website' => $data['profileWebsite'] ?: null,
            'country' => $this->client->country,
            'department' => $this->client->department,
            'municipality' => $this->client->municipality,
            'exact_address' => $this->client->exact_address,
        ]);

        $this->client = $this->client->fresh();
        $this->editingProfile = false;
        session()->flash('message', 'Datos del cliente actualizados.');
    }

    public function addContact(): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $data = $this->validate([
            'contactName' => ['required', 'string', 'max:255'],
            'contactPosition' => ['nullable', 'string', 'max:120'],
            'contactDepartment' => ['nullable', 'string', 'max:120'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactMobile' => ['nullable', 'string', 'max:30'],
            'contactOfficePhone' => ['nullable', 'string', 'max:30'],
            'contactWhatsapp' => ['nullable', 'string', 'max:30'],
            'contactIsPrimary' => ['boolean'],
            'contactNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data): void {
            if ($data['contactIsPrimary']) {
                $this->client->contacts()->update(['is_primary' => false]);
            }

            $contact = $this->client->contacts()->create([
                'name' => $data['contactName'],
                'position' => $data['contactPosition'] ?: null,
                'department' => $data['contactDepartment'] ?: null,
                'email' => $data['contactEmail'] ?: null,
                'mobile' => $data['contactMobile'] ?: null,
                'office_phone' => $data['contactOfficePhone'] ?: null,
                'whatsapp' => $data['contactWhatsapp'] ?: null,
                'is_primary' => $data['contactIsPrimary'],
                'notes' => $data['contactNotes'] ?: null,
            ]);

            if ($contact->is_primary) {
                $this->client->update([
                    'contact_name' => $contact->name,
                    'contact_position' => $contact->position,
                ]);
            }
        });

        $this->resetContactForm();
        $this->showContactForm = false;
        session()->flash('message', 'Persona de contacto registrada.');
    }

    public function deleteContact(int $contactId): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);
        $contact = $this->client->contacts()->findOrFail($contactId);
        $contact->delete();
        session()->flash('message', 'Persona de contacto eliminada.');
    }

    public function toggleClientStatus(): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $inactive = in_array($this->client->status, ['inactive', 'inactivo'], true);
        $this->client->update(['status' => $inactive ? 'active' : 'inactive']);
        ActivityLog::log('crm', $inactive ? 'customer.reactivated' : 'customer.deactivated', "Estado de {$this->client->name} actualizado.", ['customer_id' => $this->client->id]);
        $this->client = $this->client->fresh();
        session()->flash('message', $inactive ? 'Cliente reactivado.' : 'Cliente desactivado. Se conserva su historial.');
    }

    public function deleteClient(): mixed
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        if ($this->clientHasHistory()) {
            $this->client->update(['status' => 'inactive']);
            ActivityLog::log('crm', 'customer.deactivated', "Cliente {$this->client->name} desactivado por historial.", ['customer_id' => $this->client->id]);
            $this->client = $this->client->fresh();
            session()->flash('message', 'El cliente tiene historial y fue desactivado para conservarlo.');

            return null;
        }

        $name = $this->client->name;
        ActivityLog::log('crm', 'customer.deleted', "Cliente {$name} eliminado.", ['customer_id' => $this->client->id]);
        $this->client->delete();

        return redirect()->route('clients.index')->with('message', "Cliente {$name} eliminado.");
    }

    private function loadProfileForm(): void
    {
        $this->profileName = $this->client->name;
        $this->profileLegalName = $this->client->legal_name ?? '';
        $this->profileEmail = $this->client->email ?? '';
        $this->profileRuc = $this->client->rfc_ruc ?? '';
        $this->profileMobile = $this->client->phone_mobile ?? '';
        $this->profilePhone = $this->client->phone_home ?? '';
        $this->profileWebsite = $this->client->website ?? '';
    }

    private function resetContactForm(): void
    {
        $this->reset([
            'contactName', 'contactPosition', 'contactDepartment', 'contactEmail',
            'contactMobile', 'contactOfficePhone', 'contactWhatsapp', 'contactNotes',
        ]);
        $this->contactIsPrimary = false;
        $this->resetValidation();
    }

    private function clientHasHistory(): bool
    {
        return $this->client->accountReceivables()->exists()
            || $this->client->accountPayables()->exists()
            || $this->client->machines()->exists()
            || $this->client->saleOrders()->exists()
            || $this->client->orderTrackers()->exists()
            || $this->client->invoices()->exists();
    }

    public function selectMachineComponents(int $machineId): void
    {
        ClientMachine::where('entity_id', $this->clientId)->findOrFail($machineId);
        $this->selectedMachineForComponents = $machineId;
        $this->selectedComponentId = null;
        $this->resetComponentForm();
        $this->activeTab = 'machines';
    }

    public function addComponent(MachineComponentLifecycleService $service): void
    {
        $this->validate([
            'selectedMachineForComponents' => ['required', 'exists:client_machines,id'],
            'componentName' => ['required', 'string', 'max:255'],
            'componentPartNumber' => ['required', 'string', 'max:255'],
            'componentSerialNumber' => ['nullable', 'string', 'max:255'],
            'componentModel' => ['nullable', 'string', 'max:255'],
            'componentBrand' => ['nullable', 'string', 'max:255'],
            'componentQuantity' => ['required', 'integer', 'min:1'],
            'componentCounterType' => ['required', 'in:horas,kilometros,dias,meses'],
            'componentLastReplacementHourmeter' => ['nullable', 'integer', 'min:0'],
            'componentCurrentUsage' => ['required', 'integer', 'min:0'],
            'componentEstimatedLife' => ['required', 'integer', 'min:1'],
            'componentInstallationDate' => ['nullable', 'date', 'before_or_equal:today'],
            'componentNotes' => ['nullable', 'string', 'max:2000'],
            'componentImage' => ['nullable', 'image', 'max:5120'],
        ]);

        $machine = ClientMachine::where('entity_id', $this->clientId)->findOrFail($this->selectedMachineForComponents);
        $imagePath = $this->componentImage ? $this->componentImage->store('machine-components', 'private') : null;

        $service->create([
            'client_machine_id' => $machine->id,
            'component_name' => $this->componentName,
            'part_number' => $this->componentPartNumber,
            'serial_number' => $this->componentSerialNumber ?: null,
            'component_model' => $this->componentModel ?: null,
            'component_brand' => $this->componentBrand ?: null,
            'quantity' => $this->componentQuantity,
            'counter_type' => $this->componentCounterType,
            'last_replacement_hourmeter' => $this->componentCounterType === 'horas'
                ? ($this->componentLastReplacementHourmeter ?? (int) ($machine->hourmeter ?? 0))
                : null,
            'current_usage' => $this->componentCurrentUsage,
            'estimated_life' => $this->componentEstimatedLife,
            'last_installation_date' => $this->componentInstallationDate ?: null,
            'image_path' => $imagePath,
            'notes' => $this->componentNotes ?: null,
        ]);

        $this->resetComponentForm();
        $this->showComponentForm = false;
        session()->flash('message', 'Componente agregado a la maquinaria.');
    }

    public function showComponent(int $componentId): void
    {
        $component = CustomerMachineComponent::whereHas('machine', fn ($q) => $q->where('entity_id', $this->clientId))
            ->with('usageLogs.updater')
            ->findOrFail($componentId);

        $this->selectedComponentId = $component->id;
        $this->newComponentUsage = $component->counter_type === 'horas'
            ? (int) ($component->machine?->hourmeter ?? $component->current_usage)
            : (int) $component->current_usage;
        $this->usageUpdateNotes = '';
    }

    public function updateComponentUsage(MachineComponentLifecycleService $service): void
    {
        $this->validate([
            'selectedComponentId' => ['required', 'exists:customer_machine_components,id'],
            'newComponentUsage' => ['required', 'integer', 'min:0'],
            'usageUpdateNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $component = CustomerMachineComponent::whereHas('machine', fn ($q) => $q->where('entity_id', $this->clientId))
            ->findOrFail($this->selectedComponentId);

        $service->updateUsage($component, $this->newComponentUsage, $this->usageUpdateNotes ?: null);
        $this->showComponent($component->id);
        session()->flash('message', 'Uso del componente actualizado.');
    }

    public function generateComponentQuote(int $componentId, MachineComponentLifecycleService $service): mixed
    {
        $component = CustomerMachineComponent::whereHas('machine', fn ($q) => $q->where('entity_id', $this->clientId))
            ->with('machine.entity')
            ->findOrFail($componentId);

        $quote = $service->generateQuotation($component);

        return redirect()->route('sales.quotations.manage')->with('message', "Cotizacion {$quote->reference} generada desde componente.");
    }

    public function createComponentTicket(int $componentId): void
    {
        $component = CustomerMachineComponent::whereHas('machine', fn ($q) => $q->where('entity_id', $this->clientId))
            ->with('machine.entity')
            ->findOrFail($componentId);

        $ticket = Ticket::create([
            'code' => 'TKT-'.now()->format('Y').'-'.str_pad((string) (Ticket::count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => auth()->id(),
            'type' => Ticket::TYPE_SUPPORT,
            'subject' => "Revision de {$component->component_name}",
            'description' => "Componente {$component->component_name} de {$component->machine->name} en estado {$component->status}. Uso {$component->current_usage}/{$component->estimated_life} {$component->counter_type}.",
            'priority' => in_array($component->status, ['critico', 'vencido'], true) ? Ticket::PRIORITY_URGENT : Ticket::PRIORITY_HIGH,
            'status' => Ticket::STATUS_OPEN,
            'client_machine_id' => $component->client_machine_id,
            'related_sku' => $component->part_number,
            'last_message_at' => now(),
            'last_admin_message_at' => now(),
            'machinery' => $component->machine->name,
            'machine_brand' => $component->machine->brand,
            'machine_model' => $component->machine->model,
            'equipment_serial' => $component->machine->serial_number,
            'part_code' => $component->part_number,
            'request_quantity' => $component->quantity,
            'request_urgency' => $component->status,
        ]);

        ActivityLog::log('crm', 'component_ticket_created', "Ticket {$ticket->code} creado desde componente {$component->component_name}", [
            'ticket_id' => $ticket->id,
            'component_id' => $component->id,
        ]);

        session()->flash('message', "Ticket {$ticket->code} creado desde componente.");
    }

    public function saveLocation(): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $this->validate([
            'country' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'exactAddress' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->client->update([
            'country' => $this->country ?: null,
            'department' => $this->department ?: null,
            'municipality' => $this->municipality ?: null,
            'exact_address' => $this->exactAddress ?: null,
            'address' => $this->exactAddress ?: null,
        ]);

        ActivityLog::log('crm', 'client_updated', "Cliente {$this->client->name} actualizado", [
            'client_id' => $this->client->id,
        ]);

        $this->client = $this->client->fresh();
        $this->showLocationEditor = false;
        session()->flash('message', 'Ubicacion actualizada.');
    }

    public function addMachine(): void
    {
        $this->validate([
            'machineType' => ['required', 'string', 'max:120'],
            'machineBrand' => ['nullable', 'string', 'max:120'],
            'machineModel' => ['nullable', 'string', 'max:120'],
            'machineYear' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'machineSerial' => ['nullable', 'string', 'max:255'],
            'machineInternalCode' => ['nullable', 'string', 'max:255'],
            'machineHourmeter' => ['nullable', 'integer', 'min:0'],
            'machineHourmeterDate' => ['nullable', 'date'],
            'machineStatus' => ['required', 'in:activa,en_mantenimiento,fuera_de_servicio'],
            'machineNotes' => ['nullable', 'string', 'max:2000'],
            'machineImage' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = $this->machineImage ? $this->machineImage->store('client-machines', 'private') : null;

        $machine = ClientMachine::create([
            'entity_id' => $this->clientId,
            'machine_type' => $this->machineType,
            'name' => trim($this->machineBrand.' '.$this->machineModel) ?: $this->machineType,
            'brand' => $this->machineBrand ?: null,
            'model' => $this->machineModel ?: null,
            'year' => $this->machineYear ?: null,
            'serial_number' => $this->machineSerial ?: null,
            'internal_code' => $this->machineInternalCode ?: null,
            'hourmeter' => $this->machineHourmeter ?: null,
            'hourmeter_date' => $this->machineHourmeterDate ?: null,
            'status' => $this->machineStatus,
            'image_path' => $imagePath,
            'notes' => $this->machineNotes ?: null,
            'technical_details' => $this->machineNotes ?: null,
        ]);

        ActivityLog::log('crm', 'client_machine_added', "Maquinaria agregada a {$this->client->name}", [
            'client_id' => $this->clientId,
            'client_machine_id' => $machine->id,
        ]);

        $this->resetMachineForm();
        $this->showMachineForm = false;
        session()->flash('message', 'Maquinaria agregada.');
    }

    public function updateHourmeter(int $machineId, int $value): void
    {
        $machine = ClientMachine::where('entity_id', $this->clientId)->findOrFail($machineId);
        $machine->update([
            'hourmeter' => max(0, $value),
            'hourmeter_date' => now()->toDateString(),
        ]);

        ActivityLog::log('crm', 'hourmeter_updated', "Horometro actualizado para {$machine->name}", [
            'client_id' => $this->clientId,
            'client_machine_id' => $machine->id,
            'hourmeter' => $value,
        ]);
    }

    private function resetMachineForm(): void
    {
        $this->reset([
            'machineType',
            'machineBrand',
            'machineModel',
            'machineYear',
            'machineSerial',
            'machineInternalCode',
            'machineHourmeter',
            'machineHourmeterDate',
            'machineNotes',
            'machineImage',
        ]);
        $this->machineStatus = 'activa';
        $this->resetValidation();
    }

    private function resetComponentForm(): void
    {
        $this->reset([
            'componentName',
            'componentPartNumber',
            'componentSerialNumber',
            'componentModel',
            'componentBrand',
            'componentNotes',
            'componentImage',
        ]);
        $this->componentQuantity = 1;
        $this->componentCounterType = 'horas';
        $this->componentLastReplacementHourmeter = null;
        $this->componentCurrentUsage = 0;
        $this->componentEstimatedLife = 1;
        $this->componentInstallationDate = null;
        $this->resetValidation();
    }

    public function render()
    {
        $orders = SaleOrder::where('client_name', $this->client->name)
            ->with('items')
            ->orderBy('date', 'desc')
            ->get();

        $receivables = AccountReceivable::where('entity_id', $this->clientId)
            ->orderBy('created_at', 'desc')
            ->get();

        $invoices = Invoice::where('entity_id', $this->clientId)
            ->orderBy('date', 'desc')
            ->get();

        $machines = ClientMachine::with(['components.usageLogs.updater'])->where('entity_id', $this->clientId)->latest()->get();
        if (! $this->selectedMachineForComponents && $machines->isNotEmpty()) {
            $this->selectedMachineForComponents = $machines->first()->id;
        }
        $selectedMachine = $this->selectedMachineForComponents
            ? $machines->firstWhere('id', $this->selectedMachineForComponents)
            : null;
        $selectedComponent = $this->selectedComponentId
            ? CustomerMachineComponent::with('usageLogs.updater')->find($this->selectedComponentId)
            : null;
        $tickets = Ticket::whereHas('customer', fn ($q) => $q->where('email', $this->client->email))
            ->orWhereIn('client_machine_id', $machines->pluck('id'))
            ->latest()
            ->limit(25)
            ->get();
        $quotationRequests = QuotationRequest::where('quote_to_email', $this->client->email)
            ->orWhere('quote_to_name', $this->client->name)
            ->orWhereIn('client_machine_id', $machines->pluck('id'))
            ->latest()
            ->limit(25)
            ->get();

        // KPIs del cliente
        $totalFacturado = $receivables->sum('total_amount') + $invoices->sum('amount_nio');
        $totalPendiente = $receivables->where('status', '!=', 'pagado')->sum(fn ($r) => $r->total_amount - $r->paid_amount);
        $totalCobrado = $receivables->sum('paid_amount') + $invoices->where('status', 'Pagado')->sum('amount_nio');
        $pedidosActivos = $orders->whereNotIn('status', ['entregado', 'cancelado'])->count();
        $contacts = $this->client->contacts()->get();

        return view('livewire.crm.client-profile', compact(
            'orders', 'receivables', 'invoices',
            'totalFacturado', 'totalPendiente', 'totalCobrado', 'pedidosActivos',
            'machines', 'tickets', 'quotationRequests', 'selectedMachine', 'selectedComponent', 'contacts'
        ));
    }
}
