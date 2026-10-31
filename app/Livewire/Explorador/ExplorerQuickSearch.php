<?php

namespace App\Livewire\Explorador;

use App\Models\Entity;
use App\Models\Invoice;
use App\Models\OrderTracker;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\StockMove;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class ExplorerQuickSearch extends Component
{
    public string $search = '';

    public bool $open = false;

    public ?string $area = null;

    public string $scope = 'area';

    public function mount(?string $area = null): void
    {
        $this->area = $area;
        $this->search = request()->routeIs('explorer.*') ? (string) request('q', '') : '';
    }

    public function updatedSearch(): void
    {
        $this->open = mb_strlen(trim($this->search)) >= 2;
    }

    public function showSuggestions(): void
    {
        $this->open = mb_strlen(trim($this->search)) >= 2;
    }

    public function hideSuggestions(): void
    {
        $this->open = false;
    }

    public function toggleScope(): void
    {
        $this->scope = $this->scope === 'area' ? 'all' : 'area';
        if (mb_strlen(trim($this->search)) >= 2) {
            $this->open = true;
        }
    }

    public function submitSearch()
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2) {
            return null;
        }

        $params = ['q' => $term];
        if ($this->area && $this->scope === 'area') {
            $params['area'] = $this->area;
        }

        return $this->redirectRoute('explorer.index', $params, navigate: true);
    }

    public function getAreaTitleProperty(): ?string
    {
        if (! $this->area) {
            return null;
        }
        $configs = config('workspaces');

        return $configs[$this->area]['title'] ?? ucfirst($this->area);
    }

    public function render()
    {
        $navigationResults = [];
        if ($this->open && auth()->user()) {
            $term = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim($this->search)));
            $allAreas = app(\App\Support\WorkspaceNavigation::class)->areas(auth()->user());

            // If scope is area and area is set, prioritize or filter matching items in this area
            if ($this->area && $this->scope === 'area' && isset($allAreas[$this->area])) {
                $curArea = $allAreas[$this->area];
                foreach ($curArea['items'] as $destination => [$label, $description]) {
                    if (str_contains(\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($label.' '.$curArea['title'])), $term)) {
                        $navigationResults[$destination] = ['label' => $label, 'area' => $curArea['title']];
                    }
                }
            } else {
                foreach ($allAreas as $area) {
                    foreach ($area['items'] as $destination => [$label, $description]) {
                        if (str_contains(\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($label.' '.$area['title'])), $term)) {
                            $navigationResults[$destination] = ['label' => $label, 'area' => $area['title']];
                        }
                    }
                }
            }
        }

        return view('livewire.explorador.explorer-quick-search', [
            'navigationResults' => array_slice($navigationResults, 0, 4, true),
            'suggestions' => $this->open ? $this->suggestions() : collect(),
            'areaTitle' => $this->areaTitle,
        ]);
    }

    private function suggestions(): Collection
    {
        $term = trim($this->search);
        $user = auth()->user();
        $results = collect();

        if (mb_strlen($term) < 2) {
            return $results;
        }

        $isArea = $this->area && $this->scope === 'area';

        if ((! $isArea || in_array($this->area, ['ventas', 'clientes', 'finanzas'])) &&
            ($user?->hasAnyPermission(['customer_request.view', 'order.view', 'direct_order.view', 'tracker.view']) || $user?->canAccess('finance'))) {
            $results->push(...Entity::query()
                ->where('type', 'cliente')
                ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('legal_name', 'like', "%{$term}%")
                    ->orWhere('rfc_ruc', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%"))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('customer', $row->id, 'Cliente', $row->rfc_ruc ?: 'CL-'.$row->id, $row->name)));
        }

        if ((! $isArea || in_array($this->area, ['ventas', 'compras'])) &&
            $user?->hasAnyPermission(['customer_request.view', 'supplier_bid.view', 'client_offer.view', 'order_approval.view'])) {
            $results->push(...QuotationRequest::query()
                ->where(fn (Builder $query) => $query->where('reference', 'like', "%{$term}%")->orWhere('quote_to_name', 'like', "%{$term}%"))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('quotation', $row->id, 'Solicitud / Cotización', $row->reference, $row->quote_to_name)));
        }

        if ((! $isArea || $this->area === 'ventas') &&
            $user?->hasAnyPermission(['order.view', 'direct_order.view', 'tracker.view'])) {
            $results->push(...SaleOrder::query()
                ->where(fn (Builder $query) => $query->where('reference', 'like', "%{$term}%")->orWhere('client_name', 'like', "%{$term}%"))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('sale_order', $row->id, 'Orden de venta', $row->reference, $row->client_name)));
        }

        if ((! $isArea || in_array($this->area, ['compras', 'bodega', 'proveedores'])) &&
            $user?->canAccess('purchases')) {
            $results->push(...PurchaseOrder::query()->with('supplier:id,name')
                ->where(fn (Builder $query) => $query->where('reference_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$term}%")))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('purchase_order', $row->id, 'Orden de compra', $row->reference_number, $row->supplier?->name)));
        }

        if ((! $isArea || in_array($this->area, ['operaciones', 'ventas'])) &&
            $user?->can('tracker.view')) {
            $results->push(...OrderTracker::query()
                ->where(fn (Builder $query) => $query->where('operational_tracking_code', 'like', "%{$term}%")
                    ->orWhere('main_reference', 'like', "%{$term}%")
                    ->orWhereHas('references', fn (Builder $references) => $references->where('reference_code', 'like', "%{$term}%")))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('tracker', $row->id, 'Seguimiento', $row->tracking_code ?: $row->main_reference, $row->operational_phase)));
        }

        if ((! $isArea || $this->area === 'bodega') &&
            $user?->canAccess('inventory')) {
            $results->push(...Product::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('part_number', 'like', "%{$term}%"))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('product', $row->id, 'Producto', $row->part_number ?: $row->sku, $row->name)));

            $results->push(...StockMove::query()->with('product:id,name')
                ->where(fn (Builder $query) => $query->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', "%{$term}%")))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('stock_move', $row->id, 'Movimiento Kardex', $row->reference ?: '#'.$row->id, $row->product?->name)));
        }

        if ((! $isArea || $this->area === 'finanzas') &&
            $user?->canAccess('finance')) {
            $results->push(...Invoice::query()->with('entity:id,name')
                ->where(fn (Builder $query) => $query->where('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('entity', fn (Builder $entity) => $entity->where('name', 'like', "%{$term}%")))
                ->latest()->limit(4)->get()->map(fn ($row) => $this->row('invoice', $row->id, 'Factura', $row->invoice_number, $row->entity?->name)));
        }

        return $results->take(8)->values();
    }

    private function row(string $type, int $id, string $category, ?string $reference, ?string $description): array
    {
        return compact('type', 'id', 'category', 'reference', 'description');
    }
}
