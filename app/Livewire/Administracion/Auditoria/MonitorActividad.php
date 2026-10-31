<?php

namespace App\Livewire\Administracion\Auditoria;

use App\Domain\Administracion\Auditoria\Queries\ListarActividadQuery;
use App\Domain\Administracion\Auditoria\Queries\ListarUsuariosAuditoriaQuery;
use App\Domain\Administracion\Auditoria\Queries\ObtenerMetricasAuditoriaQuery;
use App\Domain\Administracion\Auditoria\Queries\UnifiedActivityQuery;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MonitorActividad extends Component
{
    use WithPagination;

    public $filterRole = '';

    public $filterModule = '';

    public $filterUser = '';

    public $filterMoveType = '';

    public $filterSource = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $search = '';

    public $perPage = 50;

    public $modules = [
        '' => 'Todos los módulos',
        'admin' => 'Administración',
        'ventas' => 'Ventas',
        'finanzas' => 'Finanzas',
        'bodega' => 'Bodega / Inventario',
        'logistica' => 'Logística',
        'crm' => 'CRM',
        'operaciones' => 'Operaciones',
        'compras' => 'Compras / Proveedores',
        'inventario' => 'Inventario',
        'warehouse' => 'Recepciones de bodega',
        'order_status' => 'Seguimiento de pedidos',
        'customer_acquisition' => 'Captación de clientes',
        'security' => 'Accesos y solicitudes HTTP',
        'approval' => 'Aprobaciones',
        'kpi' => 'Indicadores',
        'soporte' => 'Soporte',
        'sistema' => 'Sistema / otros registros',
    ];

    public function updating($property): void
    {
        if (in_array($property, ['filterRole', 'filterModule', 'filterUser', 'filterSource', 'filterMoveType', 'dateFrom', 'dateTo', 'search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('admin.activity.view'), 403);
        $modules = $this->modules;
        foreach (app(UnifiedActivityQuery::class)->base()->distinct()->pluck('module') as $module) {
            $modules[$module] ??= $module;
        }
        $logs = app(ListarActividadQuery::class)->ejecutar(
            $this->filterUser,
            $this->filterModule,
            $this->filterRole,
            $this->search,
            (int) $this->perPage,
            $this->dateFrom,
            $this->dateTo,
            $this->filterSource,
            $this->filterMoveType,
        );
        $users = app(ListarUsuariosAuditoriaQuery::class)->ejecutar();
        $metrics = app(ObtenerMetricasAuditoriaQuery::class)->ejecutar();

        return view('livewire.administracion.auditoria.monitor-actividad', [
            'logs' => $logs,
            'modules' => $modules,
            'users' => $users,
            'roles' => User::$roles,
            'todayLogs' => $metrics['todayLogs'],
            'activeToday' => $metrics['activeToday'],
            'totalActions' => $metrics['totalActions'],
            'todayMoves' => $metrics['todayMoves'],
            'todayTickets' => $metrics['todayTickets'],
        ]);
    }
}
