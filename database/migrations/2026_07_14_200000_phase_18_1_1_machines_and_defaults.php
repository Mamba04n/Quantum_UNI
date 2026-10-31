<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Machines reference to client_machines
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (Schema::hasColumn('journal_entries', 'machine_id')) {
                    $table->dropForeign(['machine_id']);
                    $table->dropColumn('machine_id'); // Drop old legacy
                }
                if (!Schema::hasColumn('journal_entries', 'client_machine_id')) {
                    $table->foreignId('client_machine_id')->nullable()->constrained('client_machines')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('logistics_shipments')) {
            Schema::table('logistics_shipments', function (Blueprint $table) {
                if (Schema::hasColumn('logistics_shipments', 'machine_id')) {
                    $table->dropForeign(['machine_id']);
                    $table->dropColumn('machine_id'); // Drop old legacy
                }
                if (!Schema::hasColumn('logistics_shipments', 'client_machine_id')) {
                    $table->foreignId('client_machine_id')->nullable()->constrained('client_machines')->nullOnDelete();
                }
            });
        }

        // 2. Exact Defaults for Order Statuses and Trackers
        // MariaDB exact syntax for defaults
        if (Schema::hasTable('order_statuses')) {
            DB::statement("ALTER TABLE order_statuses ALTER COLUMN administrative_phase SET DEFAULT 'pedido_extranjero'");
            DB::statement("ALTER TABLE order_statuses ALTER COLUMN administrative_status SET DEFAULT 'pendiente_oc'");
        }

        if (Schema::hasTable('order_trackers')) {
            DB::statement("ALTER TABLE order_trackers ALTER COLUMN operational_phase SET DEFAULT 'solicitud'");
            DB::statement("ALTER TABLE order_trackers ALTER COLUMN operational_status SET DEFAULT 'abierto'");
        }

        // 3. Drop Default Currency
        if (Schema::hasTable('entity_bank_accounts')) {
            // Remove default from currency
            DB::statement("ALTER TABLE entity_bank_accounts ALTER COLUMN currency DROP DEFAULT");
        }

        // 4. Product Images Unique
        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                // Drop existing if exists to recreate safely? We created it in phase 18.1 with unique constraint actually!
                // Let's verify. Yes, in 18.1 we created product_images with $table->unique(['product_id', 'image_path']);
                // So this is already covered. We will just ensure it exists if not.
                $indexes = Schema::getIndexes('product_images');
                $hasUnique = false;
                foreach ($indexes as $index) {
                    if ($index['unique'] && !$index['primary']) {
                        $cols = $index['columns'];
                        if (in_array('product_id', $cols) && in_array('image_path', $cols)) {
                            $hasUnique = true;
                            break;
                        }
                    }
                }
                if (!$hasUnique) {
                    $table->unique(['product_id', 'image_path'], 'prod_img_unique');
                }
            });
        }
    }

    public function down(): void
    {
        // Reversals
    }
};
