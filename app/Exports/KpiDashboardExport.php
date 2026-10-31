<?php

namespace App\Exports;

use App\Models\User;
use App\Services\KpiService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class KpiDashboardExport implements FromCollection
{
    public function __construct(
        private readonly array $filters,
        private readonly User $user,
        private readonly KpiService $service = new KpiService()
    ) {
    }

    public function collection(): Collection
    {
        return $this->service->exportRows($this->filters, $this->user);
    }
}
