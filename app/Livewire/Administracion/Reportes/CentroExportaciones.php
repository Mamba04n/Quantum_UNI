<?php

namespace App\Livewire\Administracion\Reportes;

use App\Exports\AdminModuleExport;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
class CentroExportaciones extends Component
{
    public string $module = 'sale_orders';

    public function exportExcel()
    {
        abort_unless(auth()->user()?->can('full_access'), 403);

        return Excel::download(
            new AdminModuleExport($this->module),
            AdminModuleExport::filenameFor($this->module)
        );
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('full_access'), 403);

        return view('livewire.administracion.reportes.exportaciones.centro-exportaciones', [
            'modules' => AdminModuleExport::options(),
        ]);
    }
}
