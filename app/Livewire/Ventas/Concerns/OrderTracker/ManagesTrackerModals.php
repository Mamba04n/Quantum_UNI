<?php

namespace App\Livewire\Ventas\Concerns\OrderTracker;

use App\Models\AuditLog;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Services\ConvertQuotationToSaleOrderService;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

trait ManagesTrackerModals
{
    use WithFileUploads;

    public $showModal = false;

    public $selectedItem = null;

    public $selectedTracker = null;

    public ?int $selectedOrderStatusItemId = null;

    public $itemType = '';

    public $errorMessage = '';

    public $showConfirmModal = false;

    public $viewMode = 'kanban';

    public $search = '';

    public $itemNotes = '';

    public $approvalReason = '';

    public $trackerComment = '';

    public $commentVisibility = 'internal';

    public $trackerAttachment = null;

    public $attachmentPhase = 'general';

    public $attachmentComment = '';

    public $confirmSummary = [
        'items_count' => 0,
        'excluded_count' => 0,
        'total' => 0,
        'client' => '',
    ];

    public function setViewMode($mode)
    {
        abort_unless(auth()->user()?->can('tracker.view'), 403);
        $this->viewMode = $mode;
    }

    public function openDetails($id, $type, $orderStatusItemId = null)
    {
        abort_unless(auth()->user()?->can('tracker.view'), 403);

        $trackerService = app(OrderTrackerService::class);
        $this->itemType = $type;
        $this->selectedOrderStatusItemId = $orderStatusItemId ? (int) $orderStatusItemId : null;

        if ($type === 'quote') {
            $this->selectedItem = QuotationRequest::with('items')->find($id);
            $this->selectedTracker = $this->selectedItem
                ? $trackerService->findBySource('quotation_requests', $this->selectedItem->id)?->load($trackerService->defaultRelations())
                : null;
        } else {
            $orderStatus = SaleOrder::query()->with('orderStatus.items')->find($id)?->orderStatus;
            if ($orderStatus?->items->isNotEmpty()) {
                app(OrderStatusService::class)->recalculate($orderStatus);
            }

            $this->selectedItem = SaleOrder::with([
                'items',
                'orderStatus.items.product',
                'orderStatus.items.supplier',
                'orderStatus.items.saleOrderItem',
                'orderStatus.events.user',
                'orderStatus.tracker',
            ])->find($id);
            $tracker = $this->selectedItem
                ? ($trackerService->findBySource('sale_orders', $this->selectedItem->id) ?: $this->selectedItem->orderStatus?->tracker)
                : null;
            $this->selectedTracker = $tracker?->load($trackerService->defaultRelations());
        }

        if (! $this->selectedItem) {
            $this->errorMessage = 'La orden solicitada ya no está disponible.';

            return;
        }

        $this->itemNotes = $this->selectedItem->notes ?? '';
        $this->trackerComment = '';
        $this->attachmentComment = '';
        $this->trackerAttachment = null;
        $this->showModal = true;
    }

    public function updatedItemNotes($value)
    {
        abort_unless(auth()->user()?->can('tracker.comment'), 403);

        if ($this->selectedItem) {
            $this->selectedItem->notes = $value;
            $this->selectedItem->save();
        }
    }

    #[Computed]
    public function itemNotesAuthor()
    {
        if (! $this->selectedItem || ! $this->selectedItem->notes) {
            return null;
        }

        $log = AuditLog::where('entity_type', get_class($this->selectedItem))
            ->where('entity_id', $this->selectedItem->id)
            ->where('metadata', 'like', '%"notes"%')
            ->latest()
            ->first();

        if ($log) {
            return (object) [
                'name' => $log->user->name ?? 'Sistema',
                'date' => $log->created_at->format('d/m/Y H:i'),
            ];
        }

        return (object) [
            'name' => 'Sistema',
            'date' => $this->selectedItem->created_at->format('d/m/Y H:i'),
        ];
    }

    public function openConfirmModal()
    {
        abort_unless(auth()->user()?->can('order_approval.view'), 403);

        if ($this->itemType !== 'quote' || ! $this->selectedItem) {
            return;
        }

        $approved = $this->selectedItem->items->where('is_approved', true);
        $excluded = $this->selectedItem->items->where('is_approved', false);

        if ($approved->isEmpty()) {
            $this->errorMessage = 'Debes aprobar al menos una partida para convertir.';

            return;
        }

        $this->confirmSummary = [
            'items_count' => $approved->count(),
            'excluded_count' => $excluded->count(),
            'total' => $approved->sum('line_total'),
            'client' => $this->selectedItem->quote_to_name,
        ];

        $this->approvalReason = '';
        $this->showConfirmModal = true;
    }

    public function requestForeignOrderApproval(OrderTrackerService $trackerService)
    {
        abort_unless(auth()->user()?->can('supplier_bid.send_to_approval'), 403);

        if ($this->itemType !== 'quote' || ! $this->selectedItem) {
            return;
        }

        try {
            $approval = $trackerService->requestForeignOrderApproval($this->selectedItem->fresh('items'), auth()->user(), $this->approvalReason ?: null);
            $this->selectedItem = $this->selectedItem->fresh('items');
            $this->selectedTracker = $approval->tracker->fresh($trackerService->defaultRelations());
            $this->showConfirmModal = false;
            session()->flash('message', "Solicitud de aprobacion enviada. Tracker {$this->selectedTracker->tracking_code} quedo pendiente.");
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = \App\Support\UserMessage::error($e, 'No se pudo solicitar la aprobación. Revisa el estado de la orden.');
        }
    }

    public function addTrackerComment(OrderTrackerService $trackerService)
    {
        abort_unless(auth()->user()?->can('tracker.comment'), 403);

        if (! $this->selectedTracker || trim($this->trackerComment) === '') {
            return;
        }

        $this->validate([
            'trackerComment' => ['required', 'string', 'max:2000'],
            'commentVisibility' => ['required', 'in:internal,customer'],
        ]);

        $trackerService->addComment($this->selectedTracker, $this->trackerComment, $this->commentVisibility);
        $this->trackerComment = '';
        $this->selectedTracker = $this->selectedTracker->fresh($trackerService->defaultRelations());
    }

    public function uploadTrackerAttachment(OrderTrackerService $trackerService)
    {
        abort_unless(auth()->user()?->can('tracker.attach_files'), 403);

        if (! $this->selectedTracker) {
            return;
        }

        $this->validate([
            'trackerAttachment' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            'attachmentPhase' => ['required', 'string', 'max:80'],
            'attachmentComment' => ['nullable', 'string', 'max:1000'],
        ]);

        $sourceModule = $this->itemType === 'quote' ? 'quotation_requests' : 'sale_orders';
        $trackerService->addAttachment($this->selectedTracker, $this->trackerAttachment, $this->attachmentPhase, $this->attachmentComment ?: null, null, $sourceModule, $this->selectedItem?->id);

        $this->trackerAttachment = null;
        $this->attachmentComment = '';
        $this->selectedTracker = $this->selectedTracker->fresh($trackerService->defaultRelations());
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedItem = null;
        $this->selectedTracker = null;
        $this->selectedOrderStatusItemId = null;
        $this->errorMessage = '';
    }

    public function convertToOrder(ConvertQuotationToSaleOrderService $service)
    {
        $this->requestForeignOrderApproval(app(OrderTrackerService::class));
    }
}
