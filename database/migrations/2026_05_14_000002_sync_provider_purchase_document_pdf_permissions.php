<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_order_documents') && ! Schema::hasColumn('purchase_order_documents', 'foreign_order_id')) {
            Schema::table('purchase_order_documents', function (Blueprint $table) {
                if (Schema::hasTable('foreign_orders')) {
                    $table->foreignId('foreign_order_id')->nullable()->after('purchase_order_id')->constrained('foreign_orders')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('foreign_order_id')->nullable()->after('purchase_order_id')->index();
                }
            });
        }

        $permissions = [
            'supplier_rfq.generate_pdf' => 'PDF Comerciales',
            'client_offer.generate_pdf' => 'PDF Comerciales',
            'purchase_order_provider.view' => 'OC Proveedor PDF',
            'purchase_order_provider.edit' => 'OC Proveedor PDF',
            'purchase_order_provider.generate_pdf' => 'OC Proveedor PDF',
            'purchase_order_provider.regenerate_pdf' => 'OC Proveedor PDF',
            'purchase_order_provider.download_pdf' => 'OC Proveedor PDF',
            'purchase_order_provider.view_costs' => 'OC Proveedor PDF',
            'order_tracker.attach_documents' => 'Order Tracker',
        ];

        $ids = collect($permissions)->map(fn (string $module, string $slug) => Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('.', ' ')->headline()->toString(), 'module' => $module]
        )->id);

        Role::whereIn('slug', ['admin', 'administrador'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));

        $purchaseIds = Permission::whereIn('slug', [
            'purchase_order_provider.view',
            'purchase_order_provider.edit',
            'purchase_order_provider.generate_pdf',
            'purchase_order_provider.regenerate_pdf',
            'purchase_order_provider.download_pdf',
            'supplier_rfq.generate_pdf',
            'order_tracker.attach_documents',
        ])->pluck('id');
        Role::whereIn('slug', ['compras', 'operaciones'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($purchaseIds));

        $financeIds = Permission::whereIn('slug', [
            'purchase_order_provider.view',
            'purchase_order_provider.download_pdf',
            'purchase_order_provider.view_costs',
        ])->pluck('id');
        Role::whereIn('slug', ['agente_finanzas', 'finanzas'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($financeIds));

        $salesIds = Permission::whereIn('slug', [
            'client_offer.generate_pdf',
            'supplier_rfq.generate_pdf',
        ])->pluck('id');
        Role::whereIn('slug', ['ventas'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($salesIds));
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_order_documents') && Schema::hasColumn('purchase_order_documents', 'foreign_order_id')) {
            Schema::table('purchase_order_documents', function (Blueprint $table) {
                $table->dropColumn('foreign_order_id');
            });
        }
    }
};
