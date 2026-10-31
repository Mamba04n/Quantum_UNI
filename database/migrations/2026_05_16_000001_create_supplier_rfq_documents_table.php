<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_rfq_documents')) {
            Schema::create('supplier_rfq_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
                $table->foreignId('supplier_bid_id')->nullable()->constrained('supplier_bids')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->string('quote_to_name')->nullable();
                $table->string('consignee_name')->nullable();
                $table->text('consignee_comment')->nullable();
                $table->text('consignee_urgent_comment')->nullable();
                $table->string('consignee_address_1')->nullable();
                $table->string('consignee_address_2')->nullable();
                $table->string('consignee_country')->nullable();
                $table->string('consignee_city')->nullable();
                $table->string('consignee_phone')->nullable();
                $table->text('consignee_emails')->nullable();
                $table->string('status')->default('draft');
                $table->foreignId('admin_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('admin_reviewed_at')->nullable();
                $table->text('admin_review_notes')->nullable();
                $table->string('pdf_path')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->unique(['quotation_request_id', 'supplier_id'], 'supplier_rfq_unique_quote_supplier');
                $table->index(['status', 'created_at'], 'supplier_rfq_status_created_idx');
            });
        }

        $this->syncPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_rfq_documents');
    }

    private function syncPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissions = [
            'rfq_review.view' => ['Ver revision admin de RFQ', 'rfq_review'],
            'rfq_review.approve' => ['Aprobar RFQ para proveedor', 'rfq_review'],
            'rfq_review.request_changes' => ['Solicitar cambios en RFQ', 'rfq_review'],
            'rfq_review.reject' => ['Rechazar RFQ para proveedor', 'rfq_review'],
            'supplier_rfq.edit_consignee' => ['Editar datos de consignatario RFQ', 'supplier_rfq'],
            'supplier_rfq.request_review' => ['Enviar RFQ a revision admin', 'supplier_rfq'],
        ];

        foreach ($permissions as $slug => [$name, $module]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => $module, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $adminIds = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id');
        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            Role::whereIn('slug', ['admin', 'super_admin'])->get()
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($adminIds));
        }
    }
};
