<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_request_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('quotation_request_id')->constrained('products')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotation_request_items', 'client_machine_id')) {
                $table->foreignId('client_machine_id')->nullable()->after('product_id')->constrained('client_machines')->nullOnDelete();
            }

            if (! Schema::hasColumn('quotation_request_items', 'part_name')) {
                $table->string('part_name')->nullable()->after('client_machine_id');
            }

            if (! Schema::hasColumn('quotation_request_items', 'model')) {
                $table->string('model')->nullable()->after('brand');
            }

            if (! Schema::hasColumn('quotation_request_items', 'serial_number')) {
                $table->string('serial_number')->nullable()->after('model');
            }

            if (! Schema::hasColumn('quotation_request_items', 'machine_brand')) {
                $table->string('machine_brand')->nullable()->after('serial_number');
            }

            if (! Schema::hasColumn('quotation_request_items', 'machine_model')) {
                $table->string('machine_model')->nullable()->after('machine_brand');
            }

            if (! Schema::hasColumn('quotation_request_items', 'unit')) {
                $table->string('unit', 30)->default('unidad')->after('quantity');
            }

            if (! Schema::hasColumn('quotation_request_items', 'notes')) {
                $table->text('notes')->nullable()->after('unit');
            }

            if (! Schema::hasColumn('quotation_request_items', 'image_path')) {
                $table->string('image_path')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('quotation_request_items', 'status')) {
                $table->string('status', 40)->default('pending_review')->after('image_path');
            }
        });

        DB::table('quotation_request_items')
            ->orderBy('id')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $request = DB::table('quotation_requests')->where('id', $item->quotation_request_id)->first();

                    DB::table('quotation_request_items')
                        ->where('id', $item->id)
                        ->update([
                            'part_name' => $item->part_name ?: ($item->description ?: ($item->part_number ?: 'Pieza sin nombre')),
                            'notes' => $item->notes ?: $item->description,
                            'client_machine_id' => $item->client_machine_id ?: ($request->client_machine_id ?? null),
                            'machine_brand' => $item->machine_brand ?: ($request->machine_brand ?? null),
                            'machine_model' => $item->machine_model ?: ($request->machine_model ?? null),
                            'unit' => $item->unit ?: 'unidad',
                            'status' => $item->status ?: 'pending_review',
                        ]);
                }
            });

        DB::table('quotation_request_items')
            ->whereNull('unit')
            ->update(['unit' => 'unidad']);

        DB::table('quotation_request_items')
            ->whereNull('status')
            ->update(['status' => 'pending_review']);

        DB::table('quotation_request_items')
            ->whereNull('product_id')
            ->whereNotNull('part_number')
            ->orderBy('id')
            ->chunkById(100, function ($items) {
                foreach ($items as $item) {
                    $product = DB::table('products')
                        ->where('sku', $item->part_number)
                        ->orWhere('part_number', $item->part_number)
                        ->first();

                    if ($product) {
                        DB::table('quotation_request_items')
                            ->where('id', $item->id)
                            ->update(['product_id' => $product->id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('quotation_request_items', function (Blueprint $table) {
            foreach ([
                'product_id',
                'client_machine_id',
                'part_name',
                'model',
                'serial_number',
                'machine_brand',
                'machine_model',
                'unit',
                'notes',
                'image_path',
                'status',
            ] as $column) {
                if (Schema::hasColumn('quotation_request_items', $column)) {
                    if (in_array($column, ['product_id', 'client_machine_id'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
