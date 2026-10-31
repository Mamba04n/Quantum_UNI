<?php

namespace App\Livewire\Administracion\Indicadores;

use App\Exports\KpiDashboardExport;
use App\Services\KpiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
class PanelKpis extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $customerId = '';
    public string $supplierId = '';
    public string $brand = '';
    public string $segment = '';
    public string $sellerId = '';
    public string $technicalCategory = '';

    private bool $viewLogged = false;

    public function mount(KpiService $service): void
    {
        abort_unless(auth()->user()?->can('kpi.view'), 403);

        $service->logDashboardViewed($this->filters());

        if (
            auth()->user()?->can('kpi.view_sales_margin')
            || auth()->user()?->can('kpi.view_inventory_cost')
        ) {
            $service->logSensitiveCostsViewed($this->filters());
        }
    }

    public function updated(): void
    {
        $this->validateFilters();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->customerId = '';
        $this->supplierId = '';
        $this->brand = '';
        $this->segment = '';
        $this->sellerId = '';
        $this->technicalCategory = '';
    }

    public function exportExcel(KpiService $service)
    {
        abort_unless(auth()->user()?->can('kpi.export'), 403);
        $this->validateFilters();
        $service->logExported('xlsx', $this->filters());

        return Excel::download(
            new KpiDashboardExport($this->filters(), auth()->user()),
            'dashboard-kpi-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function exportPdf(KpiService $service)
    {
        abort_unless(auth()->user()?->can('kpi.export'), 403);
        $this->validateFilters();

        $dashboard = $service->getDashboard($this->filters(), auth()->user());
        $service->logExported('pdf', $this->filters());

        $pdf = Pdf::loadView('exports.kpi-summary', [
            'dashboard' => $dashboard,
            'filters' => $this->filters(),
            'user' => auth()->user(),
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'dashboard-kpi-' . now()->format('Ymd-His') . '.pdf'
        );
    }

    public function render(KpiService $service)
    {
        abort_unless(auth()->user()?->can('kpi.view'), 403);
        $this->validateFilters();

        $dashboard = $service->getDashboard($this->filters(), auth()->user());

        return view('livewire.administracion.indicadores.panel-kpis', [
            'dashboard' => $dashboard,
            'options' => $service->filterOptions(),
        ]);
    }

    private function filters(): array
    {
        return [
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
            'customer_id' => $this->customerId ?: null,
            'supplier_id' => $this->supplierId ?: null,
            'brand' => $this->brand ?: null,
            'segment' => $this->segment ?: null,
            'seller_id' => $this->sellerId ?: null,
            'technical_category' => $this->technicalCategory ?: null,
        ];
    }

    private function validateFilters(): void
    {
        $this->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'customerId' => ['nullable', 'integer', Rule::exists('entities', 'id')->where('type', 'cliente')],
            'supplierId' => ['nullable', 'integer', Rule::exists('entities', 'id')->where('type', 'proveedor')],
            'sellerId' => ['nullable', 'integer', 'exists:users,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'segment' => ['nullable', Rule::in(['agricola', 'industrial', 'minero'])],
            'technicalCategory' => ['nullable', 'string', 'max:80'],
        ]);
    }
}
