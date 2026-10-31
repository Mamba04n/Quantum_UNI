<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->unsignedBigInteger('next_value');
                $table->timestamps();
            });
        }

        $this->fillMissingCodes('account_payables', 'payable_code', 'CXP');
        $this->fillMissingCodes('account_receivables', 'receivable_code', 'CXC');
        $this->replaceDuplicateCodes('account_payables', 'payable_code', 'CXP');
        $this->replaceDuplicateCodes('account_receivables', 'receivable_code', 'CXC');

        DB::table('document_sequences')->insertOrIgnore([
            ['name' => 'preprinted_invoice', 'next_value' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'account_payable', 'next_value' => $this->nextFinanceValue('account_payables', 'payable_code', 'CXP'), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'account_receivable', 'next_value' => $this->nextFinanceValue('account_receivables', 'receivable_code', 'CXC'), 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('account_payables', function (Blueprint $table): void {
            $table->unique('payable_code', 'account_payables_payable_code_unique');
        });
        Schema::table('account_receivables', function (Blueprint $table): void {
            $table->unique('receivable_code', 'account_receivables_receivable_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', fn (Blueprint $table) => $table->dropUnique('account_payables_payable_code_unique'));
        Schema::table('account_receivables', fn (Blueprint $table) => $table->dropUnique('account_receivables_receivable_code_unique'));
        Schema::dropIfExists('document_sequences');
    }

    private function nextFinanceValue(string $table, string $column, string $prefix): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 1;
        }

        return DB::table($table)->pluck($column)
            ->filter()
            ->map(fn ($code) => preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', (string) $code, $matches) ? (int) $matches[1] : 0)
            ->max() + 1;
    }

    private function fillMissingCodes(string $table, string $column, string $prefix): void
    {
        $next = $this->nextFinanceValue($table, $column, $prefix);
        DB::table($table)->whereNull($column)->orWhere($column, '')->orderBy('id')->get(['id'])->each(function ($row) use ($table, $column, $prefix, &$next): void {
            DB::table($table)->where('id', $row->id)->update([$column => $prefix.'-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT)]);
        });
    }

    private function replaceDuplicateCodes(string $table, string $column, string $prefix): void
    {
        $next = $this->nextFinanceValue($table, $column, $prefix);
        $duplicates = DB::table($table)->select($column)->whereNotNull($column)->where($column, '<>', '')
            ->groupBy($column)->havingRaw('COUNT(*) > 1')->pluck($column);

        foreach ($duplicates as $duplicate) {
            DB::table($table)->where($column, $duplicate)->orderBy('id')->get(['id'])->skip(1)->each(function ($row) use ($table, $column, $prefix, &$next): void {
                DB::table($table)->where('id', $row->id)->update([$column => $prefix.'-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT)]);
            });
        }
    }
};
