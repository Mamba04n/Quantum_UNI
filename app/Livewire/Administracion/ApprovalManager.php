<?php

namespace App\Livewire\Administracion;

use App\Domain\Ventas\FlujoTrabajo\Queries\ListPendingQuotationStageChanges;
use App\Domain\Ventas\FlujoTrabajo\Services\QuotationStageChangeService;
use App\Livewire\Administracion\Concerns\ManagesApprovalActions;
use App\Livewire\Administracion\Concerns\ManagesApprovalFinancials;
use App\Livewire\Administracion\Concerns\ManagesApprovalList;
use App\Livewire\Administracion\Concerns\ManagesApprovalOrderChanges;
use App\Models\ApprovalRequest;
use App\Models\ClientOfferReview;
use App\Models\FinanceChangeRequest;
use App\Models\OrderStatusItem;
use App\Models\QuotationStageChangeRequest;
use App\Models\SupplierRfqDocument;
use App\Services\OrderStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class ApprovalManager extends Component
{
    use ManagesApprovalActions;
    use ManagesApprovalFinancials;
    use ManagesApprovalList;
    use ManagesApprovalOrderChanges;

    public ?int $selectedApprovalId = null;

    public string $activeTab = 'summary';

    public string $rejectionReason = '';

    public string $decisionMode = 'reject';

    public bool $showDecisionModal = false;

    public bool $showOrderChangeConfirmation = false;

    public string $pendingOrderChangeType = '';

    public ?int $pendingOrderChangeItemId = null;

    public ?int $editingPurchasePreparationItemId = null;

    public array $selectedFinalSupplierQuote = [];

    public array $stageChangeReviewNotes = [];

    public array $manualSupplierId = [];

    public array $manualSupplierName = [];

    public array $manualSupplierCost = [];

    public array $manualSupplierCurrency = [];

    public array $manualSupplierDelivery = [];

    public string $changeSupplierReason = '';

    public string $managerSection = 'overview';

    private ListPendingQuotationStageChanges $stageChanges;

    public function boot(ListPendingQuotationStageChanges $stageChanges): void
    {
        $this->stageChanges = $stageChanges;
    }

    public function showOverview(): void
    {
        $this->selectedApprovalId = null;
        $this->managerSection = 'overview';
    }

    public function showDispatchAuthorizations(): void
    {
        abort_unless(auth()->user()?->can('dispatch_authorization.view'), 403);
        $this->selectedApprovalId = null;
        $this->managerSection = 'dispatch_authorizations';
    }

    public function showPurchaseApprovals(): void
    {
        $this->managerSection = 'purchase_orders';
    }

    public function authorizeDispatchItem(int $itemId, OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('dispatch_authorization.approve'), 403);

        $item = OrderStatusItem::query()
            ->where('status', OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED)
            ->findOrFail($itemId);

        $service->authorizePartialDispatch($item, auth()->user(), 'Autorizada desde el módulo administrativo de salidas parciales.');
        session()->flash('message', 'Salida parcial autorizada.');
    }

    public function approveStageChange(int $requestId, QuotationStageChangeService $service): void
    {
        abort_unless($this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve']), 403);
        try {
            $request = QuotationStageChangeRequest::findOrFail($requestId);
            $service->approve($request, auth()->user(), $this->stageChangeReviewNotes[$requestId] ?? null);
            unset($this->stageChangeReviewNotes[$requestId]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Retroceso aprobado y etapa actualizada.']);
            session()->flash('message', 'Retroceso aprobado y etapa actualizada.');
        } catch (ValidationException $exception) {
            $this->stageChangeError($this->validationMessage($exception));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->stageChangeError('No se pudo procesar la solicitud de cambio de etapa.');
        }
    }

    public function rejectStageChange(int $requestId, QuotationStageChangeService $service): void
    {
        abort_unless($this->canAny(['internal_approval.reject', 'approval.reject', 'order_approval.reject']), 403);
        $this->validate([
            "stageChangeReviewNotes.{$requestId}" => ['required', 'string', 'min:3', 'max:1000'],
        ], ["stageChangeReviewNotes.{$requestId}.required" => 'Indica el motivo del rechazo.']);

        try {
            $request = QuotationStageChangeRequest::findOrFail($requestId);
            $service->reject($request, auth()->user(), $this->stageChangeReviewNotes[$requestId]);
            unset($this->stageChangeReviewNotes[$requestId]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud de retroceso rechazada.']);
            session()->flash('message', 'Solicitud de retroceso rechazada.');
        } catch (ValidationException $exception) {
            $this->stageChangeError($this->validationMessage($exception));
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->stageChangeError('No se pudo procesar la solicitud de cambio de etapa.');
        }
    }

    public function render()
    {
        $canViewPurchaseApprovals = $this->canAny(['internal_approval.view', 'approval.view', 'order_approval.view']);

        $pendingApprovals = $canViewPurchaseApprovals
            ? $this->approvalListQuery()->where('status', ApprovalRequest::STATUS_PENDING)->latest()->get()
            : collect();

        $recentApprovals = $canViewPurchaseApprovals ? $this->approvalListQuery()
            ->whereIn('status', [
                ApprovalRequest::STATUS_APPROVED,
                ApprovalRequest::STATUS_REJECTED,
                ApprovalRequest::STATUS_CHANGES_REQUESTED,
            ])
            ->latest('updated_at')
            ->take(20)
            ->get() : collect();

        $selectedApproval = $this->selectedApprovalId
            ? ApprovalRequest::with($this->detailRelations())
                ->where(fn ($query) => $this->internalApprovalTypeFilter($query))
                ->find($this->selectedApprovalId)
            : null;

        $pendingDispatchItems = auth()->user()?->can('dispatch_authorization.view')
            ? OrderStatusItem::query()
                ->with(['orderStatus.saleOrder.customer', 'orderStatus.tracker', 'product', 'supplier'])
                ->where('status', OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED)
                ->latest('updated_at')
                ->get()
            : collect();

        $pendingStageChanges = $canViewPurchaseApprovals
            ? $this->stageChanges->handle()
            : collect();

        $approvalTypes = collect([
            [
                'key' => 'quotation_stage_changes',
                'label' => 'Cambios de etapa',
                'area' => 'Ventas',
                'description' => 'Retrocesos excepcionales solicitados desde la fase de cotización.',
                'count' => $pendingStageChanges->count(),
                'route' => route('admin.approvals', ['section' => 'stage-changes']),
                'visible' => $canViewPurchaseApprovals,
            ],
            [
                'key' => 'purchase_orders',
                'label' => 'Órdenes de compra',
                'area' => 'Compras',
                'description' => 'Revisión final de proveedor, costos y condiciones antes de generar la OC.',
                'count' => $pendingApprovals->count(),
                'route' => route('admin.approvals', ['section' => 'purchase-orders']),
                'visible' => $canViewPurchaseApprovals,
            ],
            [
                'key' => 'client_offers',
                'label' => 'Ofertas al cliente',
                'area' => 'Ventas',
                'description' => 'Ofertas comerciales pendientes de autorización antes de enviarlas.',
                'count' => auth()->user()?->can('client_offer_review.view')
                    ? ClientOfferReview::where('status', ClientOfferReview::STATUS_PENDING)->count() : 0,
                'route' => route('admin.offer-reviews'),
                'visible' => auth()->user()?->can('client_offer_review.view') ?? false,
            ],
            [
                'key' => 'supplier_rfqs',
                'label' => 'RFQ de proveedores',
                'area' => 'Compras',
                'description' => 'Documentos RFQ que requieren revisión antes de enviarse al proveedor.',
                'count' => auth()->user()?->can('rfq_review.view')
                    ? SupplierRfqDocument::where('status', SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW)->count() : 0,
                'route' => route('admin.rfq-reviews'),
                'visible' => auth()->user()?->can('rfq_review.view') ?? false,
            ],
            [
                'key' => 'dispatch_authorizations',
                'label' => 'Salidas parciales',
                'area' => 'Bodega',
                'description' => 'Solicitudes de Bodega pendientes de autorización para salida de Kardex.',
                'count' => $pendingDispatchItems->count(),
                'route' => route('admin.approvals', ['section' => 'dispatch-authorizations']),
                'visible' => auth()->user()?->can('dispatch_authorization.view') ?? false,
            ],
            [
                'key' => 'finance_changes',
                'label' => 'Cambios financieros',
                'area' => 'Finanzas',
                'description' => 'Modificaciones solicitadas sobre cuentas por cobrar o pagar.',
                'count' => $this->canViewFinanceApprovals()
                    ? FinanceChangeRequest::where('status', FinanceChangeRequest::STATUS_PENDING)->count() : 0,
                'route' => route('admin.finance-reviews'),
                'visible' => $this->canViewFinanceApprovals(),
            ],
        ])->where('visible', true)->values();

        return view('livewire.administracion.approval-manager', [
            'pendingApprovals' => $pendingApprovals,
            'recentApprovals' => $recentApprovals,
            'selectedApproval' => $selectedApproval,
            'approvedItems' => $selectedApproval ? $this->approvedItems($selectedApproval) : collect(),
            'excludedItems' => $selectedApproval ? $this->excludedItems($selectedApproval) : collect(),
            'supplierGroups' => $selectedApproval ? $this->supplierGroups($selectedApproval) : collect(),
            'financialSummary' => $selectedApproval ? $this->financialSummary($selectedApproval) : [],
            'documents' => $selectedApproval ? $this->documents($selectedApproval) : collect(),
            'existingPurchaseOrders' => $selectedApproval ? $this->existingPurchaseOrders($selectedApproval) : collect(),
            'suppliers' => $this->supplierOptions(),
            'canViewCosts' => $this->canViewCosts(),
            'canViewMargin' => $this->canViewMargin(),
            'canViewDocuments' => $this->canViewDocuments(),
            'canApproveInternal' => $this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve']),
            'canRequestInternalChanges' => $this->canAny(['internal_approval.request_changes', 'approval.request_changes', 'order_approval.request_changes']),
            'canRejectInternal' => $this->canAny(['internal_approval.reject', 'approval.reject', 'order_approval.reject']),
            'pendingDispatchItems' => $pendingDispatchItems,
            'canApproveDispatch' => auth()->user()?->can('dispatch_authorization.approve') ?? false,
            'approvalTypes' => $approvalTypes,
            'pendingStageChanges' => $pendingStageChanges,
            'canApproveStageChanges' => $this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve']),
            'canRejectStageChanges' => $this->canAny(['internal_approval.reject', 'approval.reject', 'order_approval.reject']),
        ]);
    }

    private function canViewFinanceApprovals(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->can('finance.view') || $user->can('full_access') || $user->hasRole('admin')));
    }

    private function validationMessage(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first()
            ?? 'No se pudo procesar la solicitud de cambio de etapa.');
    }

    private function stageChangeError(string $message): void
    {
        $this->dispatch('notify', ['type' => 'error', 'message' => $message]);
        session()->flash('error', $message);
    }
}
