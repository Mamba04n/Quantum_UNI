<?php

// Browser fixtures only. Refuse to touch the development or production database.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.mysql.database') !== 'erp_crm_testing') {
    throw new RuntimeException('These fixtures require the isolated erp_crm_testing database.');
}
$user = App\Models\User::firstOrCreate(['email' => 'ux-quotation@quantum.test'], [
    'name' => 'Revisión UX', 'password' => Illuminate\Support\Facades\Hash::make(getenv('E2E_PASSWORD') ?: 'local-ux-review'),
    'role' => 'admin', 'is_active' => true, 'email_verified_at' => now(),
]);
$permission = App\Models\Permission::firstOrCreate(['slug' => 'full_access'], ['name' => 'Acceso total', 'module' => 'system']);
$user->permissions()->syncWithoutDetaching([$permission->id]);
foreach (['supplier_bid.view', 'supplier_bid.add_supplier_response', 'supplier_bid.edit_supplier_response', 'supplier_bid.create', 'customer_request.view', 'client_offer.view', 'tracker.attach_files'] as $slug) {
    $permission = App\Models\Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'commercial']);
    $user->permissions()->syncWithoutDetaching([$permission->id]);
}
$supplier = App\Models\Entity::firstOrCreate(['name' => 'Proveedor de prueba UX', 'type' => 'proveedor'], ['status' => 'activo']);
foreach ([5, 20, 60] as $count) {
    $quote = App\Models\QuotationRequest::firstOrCreate(['reference' => 'UX-COT-'.$count], [
        'submission_token' => (string) Illuminate\Support\Str::uuid(), 'date' => now()->toDateString(),
        'quote_to_name' => 'Cliente de prueba UX', 'requested_by' => $user->name, 'department' => 'ventas',
        'source_channel' => 'whatsapp', 'machinery' => 'Equipo de prueba', 'part_code' => 'UX-001',
        'request_quantity' => $count, 'request_urgency' => 'media', 'subtotal' => 0, 'total_amount' => 0,
        'status' => App\Models\QuotationRequest::STATUS_SENT_TO_SUPPLIER, 'user_id' => $user->id,
    ]);
    foreach (range(1, $count) as $index) {
        $quote->items()->firstOrCreate(['part_number' => 'UX-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)], [
            'part_name' => 'Filtro industrial '.$index, 'description' => 'Filtro industrial '.$index,
            'quantity' => 2, 'unit' => 'PZA', 'status' => 'pending_review',
        ]);
    }
    App\Models\SupplierRfqDocument::firstOrCreate(['quotation_request_id' => $quote->id, 'supplier_id' => $supplier->id], [
        'rfq_number' => 'UX-RFQ-'.$count, 'status' => App\Models\SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
        'selected_item_ids' => $quote->items()->pluck('id')->all(),
    ]);
}
echo "Fixtures UX: 5, 20 y 60 partidas disponibles en la base de pruebas.\n";
