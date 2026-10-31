<?php

use App\Domain\Finanzas\ExchangeRates\Enums\ExchangeRateSourceType;
use App\Models\ExchangeRate;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->string('source_type', 40)->default(ExchangeRateSourceType::Legacy->value)->after('source');
            $table->date('expires_at')->nullable()->after('rate_date');
            $table->foreignId('approved_by')->nullable()->after('is_fallback')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('stale_approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('stale_approved_at')->nullable()->after('stale_approved_by');
        });

        $maxAgeDays = max(0, (int) config('services.exchange_rate.max_age_days', 3));
        ExchangeRate::query()->orderBy('id')->each(function (ExchangeRate $rate) use ($maxAgeDays): void {
            $source = mb_strtolower((string) $rate->source);
            $sourceType = str_contains($source, 'manual')
                ? ExchangeRateSourceType::ConfiguredFallback
                : ($rate->is_fallback ? ExchangeRateSourceType::Stored : ExchangeRateSourceType::Api);

            $rate->forceFill([
                'source_type' => $sourceType->value,
                'expires_at' => $rate->rate_date?->copy()->addDays($maxAgeDays),
            ])->save();
        });

        Schema::table('quotation_requests', function (Blueprint $table) {
            $table->string('exchange_rate_source_type', 40)->nullable()->after('exchange_rate_source');
            $table->boolean('exchange_rate_is_stale')->default(false)->after('exchange_rate_is_fallback');
            $table->foreignId('exchange_rate_approved_by')->nullable()->after('exchange_rate_is_stale')->constrained('users')->nullOnDelete();
        });

        $permission = Permission::updateOrCreate(
            ['slug' => 'finance.exchange_rates.approve'],
            ['name' => 'Aprobar tasas de cambio manuales o vencidas', 'module' => 'finance']
        );

        Role::whereIn('slug', ['admin', 'agente_finanzas', 'finanzas'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    public function down(): void
    {
        Permission::where('slug', 'finance.exchange_rates.approve')->delete();

        Schema::table('quotation_requests', function (Blueprint $table) {
            $table->dropForeign(['exchange_rate_approved_by']);
            $table->dropColumn([
                'exchange_rate_source_type',
                'exchange_rate_is_stale',
                'exchange_rate_approved_by',
            ]);
        });

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['stale_approved_by']);
            $table->dropColumn([
                'source_type',
                'expires_at',
                'approved_by',
                'approved_at',
                'stale_approved_by',
                'stale_approved_at',
            ]);
        });
    }
};
