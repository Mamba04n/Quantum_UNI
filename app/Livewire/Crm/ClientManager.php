<?php

namespace App\Livewire\Crm;

use App\Domain\Clientes\Services\CustomerService;
use App\Models\ActivityLog;
use App\Models\ClientMachine;
use App\Models\Entity;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Layout('layouts.app')]
class ClientManager extends Component
{
    use WithFileUploads, WithPagination;

    public $search = '';

    public $showModal = false;

    public $editingId = null;

    public bool $showDeleteModal = false;

    public ?int $deleteId = null;

    public $name = '';

    public $contact_info = '';

    public $email = '';

    public bool $portal_access = false;

    public string $portal_password = '';

    public string $portal_password_confirmation = '';

    public bool $portalAccountExists = false;

    public $rfc_ruc = '';

    public $phone_mobile = '';

    public $phone_home = '';

    public $machinery = '';

    public $part_types = '';

    public string $country = 'Nicaragua';

    public string $department = '';

    public string $municipality = '';

    public string $exact_address = '';

    public array $nicaraguaDepartments = [
        'Managua', 'Leon', 'Chinandega', 'Matagalpa', 'Esteli', 'Masaya', 'Granada',
        'Carazo', 'Rivas', 'Boaco', 'Chontales', 'Jinotega', 'Madriz', 'Nueva Segovia',
        'Rio San Juan', 'RACCN', 'RACCS',
    ];

    public $showFleetModal = false;

    public $selectedClientId = null;

    public $clientName = '';

    public $machines = [];

    public $machine_type = '';

    public $machine_name = '';

    public $machine_brand = '';

    public $machine_model = '';

    public $machine_serial = '';

    public $machine_internal_code = '';

    public $machine_engine = '';

    public $machine_year = '';

    public $machine_hourmeter = '';

    public $machine_hourmeter_date = '';

    public $machine_status = 'activa';

    public $machine_details = '';

    public $machine_image = null;

    public function render()
    {
        $clients = Entity::where('type', 'cliente')
            ->with([
                'accountReceivables',
                'invoices:id,entity_id,amount_nio,total,currency',
                'machines',
                'user',
            ])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('contact_info', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('rfc_ruc', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->simplePaginate(15);

        return view('livewire.crm.client-manager', compact('clients'));
    }

    public function openCreate()
    {
        $this->resetClientForm();
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        $client = Entity::findOrFail($id);

        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->contact_info = $client->contact_info ?? '';
        $this->email = $client->email ?? '';
        $client->loadMissing('user');
        $this->portalAccountExists = (bool) $client->user;
        $this->portal_access = (bool) $client->user?->is_active;
        $this->rfc_ruc = $client->rfc_ruc ?? '';
        $this->phone_mobile = $client->phone_mobile ?? '';
        $this->phone_home = $client->phone_home ?? '';
        $this->country = $client->country ?: 'Nicaragua';
        $this->department = $client->department ?? '';
        $this->municipality = $client->municipality ?? '';
        $this->exact_address = $client->exact_address ?? $client->address ?? '';
        $this->showModal = true;
    }

    public function save()
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $linkedUserId = $this->editingId ? Entity::whereKey($this->editingId)->value('user_id') : null;
        $emailRules = ['nullable', 'not_regex:/[\r\n]/', 'email', 'max:255'];
        if ($this->portal_access) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($linkedUserId);
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_info' => ['nullable', 'string', 'max:255'],
            'email' => $emailRules,
            'rfc_ruc' => ['nullable', 'string', 'max:50'],
            'phone_mobile' => ['nullable', 'string', 'max:20'],
            'phone_home' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'exact_address' => ['nullable', 'string', 'max:1000'],
            'portal_access' => ['boolean'],
            'portal_password' => [$this->portal_access && ! $this->portalAccountExists ? 'required' : 'nullable', 'string', 'min:10', 'max:255', 'confirmed'],
        ]);

        $portalAccess = (bool) $data['portal_access'];
        $portalPassword = $data['portal_password'] ?: null;
        unset($data['portal_access'], $data['portal_password']);

        if ($portalAccess && blank($data['email'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'El correo es obligatorio para crear acceso al portal.']);
        }

        $customerService = app(CustomerService::class);

        $client = DB::transaction(function () use ($customerService, $data, $portalAccess, $portalPassword) {
            if ($this->editingId) {
                $client = Entity::findOrFail($this->editingId);
                $client = $customerService->updateFromAdmin($client, $data);
                ActivityLog::log('crm', 'client_updated', "Cliente {$client->name} actualizado", ['client_id' => $client->id]);
            } else {
                $client = $customerService->createFromAdmin($data);
                ActivityLog::log('crm', 'client_created', "Cliente {$client->name} registrado", ['client_id' => $client->id]);
            }

            if ($portalAccess) {
                return $customerService->syncPortalAccess($client, $data['email'], $portalPassword, true);
            }
            if ($client->user_id) {
                return $customerService->disablePortalAccess($client);
            }

            return $client;
        });

        session()->flash('message', $this->editingId
            ? "Cliente {$client->name} actualizado."
            : "Cliente {$client->name} registrado.");

        $this->showModal = false;
        $this->resetClientForm();
    }

    public function openFleet($id)
    {
        $client = Entity::with('machines')->findOrFail($id);
        $this->selectedClientId = $client->id;
        $this->clientName = $client->name;
        $this->machines = $client->machines->toArray();
        $this->resetMachineForm();
        $this->showFleetModal = true;
    }

    public function addMachine()
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $data = $this->validate([
            'machine_type' => ['required', 'string', 'max:120'],
            'machine_name' => ['nullable', 'string', 'max:255'],
            'machine_brand' => ['nullable', 'string', 'max:120'],
            'machine_model' => ['nullable', 'string', 'max:120'],
            'machine_serial' => ['nullable', 'string', 'max:255'],
            'machine_internal_code' => ['nullable', 'string', 'max:255'],
            'machine_engine' => ['nullable', 'string', 'max:255'],
            'machine_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'machine_hourmeter' => ['nullable', 'integer', 'min:0'],
            'machine_hourmeter_date' => ['nullable', 'date'],
            'machine_status' => ['required', 'in:activa,en_mantenimiento,fuera_de_servicio'],
            'machine_details' => ['nullable', 'string', 'max:2000'],
            'machine_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = $this->machine_image ? $this->machine_image->store('client-machines', 'private') : null;
        $name = $data['machine_name'] ?: trim($data['machine_brand'].' '.$data['machine_model']) ?: $data['machine_type'];

        $machine = ClientMachine::create([
            'entity_id' => $this->selectedClientId,
            'machine_type' => $data['machine_type'],
            'name' => $name,
            'brand' => $data['machine_brand'] ?: null,
            'model' => $data['machine_model'] ?: null,
            'serial_number' => $data['machine_serial'] ?: null,
            'internal_code' => $data['machine_internal_code'] ?: null,
            'engine_serial' => $data['machine_engine'] ?: null,
            'year' => $data['machine_year'] ?: null,
            'hourmeter' => $data['machine_hourmeter'] ?: null,
            'hourmeter_date' => $data['machine_hourmeter_date'] ?: null,
            'status' => $data['machine_status'],
            'image_path' => $imagePath,
            'notes' => $data['machine_details'] ?: null,
            'technical_details' => $data['machine_details'] ?: null,
        ]);

        ActivityLog::log('crm', 'client_machine_added', "Maquinaria agregada a {$this->clientName}", [
            'client_id' => $this->selectedClientId,
            'client_machine_id' => $machine->id,
        ]);

        $this->openFleet($this->selectedClientId);
        session()->flash('fleet_message', 'Maquinaria registrada.');
    }

    public function deleteMachine($id)
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        ClientMachine::where('entity_id', $this->selectedClientId)->whereKey($id)->delete();
        $this->openFleet($this->selectedClientId);
    }

    public function deactivateClient(int $id): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $client = Entity::where('type', 'cliente')->findOrFail($id);
        $client->update(['status' => 'inactive']);

        ActivityLog::log('crm', 'customer.deactivated', "Cliente {$client->name} desactivado.", ['customer_id' => $client->id]);
        session()->flash('message', "Cliente {$client->name} desactivado. Se conserva su historial.");
    }

    public function reactivateClient(int $id): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $client = Entity::where('type', 'cliente')->findOrFail($id);
        $client->update(['status' => 'active']);

        ActivityLog::log('crm', 'customer.reactivated', "Cliente {$client->name} reactivado.", ['customer_id' => $client->id]);
        session()->flash('message', "Cliente {$client->name} reactivado.");
    }

    public function confirmDeleteClient(int $id): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function destroyClient(): void
    {
        abort_unless(auth()->user()?->can('crm.clients.manage'), 403);

        $client = Entity::where('type', 'cliente')->findOrFail($this->deleteId);

        if ($this->clientHasHistory($client)) {
            $client->update(['status' => 'inactive']);
            ActivityLog::log('crm', 'customer.deactivated', "Cliente {$client->name} desactivado por historial.", ['customer_id' => $client->id]);
            session()->flash('message', "Cliente {$client->name} tiene historial. Se desactivo y se ocultara de nuevas operaciones.");
        } else {
            ActivityLog::log('crm', 'customer.deleted', "Cliente {$client->name} eliminado.", ['customer_id' => $client->id]);
            $client->delete();
            session()->flash('message', "Cliente {$client->name} eliminado.");
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function closeFleet()
    {
        $this->showFleetModal = false;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetClientForm();
    }

    private function resetClientForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'contact_info',
            'email',
            'portal_access',
            'portal_password',
            'portal_password_confirmation',
            'portalAccountExists',
            'rfc_ruc',
            'phone_mobile',
            'phone_home',
            'department',
            'municipality',
            'exact_address',
        ]);
        $this->country = 'Nicaragua';
        $this->resetValidation();
    }

    private function resetMachineForm(): void
    {
        $this->reset([
            'machine_type',
            'machine_name',
            'machine_brand',
            'machine_model',
            'machine_serial',
            'machine_internal_code',
            'machine_engine',
            'machine_year',
            'machine_hourmeter',
            'machine_hourmeter_date',
            'machine_details',
            'machine_image',
        ]);
        $this->machine_status = 'activa';
        $this->resetValidation();
    }

    private function clientHasHistory(Entity $client): bool
    {
        return $client->accountReceivables()->exists()
            || $client->accountPayables()->exists()
            || $client->machines()->exists()
            || $client->saleOrders()->exists()
            || $client->orderTrackers()->exists();
    }
}
