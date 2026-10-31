<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock_on_hand', 15, 2)->default(0)->change();
            if (! Schema::hasColumn('products', 'piece_rotation_type')) {
                $table->string('piece_rotation_type', 60)->nullable()->after('type');
            }
            if (! Schema::hasColumn('products', 'technical_category')) {
                $table->string('technical_category', 80)->nullable()->after('piece_rotation_type');
            }
            if (! Schema::hasColumn('products', 'part_number_alt_1')) {
                $table->string('part_number_alt_1', 180)->nullable()->after('part_number');
            }
            if (! Schema::hasColumn('products', 'part_number_alt_2')) {
                $table->string('part_number_alt_2', 180)->nullable()->after('part_number_alt_1');
            }
            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path')->nullable()->after('part_number_alt_2');
            }
            if (! Schema::hasColumn('products', 'total_cost')) {
                $table->decimal('total_cost', 15, 2)->default(0)->after('cost');
            }
        });

        DB::table('products')->whereIn('type', ['storable', 'consumable'])->update(['type' => 'pieza']);
        DB::table('products')->where('type', 'service')->update(['type' => 'servicio']);
        DB::table('products')->whereNull('piece_rotation_type')->where('type', 'pieza')->update(['piece_rotation_type' => 'alta_rotacion']);
        DB::table('products')->whereNull('technical_category')->where('type', 'pieza')->update(['technical_category' => 'mecanico']);
        DB::table('products')->update([
            'total_cost' => DB::raw('stock_on_hand * cost'),
        ]);

        Schema::table('stock_moves', function (Blueprint $table) {
            $table->string('movement_type', 20)->nullable()->after('product_id');
            $table->date('movement_date')->nullable()->after('movement_type');
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('cost_in', 15, 2)->default(0)->after('quantity');
            $table->decimal('cost_out', 15, 2)->default(0)->after('cost_in');
            $table->decimal('unit_cost', 15, 2)->default(0)->after('cost_out');
            $table->decimal('balance_after', 15, 2)->default(0)->after('unit_cost');
            $table->decimal('total_cost_after', 15, 2)->default(0)->after('balance_after');
            $table->string('attachment_path')->nullable()->after('reference');
        });

        DB::table('stock_moves')->orderBy('id')->chunkById(200, function ($moves) {
            foreach ($moves as $move) {
                DB::table('stock_moves')
                    ->where('id', $move->id)
                    ->update([
                        'movement_type' => ((float) $move->quantity) >= 0 ? 'in' : 'out',
                        'movement_date' => $move->created_at ? Carbon::parse($move->created_at)->toDateString() : now()->toDateString(),
                        'balance_after' => 0,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_moves', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->dropColumn([
                'movement_type',
                'movement_date',
                'cost_in',
                'cost_out',
                'unit_cost',
                'balance_after',
                'total_cost_after',
                'attachment_path',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_on_hand')->default(0)->change();
            $table->dropColumn([
                'piece_rotation_type',
                'technical_category',
                'part_number_alt_1',
                'part_number_alt_2',
                'image_path',
                'total_cost',
            ]);
        });
    }
};
