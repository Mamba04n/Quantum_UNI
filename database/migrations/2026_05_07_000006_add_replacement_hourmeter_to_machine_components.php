<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_machine_components', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_machine_components', 'last_replacement_hourmeter')) {
                $table->unsignedInteger('last_replacement_hourmeter')->nullable()->after('counter_type');
            }
        });

        DB::table('customer_machine_components')
            ->join('client_machines', 'customer_machine_components.client_machine_id', '=', 'client_machines.id')
            ->where('customer_machine_components.counter_type', 'horas')
            ->whereNull('customer_machine_components.last_replacement_hourmeter')
            ->whereNotNull('client_machines.hourmeter')
            ->select([
                'customer_machine_components.id',
                'customer_machine_components.current_usage',
                'client_machines.hourmeter',
            ])
            ->orderBy('customer_machine_components.id')
            ->chunk(100, function ($components) {
                foreach ($components as $component) {
                    DB::table('customer_machine_components')
                        ->where('id', $component->id)
                        ->update([
                            'last_replacement_hourmeter' => max(0, (int) $component->hourmeter - (int) $component->current_usage),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customer_machine_components', function (Blueprint $table) {
            if (Schema::hasColumn('customer_machine_components', 'last_replacement_hourmeter')) {
                $table->dropColumn('last_replacement_hourmeter');
            }
        });
    }
};
