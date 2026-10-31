<?php

namespace App\Livewire\Administracion\Kardex;

use App\Domain\Inventario\Kardex\Queries\ListarMovimientosKardexQuery;
use App\Domain\Inventario\Kardex\Queries\ListarProductosKardexQuery;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class VisorKardex extends Component
{
    use WithPagination;

    public $filterProductId = '';
    public $filterSku = '';
    public $filterType = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 25;

    public function updating($property): void
    {
        if (in_array($property, ['filterProductId', 'filterSku', 'filterType', 'dateFrom', 'dateTo', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.administracion.kardex.visor-kardex', [
            'moves' => app(ListarMovimientosKardexQuery::class)->ejecutar(
                $this->filterProductId,
                $this->filterSku,
                $this->filterType,
                $this->dateFrom,
                $this->dateTo,
                (int) $this->perPage
            ),
            'products' => app(ListarProductosKardexQuery::class)->ejecutar(),
        ]);
    }
}
