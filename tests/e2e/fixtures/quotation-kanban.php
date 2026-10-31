<?php

use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\Role;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/quotation-workspace.php';
$role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
$user->roles()->syncWithoutDetaching([$role->id]);
$images = ['products-spare-parts.webp', 'services-maintenance.webp', 'catalogs-blueprint.webp'];
$statuses = [QuotationRequest::STATUS_PENDING_BID, QuotationRequest::STATUS_SENT_TO_SUPPLIER, QuotationRequest::STATUS_OFFER_DRAFT, QuotationRequest::STATUS_SENT_TO_CUSTOMER];
foreach ($statuses as $i => $status) {
    $q = QuotationRequest::firstOrCreate(['reference' => 'UX-KANBAN-'.($i + 1)], ['date' => now(), 'quote_to_name' => 'Constructora Norte · Prueba', 'status' => $status, 'user_id' => $user->id, 'request_urgency' => $i === 1 ? 'alta' : 'media']);
    $q->items()->firstOrCreate(['part_number' => 'CAT-'.($i + 1)], ['part_name' => ['Kit de reparación hidráulica CAT 320', 'Motor y componentes de excavadora', 'Filtros para mantenimiento preventivo', 'Repuestos de transmisión'][$i], 'description' => 'Repuesto de prueba visual', 'quantity' => 2, 'unit' => 'PZA', 'status' => 'pending_review']);
    if ($i < 3) {
        $path = 'kanban-ux/cover-'.$i.'.webp';
        Storage::disk('private')->put($path, file_get_contents(public_path('storefront/images/modules/'.$images[$i])));
        $q->forceFill(['evidence_attachment_path' => $path])->save();
    }
}
$demoImages = [
    'modules/products-spare-parts.webp', 'modules/services-maintenance.webp',
    'modules/catalogs-blueprint.webp', 'modules/catalogs-manuals.webp',
    'modules/blog-technical.webp', 'hero-agro/tractor-field.webp',
    'hero-agro/spare-parts.webp', 'hero-agro/service-maintenance.webp',
    'hero-agro/harvest-combine.webp',
];
$titles = ['Bomba hidráulica para excavadora', 'Kit de filtros y lubricación',
    'Rodamientos para transmisión', 'Motor de arranque industrial',
    'Mangueras de alta presión', 'Cuchillas para cosechadora',
    'Repuestos de tractor agrícola', 'Conjunto de sellos y empaques',
    'Servicio y repuestos de motor', 'Engranajes de reducción'];
$customers = ['Constructora Norte', 'Agroservicios del Valle', 'Mina San Rafael', 'Transportes del Pacífico', 'Taller Industrial Central'];
$groups = [
    [QuotationRequest::STATUS_PENDING_BID, 5],
    [QuotationRequest::STATUS_SENT_TO_SUPPLIER, 5],
    [QuotationRequest::STATUS_OFFER_DRAFT, 5],
    [QuotationRequest::STATUS_SENT_TO_CUSTOMER, 5],
    [QuotationRequest::STATUS_APPROVED_TOTAL, 10],
    [QuotationRequest::STATUS_APPROVED_TOTAL, 6],
];
$number = 5;
foreach ($groups as $stage => [$status, $count]) {
    for ($row = 0; $row < $count; $row++, $number++) {
        $image = $demoImages[($number * 7) % count($demoImages)];
        $path = 'kanban-ux/demo-'.$number.'.webp';
        Storage::disk('private')->put($path, file_get_contents(public_path('storefront/images/'.$image)));
        $q = QuotationRequest::updateOrCreate(['reference' => 'UX-KANBAN-'.$number], [
            'date' => now()->subDays($row)->toDateString(),
            'quote_to_name' => $customers[$number % count($customers)].' · Demo',
            'status' => $status, 'user_id' => $user->id,
            'request_urgency' => $number % 4 === 0 ? 'alta' : 'media',
            'evidence_attachment_path' => $path,
        ]);
        for ($part = 1; $part <= 1 + $number % 3; $part++) {
            $q->items()->firstOrCreate(['part_number' => 'DEMO-'.$number.'-'.$part], [
                'part_name' => $titles[($number + $part) % count($titles)],
                'description' => 'Partida ficticia para revisión visual del tablero.',
                'quantity' => $part * 2, 'unit' => 'PZA', 'status' => 'pending_review',
            ]);
        }
        if ($stage === 5) {
            PurchaseOrder::firstOrCreate(['reference_number' => 'UX-OC-'.$number], [
                'supplier_id' => $supplier->id, 'quotation_request_id' => $q->id,
                'status' => 'draft', 'total_fob' => 0, 'currency' => 'USD', 'order_date' => now(),
            ]);
        }
    }
}

echo "Kanban: 40 solicitudes demo; 10 en preparación y 6 en aprobación de OC.\n";
