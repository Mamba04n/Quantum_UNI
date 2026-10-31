<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            Schema::create('order_statuses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_order_id')->unique()->constrained('sale_orders')->cascadeOnDelete();
                $table->foreignId('order_tracker_id')->nullable()->constrained('order_trackers')->nullOnDelete();
                $table->string('tracking_code')->nullable()->index();
                $table->string('order_code')->nullable()->index();
                $table->foreignId('customer_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('current_phase')->default('pedido_extranjero');
                $table->string('current_status')->default('pendiente_oc')->index();
                $table->unsignedTinyInteger('progress_percentage')->default(0);
                $table->string('currency', 10)->default('USD');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->date('approved_at')->nullable();
                $table->date('estimated_arrival_date')->nullable();
                $table->text('observations')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_status_items')) {
            Schema::create('order_status_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_status_id')->constrained('order_statuses')->cascadeOnDelete();
                $table->foreignId('sale_order_item_id')->nullable()->unique()->constrained('sale_order_items')->nullOnDelete();
                $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->string('part_name')->nullable();
                $table->string('part_number')->nullable();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->decimal('quantity_ordered', 12, 2)->default(0);
                $table->decimal('quantity_confirmed', 12, 2)->default(0);
                $table->decimal('quantity_received', 12, 2)->default(0);
                $table->decimal('quantity_kardex_entry', 12, 2)->default(0);
                $table->decimal('quantity_kardex_output', 12, 2)->default(0);
                $table->decimal('quantity_delivered', 12, 2)->default(0);
                $table->date('estimated_arrival_date')->nullable();
                $table->date('actual_arrival_date')->nullable();
                $table->string('status')->default('pendiente_oc')->index();
                $table->text('cancellation_reason')->nullable();
                $table->boolean('replacement_required')->default(false);
                $table->timestamps();

                $table->index(['order_status_id', 'status']);
            });
        }

        if (! Schema::hasTable('order_status_documents')) {
            Schema::create('order_status_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_status_id')->constrained('order_statuses')->cascadeOnDelete();
                $table->foreignId('order_status_item_id')->nullable()->constrained('order_status_items')->cascadeOnDelete();
                $table->string('phase')->index();
                $table->string('document_type')->index();
                $table->string('file_name');
                $table->string('file_path');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['order_status_id', 'phase']);
            });
        }

        if (! Schema::hasTable('order_status_events')) {
            Schema::create('order_status_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_status_id')->constrained('order_statuses')->cascadeOnDelete();
                $table->foreignId('order_status_item_id')->nullable()->constrained('order_status_items')->cascadeOnDelete();
                $table->string('event_type')->index();
                $table->string('phase')->default('general')->index();
                $table->string('old_status')->nullable();
                $table->string('new_status')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('replacement_requests')) {
            Schema::create('replacement_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_status_item_id')->constrained('order_status_items')->cascadeOnDelete();
                $table->foreignId('original_supplier_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->foreignId('new_supplier_id')->nullable()->constrained('entities')->nullOnDelete();
                $table->text('reason');
                $table->string('status')->default('requested')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_requests');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_status_documents');
        Schema::dropIfExists('order_status_items');
        Schema::dropIfExists('order_statuses');
    }
};
