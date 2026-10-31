<?php

namespace App\Livewire\Ventas\Cotizaciones\SolicitudesCliente;

use App\Models\ActivityLog;
use App\Models\ClientMachine;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Services\CustomerService;
use App\Services\DocumentSequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class FormularioSolicitudCliente extends Component
{
    use WithFileUploads;

    public string $date = '';

    public string $reference = '';

    public string $submissionToken = '';

    public string $selectedClientId = '';

    public string $clientSearch = '';

    public string $clientMachineId = '';

    public string $quoteToName = '';

    public string $quoteToEmail = '';

    public string $quoteToRuc = '';

    public string $customerContactName = '';

    public string $sourceChannel = 'whatsapp';

    public string $machinery = '';

    public string $machineBrand = '';

    public string $machineModel = '';

    public string $equipmentSerial = '';

    public array $items = [];

    public string $requestUrgency = 'media';

    public string $description = '';

    public $evidenceAttachment;

    public array $urgencyLabels = [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    public array $sourceLabels = [
        'whatsapp' => 'WhatsApp',
        'gmail' => 'Gmail',
        'llamada' => 'Llamada',
        'presencial' => 'Presencial',
        'otro' => 'Otro',
    ];

    public bool $showBulkModal = false;

    public string $bulkPasteText = '';

    public array $bulkPreviewItems = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->submissionToken = (string) Str::uuid();
        $this->reference = $this->referencePreview();
        $this->items = [$this->blankItem()];
    }

    public function addItem(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public string $bulkMode = 'file';

    public $bulkExcelFile = null;

    public function openBulkModal(): void
    {
        $this->bulkMode = 'file';
        $this->bulkExcelFile = null;
        $this->bulkPasteText = '';
        $this->bulkPreviewItems = [];
        $this->showBulkModal = true;
    }

    public function closeBulkModal(): void
    {
        $this->showBulkModal = false;
        $this->bulkExcelFile = null;
        $this->bulkPasteText = '';
        $this->bulkPreviewItems = [];
        $this->bulkMode = 'file';
    }

    public function setBulkMode(string $mode): void
    {
        $this->bulkMode = in_array($mode, ['file', 'text'], true) ? $mode : 'file';
    }

    public function updatedBulkExcelFile(): void
    {
        if (! $this->bulkExcelFile) {
            return;
        }

        $this->validate([
            'bulkExcelFile' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
        ], [
            'bulkExcelFile.mimes' => 'El archivo debe ser un formato válido de Excel (.xlsx, .xls) o CSV (.csv).',
            'bulkExcelFile.max' => 'El archivo no puede exceder los 10MB.',
        ]);

        $parser = app(\App\Domain\Ventas\Cotizaciones\Services\BulkItemParserService::class);
        $this->bulkPreviewItems = $parser->parseUploadedFile($this->bulkExcelFile);

        $count = count($this->bulkPreviewItems);
        if ($count > 0) {
            $this->dispatch('notify', ['type' => 'success', 'message' => "Se detectaron {$count} partida(s) en el archivo Excel."]);
        } else {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'El archivo no contiene partidas válidas. Revisa su contenido.']);
        }
    }

    public function clearBulkFile(): void
    {
        $this->bulkExcelFile = null;
        $this->bulkPreviewItems = [];
    }

    public function updatedBulkPasteText(string $value): void
    {
        $parser = app(\App\Domain\Ventas\Cotizaciones\Services\BulkItemParserService::class);
        $this->bulkPreviewItems = $parser->parse($value);
    }

    public function applyBulkItems(\App\Domain\Ventas\Cotizaciones\Services\BulkItemParserService $parser): void
    {
        $itemsToApply = $this->bulkPreviewItems;

        if (empty($itemsToApply) && filled($this->bulkPasteText)) {
            $itemsToApply = $parser->parse($this->bulkPasteText);
        }

        if (empty($itemsToApply)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay partidas válidas para agregar. Revisa el archivo.']);
            return;
        }

        // Si la lista actual solo tiene una fila y está vacía, reemplazarla
        if (count($this->items) === 1 && blank($this->items[0]['part_name']) && blank($this->items[0]['part_code'])) {
            $this->items = [];
        }

        foreach ($itemsToApply as $item) {
            $this->items[] = [
                'part_name' => $item['part_name'],
                'part_code' => $item['part_code'],
                'part_brand' => $item['part_brand'] ?? '',
                'part_model' => $item['part_model'] ?? '',
                'part_serial_number' => $item['part_serial_number'] ?? '',
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?: 'PZA',
                'product_id' => $item['product_id'] ?? null,
            ];
        }

        $count = count($itemsToApply);
        $this->closeBulkModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => "Se agregaron {$count} partida(s) desde la carga masiva."]);
    }

    private function activeClientsQuery()
    {
        return app(CustomerService::class)->activeCustomerQuery();
    }

    public function updatedSelectedClientId(?string $clientId): void
    {
        if (! $clientId) {
            $this->quoteToName = '';
            $this->quoteToEmail = '';
            $this->quoteToRuc = '';
            $this->customerContactName = '';
            $this->clientMachineId = '';
            $this->clearMachineFields();

            return;
        }

        $this->clientMachineId = '';
        $this->clearMachineFields();
        $client = $this->activeClientsQuery()->find($clientId);
        if ($client) {
            $this->quoteToName = $client->name;
            $this->quoteToEmail = $client->email ?? $client->contact_info ?? '';
            $this->quoteToRuc = $client->rfc_ruc ?? '';
            $this->customerContactName = $client->contact_name ?? '';
        }

        $machines = ClientMachine::where('entity_id', $clientId)->orderBy('name')->get();
        if ($machines->count() === 1) {
            $this->clientMachineId = (string) $machines->first()->id;
            $this->fillMachineFields($machines->first());
        }
    }

    public function selectClientOption(?int $clientId = null): void
    {
        if (! $clientId) {
            $this->selectedClientId = '';
            $this->clientSearch = '';
            $this->updatedSelectedClientId(null);
            return;
        }

        $this->selectedClientId = (string) $clientId;
        $client = $this->activeClientsQuery()->find($clientId);
        $this->clientSearch = $client?->name ?? '';
        $this->updatedSelectedClientId((string) $clientId);
    }

    public function clearClientSearch(): void
    {
        $this->clientSearch = '';
        $this->selectedClientId = '';
        $this->updatedSelectedClientId(null);
    }

    public function updatedClientMachineId(?string $machineId): void
    {
        if (! $machineId) {
            $this->clearMachineFields();

            return;
        }

        $machine = ClientMachine::query()
            ->when($this->selectedClientId, fn ($query) => $query->where('entity_id', $this->selectedClientId))
            ->find($machineId);
        if (! $machine) {
            $this->clientMachineId = '';
            $this->clearMachineFields();

            return;
        }

        $this->fillMachineFields($machine);
    }

    public function updatedItems(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.product_id')) {
            return;
        }

        $index = (int) str($key)->before('.')->toString();
        $product = Product::find((int) $value);

        if (! $product || ! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['part_name'] = $product->name ?? $this->items[$index]['part_name'];
        $this->items[$index]['part_code'] = $product->part_number ?: ($product->sku ?: $this->items[$index]['part_code']);
        $this->items[$index]['part_brand'] = $product->brand ?? $this->items[$index]['part_brand'];
    }

    public function save(): mixed
    {
        if (! Str::isUuid($this->submissionToken)) {
            $this->submissionToken = (string) Str::uuid();
        }

        $allowE2eEvidenceFallback = app()->environment('local')
            && request()->headers->get('X-E2E-Test') === '1';

        try {
            $this->validate([
                'date' => ['required', 'date'],
                'quoteToName' => ['required', 'string', 'max:255'],
                'quoteToEmail' => ['nullable', 'not_regex:/[\r\n]/', 'email', 'max:255'],
                'quoteToRuc' => ['nullable', 'string', 'max:50'],
                'customerContactName' => ['nullable', 'string', 'max:255'],
                'sourceChannel' => ['required', 'in:'.implode(',', array_keys($this->sourceLabels))],
                'clientMachineId' => [
                    'nullable',
                    Rule::exists('client_machines', 'id')->where(fn ($query) => $query->where('entity_id', $this->selectedClientId)),
                ],
                'machinery' => ['required', 'string', 'max:255'],
                'machineBrand' => ['nullable', 'string', 'max:255'],
                'machineModel' => ['nullable', 'string', 'max:255'],
                'equipmentSerial' => ['nullable', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
                'items.*.part_name' => ['required', 'string', 'max:255'],
                'items.*.part_code' => ['required', 'string', 'max:255'],
                'items.*.part_brand' => ['nullable', 'string', 'max:255'],
                'items.*.part_model' => ['nullable', 'string', 'max:255'],
                'items.*.part_serial_number' => ['nullable', 'string', 'max:255'],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
                'items.*.unit' => ['required', 'string', 'max:30'],
                'requestUrgency' => ['required', 'in:'.implode(',', array_keys($this->urgencyLabels))],
                'description' => ['required', 'string', 'min:5', 'max:5000'],
                'evidenceAttachment' => [$allowE2eEvidenceFallback ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:5120'],
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Completa los campos obligatorios.',
            ]);
            throw $e;
        }

        $existing = QuotationRequest::where('submission_token', $this->submissionToken)->first();
        if ($existing) {
            session()->flash('message', "Solicitud {$existing->reference} ya estaba registrada.");

            return redirect()->route('sales.quotations.phase');
        }

        $attachmentPath = $this->evidenceAttachment
            ? $this->evidenceAttachment->store('customer-requests', 'private')
            : $this->createE2eEvidencePlaceholder();

        $items = collect($this->items)->map(fn (array $item) => [
            'part_name' => trim((string) $item['part_name']),
            'part_code' => trim((string) $item['part_code']),
            'part_brand' => trim((string) ($item['part_brand'] ?? '')),
            'part_model' => trim((string) ($item['part_model'] ?? '')),
            'part_serial_number' => trim((string) ($item['part_serial_number'] ?? '')),
            'product_id' => filled($item['product_id'] ?? null) ? (int) $item['product_id'] : null,
            'quantity' => (int) $item['quantity'],
            'unit' => trim((string) $item['unit']),
        ])->values();

        $firstItem = $items->first();

        $wasCreated = false;

        try {
            $quotation = DB::transaction(function () use ($attachmentPath, $items, $firstItem, &$wasCreated) {
                $existing = QuotationRequest::where('submission_token', $this->submissionToken)->first();
                if ($existing) {
                    return $existing;
                }

                $reference = $this->reserveReference();
                $this->reference = $reference;

                $quotation = QuotationRequest::create([
                    'reference' => $reference,
                    'submission_token' => $this->submissionToken,
                    'date' => $this->date,
                    'quote_to_name' => $this->quoteToName,
                    'quote_to_email' => $this->quoteToEmail ?: null,
                    'quote_to_ruc' => trim($this->quoteToRuc) ?: null,
                    'customer_contact_name' => trim($this->customerContactName) ?: null,
                    'requested_by' => auth()->user()?->name,
                    'department' => 'ventas',
                    'source_channel' => $this->sourceChannel,
                    'evidence_attachment_path' => $attachmentPath,
                    'machinery' => $this->machinery,
                    'machine_brand' => $this->machineBrand ?: null,
                    'machine_model' => $this->machineModel ?: null,
                    'equipment_serial' => $this->equipmentSerial ?: null,
                    'part_code' => $firstItem['part_code'],
                    'request_quantity' => $items->sum('quantity'),
                    'request_urgency' => $this->requestUrgency,
                    'subtotal' => 0,
                    'total_amount' => 0,
                    'status' => QuotationRequest::STATUS_RECEIVED,
                    'notes' => $this->description,
                    'user_id' => auth()->id(),
                    'client_machine_id' => $this->clientMachineId ?: null,
                ]);

                foreach ($items as $item) {
                    $product = $item['product_id']
                        ? Product::find($item['product_id'])
                        : Product::query()
                            ->where('sku', $item['part_code'])
                            ->orWhere('part_number', $item['part_code'])
                            ->first();

                    QuotationRequestItem::create([
                        'quotation_request_id' => $quotation->id,
                        'product_id' => $product?->id,
                        'client_machine_id' => $this->clientMachineId ?: null,
                        'part_name' => $item['part_name'],
                        'part_number' => $item['part_code'],
                        'description' => $item['part_name'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'],
                        'notes' => null,
                        'unit_price' => 0,
                        'line_total' => 0,
                        'internal_request_ref' => $reference,
                        'brand' => $item['part_brand'] ?: null,
                        'model' => $item['part_model'] ?: null,
                        'serial_number' => $item['part_serial_number'] ?: null,
                        'machine_brand' => $this->machineBrand ?: null,
                        'machine_model' => $this->machineModel ?: null,
                        'image_path' => $attachmentPath,
                        'status' => 'pending_review',
                    ]);
                }

                $wasCreated = true;

                return $quotation;
            }, 3);
        } catch (QueryException $exception) {
            $quotation = QuotationRequest::where('submission_token', $this->submissionToken)->first();

            if (! $quotation) {
                Storage::disk('private')->delete($attachmentPath);
                throw $exception;
            }
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($attachmentPath);
            throw $exception;
        }

        if ($wasCreated) {
            ActivityLog::log('ventas', 'customer_request_created', "Solicitud de cliente {$quotation->reference} capturada desde {$this->sourceLabels[$this->sourceChannel]}", [
                'quotation_request_id' => $quotation->id,
                'source_channel' => $this->sourceChannel,
                'evidence_attachment_path' => $attachmentPath,
                'items_count' => $items->count(),
            ]);
        } else {
            Storage::disk('private')->delete($attachmentPath);
        }

        session()->flash('message', $wasCreated
            ? "Solicitud {$quotation->reference} registrada."
            : "Solicitud {$quotation->reference} ya estaba registrada.");

        return redirect()->route('sales.quotations.phase');
    }

    private function reserveReference(): string
    {
        return app(DocumentSequenceService::class)->formattedNext(
            'customer_request',
            'SC-',
            5,
            1
        );
    }

    private function referencePreview(): string
    {
        return app(DocumentSequenceService::class)->formattedPeek(
            'customer_request',
            'SC-',
            5,
            1
        );
    }

    private function blankItem(): array
    {
        return [
            'part_name' => '',
            'part_code' => '',
            'part_brand' => '',
            'part_model' => '',
            'part_serial_number' => '',
            'product_id' => '',
            'quantity' => 1,
            'unit' => 'unidad',
        ];
    }

    private function fillMachineFields(ClientMachine $machine): void
    {
        $this->machinery = $machine->machine_type ?: ($machine->name ?? '');
        $this->machineBrand = $machine->brand ?? '';
        $this->machineModel = $machine->model ?? '';
        $this->equipmentSerial = $machine->serial_number ?? '';
    }

    private function clearMachineFields(): void
    {
        $this->machinery = '';
        $this->machineBrand = '';
        $this->machineModel = '';
        $this->equipmentSerial = '';
    }

    private function createE2eEvidencePlaceholder(): string
    {
        $path = 'customer-requests/e2e-evidence-'.now()->format('YmdHis').'.txt';
        Storage::disk('private')->put($path, 'E2E evidence placeholder');

        return $path;
    }

    public function render()
    {
        $search = trim($this->clientSearch);

        $clientsQuery = $this->activeClientsQuery();
        if ($search !== '') {
            $clientsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('rfc_ruc', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
            if ($this->selectedClientId) {
                $clientsQuery->orWhere('id', (int) $this->selectedClientId);
            }
        }

        return view('livewire.ventas.cotizaciones.solicitudes-cliente.formulario-solicitud-cliente', [
            'clients' => $clientsQuery->get(),
            'clientMachines' => $this->selectedClientId
                ? ClientMachine::where('entity_id', $this->selectedClientId)->orderBy('name')->get()
                : collect(),
            'products' => Product::query()
                ->where('active', true)
                ->orderBy('name')
                ->limit(300)
                ->get(),
        ]);
    }
}
