<?php

namespace App\Console\Commands;

use App\Infrastructure\Audit\BusinessAuditRecorder;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CaptureFinanceAuditBaseline extends Command
{
    protected $signature = 'audit:finance-baseline {--commit : Registrar instantáneas; sin esta opción solo cuenta}';

    protected $description = 'Incorpora el estado actual de registros financieros sin historial; no reconstruye acciones pasadas.';

    public function handle(BusinessAuditRecorder $recorder): int
    {
        $count = 0;
        DB::transaction(function () use ($recorder, &$count): void {
            foreach ([AccountReceivable::class, AccountPayable::class, Payment::class] as $class) {
                $class::orderBy('id')->chunkById(100, function ($records) use ($class, $recorder, &$count): void {
                    foreach ($records as $record) {
                        if (AuditLog::where('entity_type', $class)->where('entity_id', $record->id)->exists()) {
                            continue;
                        }
                        $count++;
                        if ($this->option('commit')) {
                            $recorder->record('baseline.snapshot', $class, $record->id, [
                                'module' => 'finanzas', 'changes' => $record->getAttributes(),
                                'source_created_at' => $record->created_at?->toIso8601String(),
                                'notice' => 'Estado observado al activar auditoría ampliada. No representa un movimiento nuevo ni permite reconstruir cambios anteriores.',
                            ]);
                            if (! AuditLog::where('entity_type', $class)->where('entity_id', $record->id)->where('action', 'baseline.snapshot')->exists()) {
                                throw new \RuntimeException('No se pudo guardar la instantánea de auditoría.');
                            }
                        }
                    }
                });
            }
        });
        $this->info(($this->option('commit') ? 'Instantáneas registradas: ' : 'Registros sin auditoría: ').$count);

        return self::SUCCESS;
    }
}
