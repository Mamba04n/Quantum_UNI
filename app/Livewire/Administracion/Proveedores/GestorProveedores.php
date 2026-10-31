<?php

namespace App\Livewire\Administracion\Proveedores;

use App\Domain\Proveedores\Perfil\Queries\ListarProveedoresQuery;
use App\Domain\Proveedores\Perfil\Queries\ObtenerProveedorDetalleQuery;
use App\Domain\Proveedores\Perfil\Queries\OpcionesFiltroProveedoresQuery;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\EntityBankAccount;
use App\Models\EntityContact;
use App\Models\Brand;
use App\Models\MachineType;
use App\Models\PaymentMethod;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class GestorProveedores extends Component
{
    use WithPagination;

    public string $search = '';
    public string $countryFilter = '';
    public string $brandFilter = '';
    public string $productTypeFilter = '';
    public string $machineTypeFilter = '';
    public string $paymentMethodFilter = '';
    public string $statusFilter = '';
    public ?int $selectedSupplierId = null;
    public string $activeTab = 'general';
    public bool $showForm = false;

    public string $name = '';
    public string $legalName = '';
    public string $taxId = '';
    public string $country = '';
    public string $city = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public string $contactName = '';
    public string $contactPosition = '';
    public string $whatsapp = '';
    public string $status = 'activo';
    public array $qualityTypes = [];

    public bool $showContactForm = false;
    public string $newContactName = '';
    public string $newContactPosition = '';
    public string $newContactDepartment = '';
    public string $newContactEmail = '';
    public string $newContactMobile = '';
    public string $newContactOfficePhone = '';
    public string $newContactWhatsapp = '';
    public string $newContactNotes = '';
    public bool $newContactIsPrimary = false;

    public array $qualityTypeOptions = [
        'A1' => 'Original fabricante de equipo',
        'A2' => 'Mercado gris',
        'B1' => 'OEM',
        'B2' => 'Aftermarket premium',
        'C1' => 'Aftermarket regular',
        'C2' => 'Aftermarket baja calidad',
    ];

    public string $brandInput = '';
    public string $productTypeInput = '';
    public string $machineTypeInput = '';
    public string $paymentMethodInput = '';

    public string $bankName = '';
    public string $accountHolder = '';
    public string $accountNumber = '';
    public string $accountType = '';
    public string $currency = 'USD';
    public string $swiftIban = '';
    public string $bankCountry = '';
    public string $bankNotes = '';

    public array $productTypeOptions = [
        'Repuestos electricos',
        'Repuestos mecanicos',
        'Hidraulicos',
        'Lubricantes',
        'Filtros',
        'Motores',
        'Transmision',
        'Herramientas',
        'Maquinaria',
        'Otros',
    ];

    public array $machineTypeOptions = [
        'Excavadora',
        'Cargador frontal',
        'Montacargas',
        'Camion',
        'Tractor',
        'Generador',
        'Compresor',
        'Otro',
    ];

    public array $paymentMethodOptions = [
        'Transferencia nacional',
        'Transferencia internacional',
        'Tarjeta',
        'Pago online',
        'PayPal / Stripe',
        'Credito comercial',
        'Contra entrega',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCountryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedBrandFilter(): void
    {
        $this->resetPage();
    }

    public function updatedProductTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedMachineTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentMethodFilter(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'countryFilter', 'brandFilter', 'productTypeFilter', 'machineTypeFilter', 'paymentMethodFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function canSeeBankData(): bool
    {
        return auth()->user()?->can('supplier.view_bank_accounts')
            || auth()->user()?->can('tracker.view_sensitive_documents')
            || auth()->user()?->can('supplier_bid.view_supplier_documents')
            || auth()->user()?->can('full_access');
    }

    public function create(): void
    {
        abort_unless(auth()->user()?->can('supplier.create') || auth()->user()?->can('supplier_bid.create'), 403);

        $this->resetForm();
        $this->selectedSupplierId = null;
        $this->showForm = true;
        $this->activeTab = 'general';
    }

    public function selectSupplier(int $supplierId): void
    {
        abort_unless(auth()->user()?->can('supplier.view') || auth()->user()?->can('supplier_bid.view') || auth()->user()?->can('supplier_bid.create'), 403);

        $this->selectedSupplierId = $supplierId;
        $this->showForm = false;
        $this->activeTab = 'general';

        ActivityLog::log('compras', 'supplier.viewed', 'Perfil de proveedor visualizado.', [
            'supplier_id' => $supplierId,
        ]);
    }

    public function backToList(): void
    {
        $this->selectedSupplierId = null;
        $this->showForm = false;
        $this->activeTab = 'general';
        $this->resetValidation();
    }

    public function edit(int $supplierId): void
    {
        abort_unless(auth()->user()?->can('supplier.edit') || auth()->user()?->can('supplier_bid.edit_supplier_response'), 403);

        $supplier = Entity::where('type', 'proveedor')->findOrFail($supplierId);
        $this->selectedSupplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->legalName = $supplier->legal_name ?? '';
        $this->taxId = $supplier->rfc_ruc ?? '';
        $this->country = $supplier->country ?? '';
        $this->city = $supplier->city ?? '';
        $this->address = $supplier->address ?? $supplier->exact_address ?? '';
        $this->phone = $supplier->phone_mobile ?? '';
        $this->email = $supplier->email ?? '';
        $this->website = $supplier->website ?? '';
        $this->contactName = $supplier->contact_name ?? '';
        $this->contactPosition = $supplier->contact_position ?? '';
        $this->whatsapp = $supplier->whatsapp ?? '';
        $this->status = $supplier->status ?? 'activo';
        $this->qualityTypes = collect($supplier->quality_types ?? [])
            ->filter(fn ($quality) => array_key_exists($quality, $this->qualityTypeOptions))
            ->values()
            ->all();
        $this->showForm = true;
        $this->activeTab = 'general';
    }

    public function save(): void
    {
        $permission = $this->selectedSupplierId ? 'supplier.edit' : 'supplier.create';
        abort_unless(auth()->user()?->can($permission) || auth()->user()?->can('supplier_bid.create') || auth()->user()?->can('supplier_bid.edit_supplier_response'), 403);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'legalName' => ['nullable', 'string', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'not_regex:/[\r\n]/', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactPosition' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:activo,inactivo'],
            'qualityTypes' => ['nullable', 'array'],
            'qualityTypes.*' => ['in:'.implode(',', array_keys($this->qualityTypeOptions))],
        ]);

        $payload = [
            'name' => trim($data['name']),
            'type' => 'proveedor',
            'legal_name' => $data['legalName'] ?: null,
            'rfc_ruc' => $data['taxId'] ?: null,
            'country' => $data['country'] ?: null,
            'city' => $data['city'] ?: null,
            'address' => $data['address'] ?: null,
            'exact_address' => $data['address'] ?: null,
            'phone_mobile' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'website' => $data['website'] ?: null,
            'contact_name' => $data['contactName'] ?: null,
            'contact_position' => $data['contactPosition'] ?: null,
            'whatsapp' => $data['whatsapp'] ?: null,
            'status' => $data['status'],
            'quality_types' => collect($data['qualityTypes'] ?? [])->unique()->values()->all(),
        ];

        $supplier = $this->selectedSupplierId
            ? tap(Entity::where('type', 'proveedor')->findOrFail($this->selectedSupplierId))->update($payload)
            : Entity::create($payload);

        ActivityLog::log('compras', $this->selectedSupplierId ? 'supplier.updated' : 'supplier.created', "Proveedor {$supplier->name} guardado.", [
            'supplier_id' => $supplier->id,
        ]);

        $this->selectedSupplierId = $supplier->id;
        $this->showForm = false;
        session()->flash('message', 'Proveedor guardado.');
    }

    public function addBrand(): void
    {
        $this->addSimpleChild(Brand::class, 'name', $this->brandInput, 'brandInput', 'supplier.brand_added', 'Marca agregada al proveedor.');
    }

    public function addContact(): void
    {
        abort_unless(auth()->user()?->can('supplier.edit') || auth()->user()?->can('supplier_bid.edit_supplier_response'), 403);

        $supplier = Entity::where('type', 'proveedor')->findOrFail($this->selectedSupplierId);
        $data = $this->validate([
            'newContactName' => ['required', 'string', 'max:255'],
            'newContactPosition' => ['nullable', 'string', 'max:120'],
            'newContactDepartment' => ['nullable', 'string', 'max:120'],
            'newContactEmail' => ['nullable', 'email', 'max:255'],
            'newContactMobile' => ['nullable', 'string', 'max:30'],
            'newContactOfficePhone' => ['nullable', 'string', 'max:30'],
            'newContactWhatsapp' => ['nullable', 'string', 'max:30'],
            'newContactNotes' => ['nullable', 'string', 'max:1000'],
            'newContactIsPrimary' => ['boolean'],
        ]);

        DB::transaction(function () use ($supplier, $data): void {
            $isPrimary = $data['newContactIsPrimary'] || ! $supplier->contacts()->exists();

            if ($isPrimary) {
                $supplier->contacts()->update(['is_primary' => false]);
            }

            $contact = $supplier->contacts()->create([
                'name' => trim($data['newContactName']),
                'position' => $data['newContactPosition'] ?: null,
                'department' => $data['newContactDepartment'] ?: null,
                'email' => $data['newContactEmail'] ?: null,
                'mobile' => $data['newContactMobile'] ?: null,
                'office_phone' => $data['newContactOfficePhone'] ?: null,
                'whatsapp' => $data['newContactWhatsapp'] ?: null,
                'notes' => $data['newContactNotes'] ?: null,
                'is_primary' => $isPrimary,
                'is_active' => true,
            ]);

            if ($isPrimary) {
                $this->syncPrimaryContact($supplier, $contact);
            }
        });

        $this->resetContactForm();
        $this->showContactForm = false;
        session()->flash('message', 'Contacto del proveedor registrado.');
    }

    public function setPrimaryContact(int $contactId): void
    {
        abort_unless(auth()->user()?->can('supplier.edit') || auth()->user()?->can('supplier_bid.edit_supplier_response'), 403);

        $supplier = Entity::where('type', 'proveedor')->findOrFail($this->selectedSupplierId);
        $contact = $supplier->contacts()->findOrFail($contactId);

        DB::transaction(function () use ($supplier, $contact): void {
            $supplier->contacts()->update(['is_primary' => false]);
            $contact->update(['is_primary' => true, 'is_active' => true]);
            $this->syncPrimaryContact($supplier, $contact);
        });

        session()->flash('message', 'Contacto principal actualizado.');
    }

    public function deleteContact(int $contactId): void
    {
        abort_unless(auth()->user()?->can('supplier.edit') || auth()->user()?->can('supplier_bid.edit_supplier_response'), 403);

        $supplier = Entity::where('type', 'proveedor')->findOrFail($this->selectedSupplierId);
        $contact = $supplier->contacts()->findOrFail($contactId);
        $wasPrimary = $contact->is_primary;

        DB::transaction(function () use ($supplier, $contact, $wasPrimary): void {
            $contact->delete();

            if ($wasPrimary) {
                $replacement = $supplier->contacts()->first();
                if ($replacement) {
                    $replacement->update(['is_primary' => true]);
                    $this->syncPrimaryContact($supplier, $replacement);
                } else {
                    $supplier->update(['contact_name' => null, 'contact_position' => null, 'whatsapp' => null]);
                }
            }
        });

        session()->flash('message', 'Contacto eliminado.');
    }

    public function addProductType(): void
    {
        $this->addSimpleChild(ProductType::class, 'name', $this->productTypeInput, 'productTypeInput', 'supplier.product_type_added', 'Tipo de producto agregado.');
    }

    public function addMachineType(): void
    {
        $this->addSimpleChild(MachineType::class, 'name', $this->machineTypeInput, 'machineTypeInput', 'supplier.machine_type_added', 'Tipo de maquinaria agregado.');
    }

    public function addPaymentMethod(): void
    {
        $this->addSimpleChild(PaymentMethod::class, 'name', $this->paymentMethodInput, 'paymentMethodInput', 'supplier.payment_method_added', 'Metodo de pago agregado.');
    }

    public function deleteChild(string $model, int $id): void
    {
        abort_unless(auth()->user()?->can('supplier.manage_brands') || auth()->user()?->can('supplier.edit') || auth()->user()?->can('supplier_bid.delete_supplier_response'), 403);

        abort_unless(in_array($model, [
            Brand::class,
            ProductType::class,
            MachineType::class,
            PaymentMethod::class,
        ], true), 403);

        $supplier = Entity::findOrFail($this->selectedSupplierId);
        $method = match($model) {
            Brand::class => 'brands',
            ProductType::class => 'productTypes',
            MachineType::class => 'machineTypes',
            PaymentMethod::class => 'paymentMethods',
        };

        $supplier->$method()->detach($id);
    }

    public function addBankAccount(): void
    {
        abort_unless($this->canSeeBankData() && (auth()->user()?->can('supplier.manage_bank_accounts') || auth()->user()?->can('full_access')), 403);

        $data = $this->validate([
            'bankName' => ['required', 'string', 'max:255'],
            'accountHolder' => ['required', 'string', 'max:255'],
            'accountNumber' => ['required', 'string', 'max:255'],
            'accountType' => ['nullable', 'string', 'max:80'],
            'currency' => ['required', 'string', 'max:10'],
            'swiftIban' => ['nullable', 'string', 'max:120'],
            'bankCountry' => ['nullable', 'string', 'max:120'],
            'bankNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        EntityBankAccount::create([
            'entity_id' => $this->selectedSupplierId,
            'bank_name' => $data['bankName'],
            'account_holder' => $data['accountHolder'],
            'account_number' => $data['accountNumber'],
            'account_type' => $data['accountType'] ?: null,
            'currency' => strtoupper($data['currency']),
            'swift_iban' => $data['swiftIban'] ?: null,
            'bank_country' => $data['bankCountry'] ?: null,
            'notes' => $data['bankNotes'] ?: null,
        ]);

        ActivityLog::log('compras', 'supplier.bank_account_added', 'Cuenta bancaria agregada a proveedor.', [
            'supplier_id' => $this->selectedSupplierId,
        ]);

        $this->reset(['bankName', 'accountHolder', 'accountNumber', 'accountType', 'swiftIban', 'bankCountry', 'bankNotes']);
        $this->currency = 'USD';
        session()->flash('message', 'Cuenta bancaria agregada.');
    }

    public function deleteBankAccount(int $id): void
    {
        abort_unless($this->canSeeBankData() && (auth()->user()?->can('supplier.manage_bank_accounts') || auth()->user()?->can('full_access')), 403);
        EntityBankAccount::where('entity_id', $this->selectedSupplierId)->whereKey($id)->delete();
    }

    public function deactivateSupplier(int $supplierId): void
    {
        abort_unless(auth()->user()?->can('supplier.deactivate') || auth()->user()?->can('supplier_bid.close'), 403);

        $supplier = Entity::where('type', 'proveedor')->findOrFail($supplierId);
        $supplier->update(['status' => 'inactivo']);

        ActivityLog::log('compras', 'supplier.deactivated', "Proveedor {$supplier->name} desactivado.", [
            'supplier_id' => $supplier->id,
        ]);
    }

    public function activateSupplier(int $supplierId): void
    {
        abort_unless(auth()->user()?->can('supplier.deactivate') || auth()->user()?->can('supplier_bid.close'), 403);

        $supplier = Entity::where('type', 'proveedor')->findOrFail($supplierId);
        $supplier->update(['status' => 'activo']);

        ActivityLog::log('compras', 'supplier.activated', "Proveedor {$supplier->name} activado.", [
            'supplier_id' => $supplier->id,
        ]);
    }

    public function deleteSupplier(int $supplierId): void
    {
        abort_unless(auth()->user()?->can('supplier.delete'), 403);

        $supplier = Entity::withCount(['supplierBids', 'supplierBidResponses', 'purchaseOrders', 'accountPayables'])
            ->where('type', 'proveedor')
            ->findOrFail($supplierId);

        if ($supplier->supplier_bids_count || $supplier->supplier_bid_responses_count || $supplier->purchase_orders_count || $supplier->account_payables_count) {
            $supplier->update(['status' => 'inactivo']);
            session()->flash('message', 'El proveedor tiene historial. Se desactivo en lugar de eliminarse.');
            return;
        }

        $supplier->delete();
        $this->backToList();

        ActivityLog::log('compras', 'supplier.deleted', "Proveedor {$supplier->name} eliminado.", [
            'supplier_id' => $supplierId,
        ]);
    }

    public function render()
    {
        $suppliers = $this->supplierQuery()
            ->with(['brands', 'productTypes', 'machineTypes', 'paymentMethods'])
            ->withCount(['supplierBids', 'supplierBidResponses', 'purchaseOrders'])
            ->latest()
            ->simplePaginate(10);

        $selectedSupplier = $this->selectedSupplierId ? $this->selectedSupplier() : null;

        return view('livewire.administracion.proveedores.gestor-proveedores', [
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'countries' => $this->filterOptions('country'),
            'brandOptions' => Brand::query()->select('name')->distinct()->orderBy('name')->pluck('name'),
            'paymentOptions' => PaymentMethod::query()->select('name')->distinct()->orderBy('name')->pluck('name'),
        ]);
    }

    private function supplierQuery(): Builder
    {
        return app(ListarProveedoresQuery::class)->ejecutar([
            'search' => $this->search,
            'countryFilter' => $this->countryFilter,
            'statusFilter' => $this->statusFilter,
            'brandFilter' => $this->brandFilter,
            'productTypeFilter' => $this->productTypeFilter,
            'machineTypeFilter' => $this->machineTypeFilter,
            'paymentMethodFilter' => $this->paymentMethodFilter,
        ]);
    }

    private function selectedSupplier(): ?Entity
    {
        if ($this->activeTab === 'banks' && $this->canSeeBankData()) {
            ActivityLog::log('compras', 'supplier.bank_account_viewed', 'Datos bancarios de proveedor visualizados.', [
                'supplier_id' => $this->selectedSupplierId,
            ]);
        }

        return app(ObtenerProveedorDetalleQuery::class)->ejecutar(
            $this->selectedSupplierId,
            $this->canSeeBankData()
        );
    }

    private function filterOptions(string $column)
    {
        return app(OpcionesFiltroProveedoresQuery::class)->ejecutar($column);
    }

    private function addSimpleChild(string $model, string $column, string $value, string $resetProperty, string $action, string $message): void
    {
        abort_unless(auth()->user()?->can('supplier.manage_brands') || auth()->user()?->can('supplier.edit') || auth()->user()?->can('supplier_bid.add_supplier_response'), 403);

        $value = trim($value);
        if ($value === '' || ! $this->selectedSupplierId) {
            return;
        }

        $slugBase = Str::slug($value) ?: 'catalogo';
        $slug = $slugBase;
        $suffix = 2;

        while ($model::query()
            ->where('slug', $slug)
            ->where($column, '!=', $value)
            ->exists()) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        $catalogItem = $model::firstOrCreate([
            $column => $value,
        ], [
            'slug' => $slug,
            'is_active' => true,
        ]);

        $supplier = Entity::findOrFail($this->selectedSupplierId);
        $method = match($model) {
            Brand::class => 'brands',
            ProductType::class => 'productTypes',
            MachineType::class => 'machineTypes',
            PaymentMethod::class => 'paymentMethods',
        };

        $supplier->$method()->syncWithoutDetaching([$catalogItem->id]);

        ActivityLog::log('compras', $action, $message, [
            'supplier_id' => $this->selectedSupplierId,
            $column => $value,
        ]);

        $this->{$resetProperty} = '';
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'legalName',
            'taxId',
            'country',
            'city',
            'address',
            'phone',
            'email',
            'website',
            'contactName',
            'contactPosition',
            'whatsapp',
            'qualityTypes',
        ]);
        $this->status = 'activo';
        $this->resetValidation();
    }

    private function resetContactForm(): void
    {
        $this->reset([
            'newContactName',
            'newContactPosition',
            'newContactDepartment',
            'newContactEmail',
            'newContactMobile',
            'newContactOfficePhone',
            'newContactWhatsapp',
            'newContactNotes',
        ]);
        $this->newContactIsPrimary = false;
        $this->resetValidation();
    }

    private function syncPrimaryContact(Entity $supplier, EntityContact $contact): void
    {
        $supplier->update([
            'contact_name' => $contact->name,
            'contact_position' => $contact->position,
            'whatsapp' => $contact->whatsapp ?: $contact->mobile,
        ]);
    }
}
