<?php

namespace App\Livewire\Compras;

use App\Models\Entity;
use App\Models\SupplierPriceItem;
use App\Models\SupplierPriceQuotation;
use App\Services\SupplierPriceBookService;
use App\Services\SupplierPriceImport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SupplierPriceBook extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $supplierFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showForm = false;

    public array $draft = [];

    public $attachment;

    public $importFile;

    public string $paste = '';

    #[Locked]
    public string $token = '';

    #[Locked]
    public ?int $detailId = null;

    #[Locked]
    public ?int $checkingId = null;

    #[Locked]
    public int $checkingVersion = 0;

    public string $availability = 'pending';

    public string $availabilityNotes = '';

    public string $notice = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', SupplierPriceQuotation::class);
    }

    public function updated($name): void
    {
        if (in_array($name, ['search', 'supplierFilter', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        Gate::authorize('create', SupplierPriceQuotation::class);
        $this->resetValidation();
        $this->reset('attachment', 'importFile', 'paste', 'notice', 'detailId', 'checkingId');
        $this->token = (string) Str::uuid();
        $this->draft = ['supplier_id' => '', 'reference' => '', 'quoted_at' => now()->toDateString(), 'valid_until' => null, 'notes' => '', 'items' => [$this->blankItem()]];
        $this->showForm = true;
    }

    private function blankItem(): array
    {
        return ['part_number' => '', 'description' => '', 'quantity' => '1', 'unit' => 'Unidad', 'unit_price' => ''];
    }

    public function addItem(): void
    {
        Gate::authorize('create', SupplierPriceQuotation::class);
        if (count($this->draft['items'] ?? []) < 500) {
            $this->draft['items'][] = $this->blankItem();
        }
    }

    public function removeItem(int $index): void
    {
        Gate::authorize('create', SupplierPriceQuotation::class);
        unset($this->draft['items'][$index]);
        $this->draft['items'] = array_values($this->draft['items']);
    }

    public function import(SupplierPriceImport $parser): void
    {
        Gate::authorize('create', SupplierPriceQuotation::class);
        $this->resetValidation();
        if ($this->importFile) {
            $this->validate(['importFile' => ['file', 'max:10240', 'mimes:xlsx,xls,csv,txt']]);
            try {
                $rows = in_array(strtolower($this->importFile->getClientOriginalExtension()), ['txt', 'csv'], true)
                    ? $parser->text(file_get_contents($this->importFile->getRealPath()))
                    : $parser->spreadsheet($this->importFile->getRealPath());
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                report($e);
                $this->addError('importFile', 'No se pudo leer el archivo. Utiliza la plantilla o pega los valores de las columnas.');

                return;
            }
        } else {
            $this->validate(['paste' => ['required', 'string', 'max:1000000']]);
            $rows = $parser->text($this->paste);
        }
        // Import is a draft preview, not a save and never an automatic stock confirmation.
        $this->draft['items'] = $rows;
        $this->notice = 'Se cargaron '.count($rows).' partidas. Revisa los datos y guarda la cotización.';
    }

    public function save(SupplierPriceBookService $service): void
    {
        Gate::authorize('create', SupplierPriceQuotation::class);
        try {
            $service->record($this->draft, $this->token, $this->attachment);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $field) => [$field === 'attachment' ? $field : 'draft.'.$field => $messages])->all());
        }
        $this->back();
        $this->notice = 'Cotización registrada. La disponibilidad queda por confirmar.';
        $this->resetPage();
    }

    public function detail(int $id): void
    {
        SupplierPriceQuotation::findOrFail($id);
        $this->detailId = $id;
        $this->checkingId = null;
    }

    public function check(int $id): void
    {
        Gate::authorize('confirmAvailability', SupplierPriceQuotation::class);
        $item = SupplierPriceItem::findOrFail($id);
        $this->detailId = $item->supplier_price_quotation_id;
        $this->checkingId = $item->id;
        $this->checkingVersion = $item->availability_version;
        $this->availability = $item->availability;
        $this->availabilityNotes = $item->availability_notes ?? '';
        $this->resetValidation();
    }

    public function confirm(SupplierPriceBookService $service): void
    {
        abort_unless($this->checkingId, 404);
        $service->confirm($this->checkingId, $this->availability, $this->availabilityNotes, $this->checkingVersion);
        $this->checkingId = null;
        $this->notice = 'Consulta de disponibilidad registrada. El precio histórico se conserva.';
    }

    public function cancelCheck(): void
    {
        $this->checkingId = null;
        $this->resetValidation();
    }

    public function back(): void
    {
        $this->reset('showForm', 'detailId', 'checkingId', 'attachment', 'importFile', 'paste');
        $this->resetValidation();
    }

    public function render()
    {
        $this->validate([
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['string', 'max:150'],
        ]);
        $items = SupplierPriceItem::query()->with('quotation.supplier')
            ->whereHas('quotation', fn ($q) => $q
                ->when($this->supplierFilter !== '', fn ($q) => $q->where('supplier_id', $this->supplierFilter))
                ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('quoted_at', '>=', $this->dateFrom))
                ->when($this->dateTo !== '', fn ($q) => $q->whereDate('quoted_at', '<=', $this->dateTo)))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('part_number', 'like', '%'.trim($this->search).'%')
                ->orWhere('description', 'like', '%'.trim($this->search).'%')))
            ->orderByDesc(SupplierPriceQuotation::select('quoted_at')->whereColumn('supplier_price_quotations.id', 'supplier_price_items.supplier_price_quotation_id')->limit(1))
            ->orderByDesc('id')->paginate(20);

        return view('livewire.compras.supplier-price-book', [
            'items' => $items,
            'suppliers' => Entity::where('type', 'proveedor')->orderBy('name')->get(['id', 'name']),
            'detail' => $this->detailId ? SupplierPriceQuotation::with(['supplier', 'items'])->findOrFail($this->detailId) : null,
        ]);
    }
}
