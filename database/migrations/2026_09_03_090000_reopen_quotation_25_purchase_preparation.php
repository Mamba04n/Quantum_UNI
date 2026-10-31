<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $order = DB::table('purchase_orders')
                ->where('id', 14)
                ->where('quotation_request_id', 25)
                ->lockForUpdate()
                ->first();

            if (! $order || in_array($order->status, ['anulado', 'cancelado', 'cancelled'], true)) {
                return;
            }

            $payable = DB::table('account_payables')
                ->where('id', 47)
                ->where('purchase_order_id', 14)
                ->lockForUpdate()
                ->first();

            if ($payable && (float) $payable->paid_amount > 0) {
                throw new \RuntimeException('No se puede reabrir la preparación: la CXP de la OC 14 tiene pagos aplicados.');
            }

            DB::table('purchase_orders')->where('id', 14)->update([
                'status' => 'anulado',
                'notes' => trim(($order->notes ? $order->notes."\n" : '').'Anulada para corregir moneda y regenerar desde preparación.'),
                'updated_at' => now(),
            ]);

            DB::table('purchase_order_items')->where('purchase_order_id', 14)->update([
                'status' => 'anulado',
                'updated_at' => now(),
            ]);

            if ($payable) {
                // El esquema heredado de CXP solo admite pendiente/parcial/pagado.
                // Al no existir pagos, se elimina el compromiso generado por la OC
                // anulada; la actividad inferior conserva la trazabilidad del retiro.
                DB::table('account_payables')->where('id', 47)->delete();
            }

            $approval = DB::table('approval_requests')
                ->where('id', 11)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            $auditUserId = $approval?->requested_by ?: $order->generated_by;

            if (! $auditUserId) {
                throw new \RuntimeException('No se puede registrar la reapertura sin un usuario responsable.');
            }

            DB::table('approval_requests')
                ->where('id', 11)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('purchase_preparation_items')
                ->where('quotation_request_id', 25)
                ->update([
                    'status' => 'ready_for_purchase_order',
                    'updated_at' => now(),
                ]);

            DB::table('quotation_requests')->where('id', 25)->update([
                'status' => 'aprobada_cliente_total',
                'commercial_phase' => 'approval',
                'updated_at' => now(),
            ]);

            DB::table('activity_logs')->insert([
                'user_id' => $auditUserId,
                'module' => 'compras',
                'action' => 'purchase_order.reopened_for_currency_retry',
                'description' => 'OC-655-0004 anulada y SC-00022 devuelta a preparación para corregir su moneda.',
                'metadata' => json_encode([
                    'quotation_request_id' => 25,
                    'purchase_order_id' => 14,
                    'account_payable_id' => $payable?->id,
                ]),
                'ip_address' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // No reactiva documentos financieros anulados automáticamente.
    }
};
