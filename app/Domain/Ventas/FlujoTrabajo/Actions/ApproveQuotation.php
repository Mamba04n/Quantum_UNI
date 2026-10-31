<?php

namespace App\Domain\Ventas\FlujoTrabajo\Actions;

use App\Domain\Ventas\FlujoTrabajo\Services\SalesWorkflowService;
use App\Models\ActivityLog;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveQuotation
{
    public function execute(SalesWorkflowService $workflow, QuotationRequest $request, User $user): QuotationRequest
    {
        return DB::transaction(function () use ($workflow, $request, $user) {
            if (! $user->can('order_approval.approve')) {
                throw ValidationException::withMessages(['workflow' => 'No tienes permiso para aprobar ofertas.']);
            }

            $request->loadMissing('items');

            if (! $request->client_offer_pdf_path) {
                throw ValidationException::withMessages(['workflow' => 'No se puede aprobar sin PDF de oferta cliente.']);
            }

            if (! in_array($request->status, [QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, QuotationRequest::STATUS_CLIENT_OFFER_SENT, QuotationRequest::STATUS_APPROVED_PARTIAL, QuotationRequest::STATUS_APPROVED_TOTAL], true)) {
                throw ValidationException::withMessages(['workflow' => 'La oferta no esta en una fase aprobable.']);
            }

            if ($request->items->contains(fn ($item) => (float) ($item->unit_price ?? 0) <= 0)) {
                throw ValidationException::withMessages(['workflow' => 'No se puede aprobar: existen partidas sin precio final.']);
            }

            if (! $workflow->hasAvailableInventory($request) && ! $workflow->hasValidSupplierBid($request)) {
                throw ValidationException::withMessages(['workflow' => 'No se puede aprobar: falta proveedor seleccionado/respuesta valida o stock disponible.']);
            }

            $request->items()->update(['is_approved' => true]);
            $subtotal = $request->items()->sum('line_total');
            $oldStatus = $request->status;

            $request->forceFill([
                'subtotal' => $subtotal,
                'total_amount' => round($subtotal * 1.15, 2),
                'status' => QuotationRequest::STATUS_APPROVED_TOTAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            $workflow->record($request, 'order_approval.approved', 'Oferta aprobada total; lista para crear orden o pedido extranjero.', SalesWorkflowService::PHASE_APPROVAL, $oldStatus, $request->status, $user);

            ActivityLog::log('ventas', 'client_offer.approved', "Oferta cliente {$request->reference} aprobada.", [
                'quote_id' => $request->id,
                'approved_by' => $user->id,
                'approved_items' => $request->items->count(),
            ]);

            return $request->fresh('items');
        });
    }
}
