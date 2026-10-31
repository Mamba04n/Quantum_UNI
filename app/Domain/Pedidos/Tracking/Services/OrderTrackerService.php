<?php

namespace App\Domain\Pedidos\Tracking\Services;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\OrderTracker;
use App\Models\OrderTrackerActivity;
use App\Models\OrderTrackerAttachment;
use App\Models\OrderTrackerComment;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\ConvertQuotationToSaleOrderService;
use App\Services\SalesWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderTrackerService
{
    public const QUOTE_PENDING_APPROVAL = 'pendiente_aprobacion';

    public function trackerForQuotation(QuotationRequest $quotation, bool $withRelations = true): OrderTracker
    {
        return DB::transaction(function () use ($quotation, $withRelations) {
            $tracker = $this->findBySource('quotation_requests', $quotation->id);

            if (! $tracker && $quotation->sale_order_id) {
                $tracker = $this->findBySource('sale_orders', $quotation->sale_order_id);
            }

            if (! $tracker) {
                $status = $quotation->status ?: QuotationRequest::STATUS_RECEIVED;
                $phase = $this->phaseForQuotationStatus($status);

                $tracker = OrderTracker::create([
                    'tracking_code' => $this->nextTrackingCode(),
                    'operational_phase' => $phase,
                    'operational_status' => $status,
                    'main_reference' => $quotation->reference,
                ]);

                $this->recordActivity(
                    $tracker,
                    'tracker_created',
                    "Tracker {$tracker->tracking_code} creado para solicitud {$quotation->reference}",
                    $phase,
                    'quotation_requests',
                    $quotation->id,
                    null,
                    $status
                );
            }

            $this->addReference($tracker, 'quotation', $quotation->reference, 'quotation_requests', $quotation->id);

            return $withRelations
                ? $tracker->fresh($this->defaultRelations())
                : $tracker->refresh();
        });
    }

    public function trackerForOrder(SaleOrder $order): OrderTracker
    {
        return DB::transaction(function () use ($order) {
            $tracker = $this->findBySource('sale_orders', $order->id);

            if (! $tracker) {
                $quote = QuotationRequest::where('sale_order_id', $order->id)->first();
                $tracker = $quote ? $this->trackerForQuotation($quote) : null;
            }

            if (! $tracker) {
                $tracker = OrderTracker::create([
                    'tracking_code' => $this->nextTrackingCode(),
                    'operational_phase' => 'pedido_extranjero',
                    'operational_status' => $order->status ?: 'solicitado_proveedor',
                    'main_reference' => $order->reference,
                ]);
            }

            $this->addReference($tracker, 'foreign_order', $order->reference, 'sale_orders', $order->id);

            return $tracker->fresh($this->defaultRelations());
        });
    }

    public function requestForeignOrderApproval(QuotationRequest $quotation, User $user, ?string $reason = null): ApprovalRequest
    {
        return DB::transaction(function () use ($quotation, $user, $reason) {
            $quotation->loadMissing('items');

            if ($quotation->items->where('is_approved', true)->isEmpty()) {
                throw ValidationException::withMessages([
                    'approvalReason' => 'Debes aprobar al menos una partida antes de solicitar la conversion.',
                ]);
            }

            $tracker = $this->trackerForQuotation($quotation);

            $existing = ApprovalRequest::where('request_type', ApprovalRequest::TYPE_FOREIGN_ORDER)
                ->where('source_module', 'quotation_requests')
                ->where('source_id', $quotation->id)
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'approvalReason' => 'Ya existe una solicitud pendiente para esta cotizacion.',
                ]);
            }

            $oldStatus = $quotation->status;
            $quotation->forceFill([
                'status' => self::QUOTE_PENDING_APPROVAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            $tracker->update([
                'operational_phase' => 'aprobacion',
                'operational_status' => self::QUOTE_PENDING_APPROVAL,
            ]);

            $approval = ApprovalRequest::create([
                'tracker_id' => $tracker->id,
                'request_type' => ApprovalRequest::TYPE_FOREIGN_ORDER,
                'source_module' => 'quotation_requests',
                'source_id' => $quotation->id,
                'requested_by' => $user->id,
                'status' => ApprovalRequest::STATUS_PENDING,
                'reason' => $reason,
            ]);

            $this->recordActivity(
                $tracker,
                'approval_requested',
                "Solicitud de aprobacion creada para convertir {$quotation->reference} a Pedido Extranjero",
                'aprobacion',
                'approval_requests',
                $approval->id,
                $oldStatus,
                self::QUOTE_PENDING_APPROVAL,
                $user
            );

            ActivityLog::log('ventas', 'approval_requested', "Solicitud de aprobacion creada para {$quotation->reference}", [
                'tracker_id' => $tracker->id,
                'tracking_code' => $tracker->operational_tracking_code,
                'approval_id' => $approval->id,
                'quote_id' => $quotation->id,
            ]);

            return $approval;
        });
    }

    public function approveForeignOrder(ApprovalRequest $approval, User $admin, ConvertQuotationToSaleOrderService $converter): SaleOrder
    {
        return DB::transaction(function () use ($approval, $admin, $converter) {
            if ($approval->status !== ApprovalRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['approval' => 'Esta solicitud ya fue procesada.']);
            }

            if (! $admin->can('order_approval.approve')) {
                throw ValidationException::withMessages(['approval' => 'No tienes permiso para aprobar esta conversion.']);
            }

            $quotation = QuotationRequest::with('items')->findOrFail($approval->source_id);
            $tracker = $approval->tracker ?: $this->trackerForQuotation($quotation);

            $order = $converter->convert($quotation, $admin);

            $quotation->forceFill([
                'status' => QuotationRequest::STATUS_APPROVED_TOTAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            $approval->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $tracker->update([
                'operational_phase' => 'pedido_extranjero',
                'operational_status' => 'solicitado_proveedor',
                'main_reference' => $order->reference,
            ]);

            $this->addReference($tracker, 'foreign_order', $order->reference, 'sale_orders', $order->id);

            $this->recordActivity(
                $tracker,
                'approval_approved',
                "Aprobada la conversion de {$quotation->reference} y creado Pedido Extranjero {$order->reference}",
                'pedido_extranjero',
                'sale_orders',
                $order->id,
                self::QUOTE_PENDING_APPROVAL,
                'solicitado_proveedor',
                $admin
            );

            ActivityLog::log('ventas', 'approval_approved', "Aprobacion aceptada y pedido {$order->reference} creado", [
                'tracker_id' => $tracker->id,
                'tracking_code' => $tracker->operational_tracking_code,
                'approval_id' => $approval->id,
                'quote_id' => $quotation->id,
                'order_id' => $order->id,
            ]);

            return $order->fresh(['items']);
        });
    }

    public function rejectForeignOrder(ApprovalRequest $approval, User $admin, string $reason): void
    {
        DB::transaction(function () use ($approval, $admin, $reason) {
            if ($approval->status !== ApprovalRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['approval' => 'Esta solicitud ya fue procesada.']);
            }

            if (! $admin->can('order_approval.reject')) {
                throw ValidationException::withMessages(['approval' => 'No tienes permiso para rechazar esta conversion.']);
            }

            $quotation = QuotationRequest::find($approval->source_id);
            $tracker = $approval->tracker;

            if ($quotation) {
                $quotation->forceFill([
                    'status' => QuotationRequest::STATUS_APPROVED_TOTAL,
                    'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
                ])->save();
            }

            $approval->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'approved_by' => $admin->id,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ]);

            if ($tracker) {
                $tracker->update([
                    'operational_phase' => 'aprobacion',
                    'operational_status' => 'rechazado',
                ]);

                $this->recordActivity(
                    $tracker,
                    'approval_rejected',
                    "Conversion a Pedido Extranjero rechazada: {$reason}",
                    'aprobacion',
                    'approval_requests',
                    $approval->id,
                    ApprovalRequest::STATUS_PENDING,
                    ApprovalRequest::STATUS_REJECTED,
                    $admin
                );

                ActivityLog::log('ventas', 'approval_rejected', "Aprobacion rechazada para tracker {$tracker->operational_tracking_code}", [
                    'tracker_id' => $tracker->id,
                    'tracking_code' => $tracker->operational_tracking_code,
                    'approval_id' => $approval->id,
                ]);
            }
        });
    }

    public function addReference(OrderTracker $tracker, string $type, ?string $code, string $sourceModule, ?int $sourceId): void
    {
        $tracker->references()->firstOrCreate(
            ['source_module' => $sourceModule, 'source_id' => $sourceId],
            ['reference_type' => $type, 'reference_code' => $code]
        );
    }

    public function recordActivity(
        OrderTracker $tracker,
        string $action,
        string $description,
        string $phase = 'general',
        ?string $sourceModule = null,
        ?int $sourceId = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?User $user = null
    ): OrderTrackerActivity {
        return $tracker->activities()->create([
            'action' => $action,
            'description' => $description,
            'phase' => $phase,
            'source_module' => $sourceModule,
            'source_id' => $sourceId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $user?->id ?? auth()->id(),
        ]);
    }

    public function addAttachment(
        OrderTracker $tracker,
        UploadedFile $file,
        string $phase,
        ?string $comment = null,
        ?OrderTrackerActivity $activity = null,
        ?string $sourceModule = null,
        ?int $sourceId = null
    ): OrderTrackerAttachment {
        $path = $file->store('order-tracker', 'private');

        $attachment = $tracker->attachments()->create([
            'activity_id' => $activity?->id,
            'phase' => $phase,
            'source_module' => $sourceModule,
            'source_id' => $sourceId,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize() ?: 0,
            'uploaded_by' => auth()->id(),
            'comment' => $comment,
        ]);

        $activity = $activity ?: $this->recordActivity(
            $tracker,
            'attachment_added',
            "Archivo adjuntado en fase {$phase}: {$attachment->file_name}",
            $phase,
            $sourceModule,
            $sourceId
        );

        $attachment->update(['activity_id' => $activity->id]);

        ActivityLog::log('ventas', 'tracker_attachment_added', "Archivo adjuntado al tracker {$tracker->operational_tracking_code}", [
            'tracker_id' => $tracker->id,
            'tracking_code' => $tracker->operational_tracking_code,
            'attachment_id' => $attachment->id,
        ]);

        return $attachment;
    }

    public function addComment(
        OrderTracker $tracker,
        string $comment,
        string $visibility = 'internal',
        ?OrderTrackerActivity $activity = null,
        ?User $user = null
    ): OrderTrackerComment {
        $user = $user ?: auth()->user();

        $commentModel = $tracker->comments()->create([
            'activity_id' => $activity?->id,
            'user_id' => $user?->id,
            'comment' => trim($comment),
            'visibility' => $visibility,
        ]);

        $activity = $activity ?: $this->recordActivity(
            $tracker,
            'comment_added',
            "Comentario {$visibility} agregado al tracker {$tracker->tracking_code}",
            'comentario',
            null,
            null,
            null,
            null,
            $user
        );

        $commentModel->update(['activity_id' => $activity->id]);

        ActivityLog::log('ventas', 'tracker_comment_added', "Comentario agregado al tracker {$tracker->operational_tracking_code}", [
            'tracker_id' => $tracker->id,
            'tracking_code' => $tracker->operational_tracking_code,
            'comment_id' => $commentModel->id,
        ]);

        return $commentModel;
    }

    public function findBySource(string $sourceModule, int $sourceId): ?OrderTracker
    {
        return OrderTracker::whereHas('references', function ($query) use ($sourceModule, $sourceId) {
            $query->where('source_module', $sourceModule)->where('source_id', $sourceId);
        })->first();
    }

    public function defaultRelations(): array
    {
        return [
            'references',
            'activities.user',
            'activities.attachments.uploader',
            'activities.attachments.generator',
            'activities.comments.user',
            'attachments.uploader',
            'attachments.generator',
            'comments.user',
            'approvalRequests.requester',
            'approvalRequests.approver',
        ];
    }

    private function nextTrackingCode(): string
    {
        $year = (int) now()->format('Y');

        if (! Schema::hasTable('order_tracker_sequences')) {
            $count = OrderTracker::whereYear('created_at', $year)->count() + 1;

            return 'TRK-'.$year.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        }

        $sequence = DB::table('order_tracker_sequences')->where('year', $year)->lockForUpdate()->first();

        if (! $sequence) {
            DB::table('order_tracker_sequences')->insert([
                'year' => $year,
                'next_number' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 'TRK-'.$year.'-0001';
        }

        DB::table('order_tracker_sequences')
            ->where('id', $sequence->id)
            ->update([
                'next_number' => $sequence->next_number + 1,
                'updated_at' => now(),
            ]);

        return 'TRK-'.$year.'-'.str_pad((string) $sequence->next_number, 4, '0', STR_PAD_LEFT);
    }

    private function phaseForQuotationStatus(string $status): string
    {
        return match ($status) {
            QuotationRequest::STATUS_RECEIVED,
            QuotationRequest::STATUS_IN_REVIEW,
            QuotationRequest::STATUS_CANCELLED => SalesWorkflowService::PHASE_REQUEST,

            QuotationRequest::STATUS_READY_FOR_BID,
            QuotationRequest::STATUS_PENDING_BID,
            QuotationRequest::STATUS_RFQ_GENERATED,
            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED => SalesWorkflowService::PHASE_SUPPLIER_BID,

            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            QuotationRequest::STATUS_OFFER_DRAFT,
            QuotationRequest::STATUS_OFFER_PREVIEW_READY,
            QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            QuotationRequest::STATUS_ADMIN_REJECTED,
            QuotationRequest::STATUS_APPROVED_TO_SEND,
            QuotationRequest::STATUS_FINAL_PDF_GENERATED,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE => SalesWorkflowService::PHASE_CLIENT_OFFER,

            QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            QuotationRequest::STATUS_SENT_TO_CUSTOMER,
            QuotationRequest::STATUS_CUSTOMER_ACCEPTED_TOTAL,
            QuotationRequest::STATUS_CUSTOMER_ACCEPTED_PARTIAL,
            QuotationRequest::STATUS_CUSTOMER_REJECTED,
            QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
            QuotationRequest::STATUS_REJECTED,
            QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
            self::QUOTE_PENDING_APPROVAL => SalesWorkflowService::PHASE_APPROVAL,

            default => SalesWorkflowService::PHASE_REQUEST,
        };
    }
}
