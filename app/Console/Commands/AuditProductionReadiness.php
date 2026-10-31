<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AuditProductionReadiness extends Command
{
    protected $signature = 'audit:production-readiness {--fail-on-findings : Devuelve código de error si hay pendientes}';

    protected $description = 'Diagnostica identidades duplicadas, responsables nulos y documentos privados faltantes sin modificar datos';

    public function handle(): int
    {
        if (! $this->schemaIsReady()) {
            $this->error('El esquema no está actualizado. Ejecuta y verifica las migraciones antes del diagnóstico.');

            return self::FAILURE;
        }

        $findings = [
            'duplicate_customer_emails' => $this->duplicateGroups('email', 'LOWER(TRIM(email))'),
            'duplicate_customer_tax_ids' => $this->duplicateGroups(
                'rfc_ruc',
                "UPPER(REPLACE(REPLACE(TRIM(rfc_ruc), '-', ''), ' ', ''))"
            ),
            'customers_without_identity_fingerprint' => DB::table('entities')
                ->where('type', 'cliente')
                ->where(function ($query): void {
                    $query->whereNull('email_fingerprint')->whereNotNull('email')
                        ->orWhere(function ($query): void {
                            $query->whereNull('tax_id_fingerprint')->whereNotNull('rfc_ruc');
                        });
                })->count(),
            'payables_without_responsible' => DB::table('account_payables')->whereNull('created_by')->count(),
            'receivables_without_responsible' => DB::table('account_receivables')->whereNull('created_by')->count(),
            'invoices_without_responsible' => DB::table('invoices')
                ->whereNull('generated_by')->whereNull('uploaded_by')->count(),
            'payments_without_responsible' => DB::table('payments')->whereNull('registered_by')->count(),
            'missing_private_finance_files' => $this->missingPrivateFiles(),
        ];

        $this->table(['Control', 'Hallazgos'], collect($findings)->map(
            fn (int $count, string $control): array => [$control, $count]
        )->values()->all());
        $this->warn('Este comando es diagnóstico: no fusiona identidades ni asigna responsables automáticamente.');

        return $this->option('fail-on-findings') && array_sum($findings) > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasColumns('entities', ['email_fingerprint', 'tax_id_fingerprint'])
            && Schema::hasColumns('account_payables', ['created_by', 'support_document_path'])
            && Schema::hasColumns('account_receivables', ['created_by', 'support_document_path'])
            && Schema::hasColumns('invoices', ['generated_by', 'uploaded_by', 'invoice_file_path'])
            && Schema::hasColumns('payments', ['registered_by', 'payment_evidence_path']);
    }

    private function duplicateGroups(string $column, string $expression): int
    {
        return DB::query()->fromSub(
            DB::table('entities')
                ->selectRaw("{$expression} AS normalized_value")
                ->where('type', 'cliente')
                ->whereNotNull($column)
                ->whereRaw("TRIM({$column}) <> ''")
                ->groupByRaw($expression)
                ->havingRaw('COUNT(*) > 1'),
            'duplicates'
        )->count();
    }

    private function missingPrivateFiles(): int
    {
        $documents = [
            ['account_payables', 'support_document_path'],
            ['account_receivables', 'support_document_path'],
            ['invoices', 'invoice_file_path'],
            ['payments', 'payment_evidence_path'],
        ];
        $missing = 0;

        foreach ($documents as [$table, $column]) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->select(['id', $column])
                ->chunkById(500, function ($rows) use ($column, &$missing): void {
                    foreach ($rows as $row) {
                        if (! Storage::disk('private')->exists((string) $row->{$column})) {
                            $missing++;
                        }
                    }
                });
        }

        return $missing;
    }
}
