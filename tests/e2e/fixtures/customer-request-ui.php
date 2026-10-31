<?php

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.mysql.database') !== 'erp_crm_testing') {
    throw new RuntimeException('Requires the isolated erp_crm_testing database.');
}
$user = App\Models\User::firstOrCreate(['email' => 'ux-request@quantum.test'], [
    'name' => 'Solicitud UX', 'password' => Illuminate\Support\Facades\Hash::make('local-ux-review'),
    'role' => 'admin', 'is_active' => true, 'email_verified_at' => now(),
]);
foreach (['full_access', 'customer_request.create', 'customer_request.view', 'admin.users.manage'] as $slug) {
    $permission = App\Models\Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'commercial']);
    $user->permissions()->syncWithoutDetaching([$permission->id]);
}
$client = App\Models\Entity::firstOrCreate(['name' => 'Cliente UX solicitud', 'type' => 'cliente'], [
    'status' => 'activo', 'email' => 'cliente@ux.test', 'contact_name' => 'Contacto UX', 'rfc_ruc' => 'UX-123',
]);
App\Models\ClientMachine::firstOrCreate(['entity_id' => $client->id, 'name' => 'Excavadora UX'], [
    'machine_type' => 'Excavadora', 'brand' => 'CAT', 'model' => '320', 'serial_number' => 'UX-320', 'status' => 'active',
]);
App\Models\Product::firstOrCreate(['part_number' => 'UX-FILTRO'], [
    'name' => 'Filtro UX', 'part_number' => 'UX-FILTRO', 'brand' => 'CAT', 'active' => true,
]);
echo "Customer request UI fixtures ready.\n";
