<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_offer_reviews')) {
            Schema::create('client_offer_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
                $table->foreignId('client_offer_version_id')->nullable()->constrained('client_offer_versions')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->text('review_notes')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['quotation_request_id', 'status'], 'client_offer_reviews_quote_status_idx');
            });
        }

        if (! Schema::hasTable('client_offer_change_requests')) {
            Schema::create('client_offer_change_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_request_id')->constrained('quotation_requests')->cascadeOnDelete();
                $table->foreignId('client_offer_version_id')->nullable()->constrained('client_offer_versions')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('change_type');
                $table->json('affected_items_json')->nullable();
                $table->text('reason');
                $table->text('customer_comment')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('status')->default('pending');
                $table->boolean('requires_admin_review')->default(true);
                $table->boolean('requires_bid_reselection')->default(false);
                $table->timestamps();

                $table->index(['quotation_request_id', 'status'], 'client_offer_changes_quote_status_idx');
            });
        }

        if (Schema::hasTable('client_offer_versions')) {
            Schema::table('client_offer_versions', function (Blueprint $table) {
                if (! Schema::hasColumn('client_offer_versions', 'reason')) {
                    $table->text('reason')->nullable()->after('status');
                }
                if (! Schema::hasColumn('client_offer_versions', 'snapshot_json')) {
                    $table->json('snapshot_json')->nullable()->after('reason');
                }
                if (! Schema::hasColumn('client_offer_versions', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('client_offer_versions', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('client_offer_versions', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable()->after('approved_at');
                }
            });
        }

        if (Schema::hasTable('quotation_requests')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('quotation_requests', 'admin_review_requested_at')) {
                    $table->timestamp('admin_review_requested_at')->nullable()->after('client_offer_generated_at');
                }
                if (! Schema::hasColumn('quotation_requests', 'admin_reviewed_at')) {
                    $table->timestamp('admin_reviewed_at')->nullable()->after('admin_review_requested_at');
                }
                if (! Schema::hasColumn('quotation_requests', 'client_offer_sent_at')) {
                    $table->timestamp('client_offer_sent_at')->nullable()->after('admin_reviewed_at');
                }
                if (! Schema::hasColumn('quotation_requests', 'offer_review_notes')) {
                    $table->text('offer_review_notes')->nullable()->after('client_offer_sent_at');
                }
            });
        }

        $this->syncPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('client_offer_change_requests');
        Schema::dropIfExists('client_offer_reviews');

        if (Schema::hasTable('client_offer_versions')) {
            Schema::table('client_offer_versions', function (Blueprint $table) {
                foreach (['sent_at', 'approved_at', 'approved_by', 'snapshot_json', 'reason'] as $column) {
                    if (Schema::hasColumn('client_offer_versions', $column)) {
                        if ($column === 'approved_by') {
                            $table->dropConstrainedForeignId($column);
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('quotation_requests')) {
            Schema::table('quotation_requests', function (Blueprint $table) {
                foreach (['offer_review_notes', 'client_offer_sent_at', 'admin_reviewed_at', 'admin_review_requested_at'] as $column) {
                    if (Schema::hasColumn('quotation_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function syncPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissions = [
            'client_offer.create' => ['Crear oferta cliente', 'client_offer'],
            'client_offer.edit' => ['Editar oferta cliente', 'client_offer'],
            'client_offer.preview' => ['Previsualizar oferta cliente', 'client_offer'],
            'client_offer.request_review' => ['Enviar oferta a revision admin', 'client_offer'],
            'client_offer.generate_final_pdf' => ['Generar PDF final oferta cliente', 'client_offer'],
            'client_offer.send_to_customer' => ['Marcar oferta enviada al cliente', 'client_offer'],
            'client_response.register' => ['Registrar respuesta cliente', 'client_offer'],
            'client_offer_review.view' => ['Ver revision admin de ofertas', 'client_offer_review'],
            'client_offer_review.approve' => ['Aprobar oferta para enviar', 'client_offer_review'],
            'client_offer_review.reject' => ['Rechazar oferta para enviar', 'client_offer_review'],
            'client_offer_review.request_changes' => ['Solicitar cambios en oferta', 'client_offer_review'],
            'client_offer.view_costs' => ['Ver costos proveedor en oferta', 'client_offer'],
            'client_offer.view_margin' => ['Ver margen de oferta', 'client_offer'],
            'client_offer.view_totals' => ['Ver totales de oferta', 'client_offer'],
        ];

        foreach ($permissions as $slug => [$name, $module]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => $module, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
