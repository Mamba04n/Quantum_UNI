<?php

namespace App\Livewire\Finanzas;

use Livewire\Component;
use App\Models\Invoice;
use App\Models\Entity;
use App\Models\AccountReceivable;
use App\Domain\Administracion\Company\Services\CompanyProfileService;
use App\Services\FinanceDocumentService;
use App\Services\FinanceService;
use Livewire\WithFileUploads;

class InvoiceDashboard extends Component
{
    use WithFileUploads;

    // Form fields
    public $showForm = false;
    public $company_issuer = 'quantum';
    public $invoice_number = '';
    public $entity_id = '';
    public $new_entity_name = '';
    public $date = '';
    public $cotizacion_ref = '';
    public $description = '';
    public $amount_nio = '';
    public $retention_amount = 0;
    public $amount_usd = '';
    public $due_date = '';
    public $status = 'Pendiente';
    public $classification = 'Venta de producto';
    public $condition = 'Contado';
    public $invoice_file;

    // Filters
    public $filterCompany = '';
    public $filterStatus = '';
    public $filterClient = '';
    public $search = '';

    public function mount()
    {
        $this->date = date('Y-m-d');
        $this->due_date = date('Y-m-d', strtotime('+30 days'));
        $this->company_issuer = 'quantum';
    }

    public function render(CompanyProfileService $companyService)
    {
        $companies = $companyService->getCompanies();

        $query = Invoice::with('entity')->latest();

        if ($this->filterCompany) {
            $query->where('company_issuer', $this->filterCompany);
        }
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterClient) {
            $query->where('entity_id', $this->filterClient);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entity', fn($e) => $e->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        $invoices = $query->get();

        $totalNio   = $invoices->sum('amount_nio');
        $totalPagado= $invoices->where('status', 'Pagado')->sum('amount_nio');
        $totalPendiente = $invoices->where('status', 'Pendiente')->sum('amount_nio');

        // Estadísticas desglosadas por cada empresa
        $allInvoices = Invoice::all();
        $companyBreakdown = [];
        foreach ($companies as $key => $info) {
            $companyInvoices = $allInvoices->where('company_issuer', $key);
            $totalAmount = (float) $companyInvoices->sum('amount_nio');
            $paidAmount = (float) $companyInvoices->where('status', 'Pagado')->sum('amount_nio');
            $pendingAmount = (float) $companyInvoices->where('status', 'Pendiente')->sum('amount_nio');
            $count = $companyInvoices->count();

            $companyBreakdown[$key] = [
                'name' => $info['name'],
                'short_name' => $info['short_name'] ?? $info['name'],
                'ruc' => $info['ruc'],
                'badge' => $info['badge_light'] ?? 'bg-slate-100 text-slate-800',
                'color' => $info['color_primary'] ?? '#0B2545',
                'total' => $totalAmount,
                'paid' => $paidAmount,
                'pending' => $pendingAmount,
                'count' => $count,
            ];
        }

        $entities = Entity::where('type', 'cliente')->orderBy('name')->get();

        return view('livewire.finanzas.invoice-dashboard', compact(
            'invoices', 'entities', 'companies', 'companyBreakdown', 'totalNio', 'totalPagado', 'totalPendiente'
        ));
    }

    public function saveInvoice(FinanceDocumentService $documentService, FinanceService $financeService)
    {
        abort_unless(auth()->user()?->can('finance.invoice.view'), 403);

        $this->validate([
            'company_issuer' => 'required|in:quantum,powersales,marcos_leiva',
            'invoice_number' => 'required|string|max:120',
            'description' => 'required|string',
            'amount_nio'  => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'invoice_file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,xlsx,docx',
        ]);

        $finalEntityId = $this->entity_id;

        if ($this->entity_id === 'new' && $this->new_entity_name) {
            $entity = Entity::create(['name' => $this->new_entity_name, 'type' => 'cliente']);
            $finalEntityId = $entity->id;
        }

        $path = $documentService->store($this->invoice_file, 'invoices', $this->invoice_number, $finalEntityId ? Entity::find($finalEntityId)?->name : $this->new_entity_name);

        $inv = $financeService->createInvoice([
            'company_issuer'   => $this->company_issuer ?: 'quantum',
            'invoice_number'   => $this->invoice_number ?: null,
            'entity_id'        => $finalEntityId ?: null,
            'date'             => $this->date,
            'cotizacion_ref'   => $this->cotizacion_ref ?: null,
            'description'      => $this->description,
            'amount_nio'       => $this->amount_nio,
            'retention_amount' => $this->retention_amount ?: 0,
            'amount_usd'       => $this->amount_usd ?: null,
            'due_date'         => $this->due_date ?: null,
            'status'           => $this->status,
            'classification'   => $this->classification,
            'condition'        => $this->condition,
            'invoice_file_path' => $path,
            'uploaded_by' => auth()->id(),
        ]);

        // Auto-generate CXC if the invoice is not already Paid
        if ($this->status !== 'Pagado' && $finalEntityId) {
            AccountReceivable::create([
                'entity_id'      => $finalEntityId,
                'invoice_number' => $this->invoice_number ?: null,
                'concept'        => 'Factura ' . ($this->invoice_number ?: ('#'.$inv->id)) . ' — ' . $this->description,
                'classification' => $this->classification,
                'condition'      => $this->condition,
                'invoice_date'   => $this->date,
                'total_amount'   => $this->amount_nio,
                'amount_usd'     => $this->amount_usd ?: null,
                'paid_amount'    => 0,
                'due_date'       => $this->due_date ?: now()->addDays(30),
                'status'         => 'pendiente',
                'support_document_path' => $path,
                'invoice_id' => $inv->id,
                'created_by' => auth()->id(),
            ]);
        }

        $this->reset([
            'invoice_number', 'entity_id', 'new_entity_name', 'cotizacion_ref',
            'description', 'amount_nio', 'retention_amount', 'amount_usd', 'status', 'invoice_file',
        ]);
        $this->date = date('Y-m-d');
        $this->due_date = date('Y-m-d', strtotime('+30 days'));
        $this->company_issuer = 'quantum';
        $this->showForm = false;

        session()->flash('message', 'Factura registrada con éxito.');
    }

    public function updateInvoiceCompany($id, $company)
    {
        abort_unless(auth()->user()?->can('finance.invoice.view'), 403);

        if (in_array($company, ['quantum', 'powersales', 'marcos_leiva'], true)) {
            Invoice::findOrFail($id)->update(['company_issuer' => $company]);
            session()->flash('message', 'Empresa emisora de la factura actualizada.');
        }
    }

    public function markAsPaid($id)
    {
        abort_unless(auth()->user()?->can('finance.payment.register') || auth()->user()?->can('finance.register_payment'), 403);

        Invoice::findOrFail($id)->update(['status' => 'Pagado']);
        session()->flash('message', 'Factura marcada como pagada.');
    }

    public function delete($id)
    {
        abort_unless(auth()->user()?->can('full_access'), 403);

        Invoice::findOrFail($id)->delete();
        session()->flash('message', 'Factura eliminada.');
    }
}
