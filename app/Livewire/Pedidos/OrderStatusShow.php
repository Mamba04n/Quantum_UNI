<?php

namespace App\Livewire\Pedidos;

use App\Livewire\Pedidos\Seguimiento\Concerns\GestionaAvanceSeguimiento;
use App\Livewire\Pedidos\Seguimiento\Concerns\GestionaDocumentosSeguimiento;
use App\Livewire\Pedidos\Seguimiento\Concerns\GestionaTableroSeguimiento;
use App\Livewire\Pedidos\Seguimiento\Concerns\RenderizaSeguimientoOrden;
use App\Models\OrderStatus;
use App\Models\SaleOrder;
use App\Services\OrderStatusService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class OrderStatusShow extends Component
{
    use WithFileUploads;
    use GestionaTableroSeguimiento;
    use GestionaDocumentosSeguimiento;
    use GestionaAvanceSeguimiento;
    use RenderizaSeguimientoOrden;

    public OrderStatus $orderStatus;

    #[Url]
    public ?int $selectedItemId = null;

    public string $documentPhase = OrderStatusService::PHASE_PURCHASE;
    public string $documentType = 'purchase_order_pdf';

    #[Url]
    public string $activePhase = OrderStatusService::PHASE_PURCHASE;
    public $documentFile;
    public string $documentNotes = '';
    public string $itemSearch = '';
    public string $itemFilter = 'all';
    public $warehouseEvidenceFile;
    public $warehousePhotoFile;
    public string $warehouseCondition = 'bueno';
    public string $warehouseLocation = '';
    public int $quantity = 1;
    public float $unitCost = 0;
    public string $notes = '';
    public string $targetStatus = OrderStatusService::STATUS_WAITING_SUPPLIER;
    public string $supplierReference = '';
    public string $supplierContact = '';
    public ?string $supplierConfirmedAt = null;
    public ?string $supplierEta = null;
    public bool $showCancelConfirmation = false;
    public string $cancelReason = '';
    public bool $cancelConfirmation = false;
    public ?int $cancelSupplierId = null;
    public bool $showFullOrderCancelConfirmation = false;
    public string $fullOrderCancelReason = '';
    public bool $fullOrderCancelConfirmation = false;
    public bool $showReplacementForm = false;
    public string $replacementReason = '';
    public ?int $replacementSupplierId = null;
    public string $replacementSupplierName = '';
    public string $replacementSupplierContact = '';
    public string $replacementPartName = '';
    public string $replacementPartNumber = '';
    public int $replacementQuantity = 1;
    public float $replacementSupplierUnitCost = 0;
    public string $replacementSupplierCurrency = 'USD';
    public float $replacementClientUnitPrice = 0;
    public string $replacementWarranty = 'Sin garantía';
    public array $replacementDocuments = [];
    public bool $showWaitingForItems = false;
    public array $waitingForItemIds = [];
    public string $dispatchInvoiceNumber = '';
    public string $invoiceCustomerName = '';
    public string $invoiceCustomerRuc = '';
    public string $invoiceCustomerAddress = '';
    public string $invoiceCustomerPhone = '';
    public string $invoiceIssueDate = '';
    public string $invoiceDueDate = '';
    public string $invoiceCondition = 'contado';
    public string $invoiceSeller = '';
    public bool $invoiceApplyIva = true;
    public bool $invoiceApplyIr = false;
    public float $invoiceIvaRate = 15.0;
    public float $invoiceIrRate = 2.0;
    public array $selectedGroupItemIds = [];
    public array $itemGroups = [];
    public array $ungroupedPurchaseOrderIds = [];
    public ?string $selectedGroupKey = null;
    public int $groupSequence = 1;

    public function mount(SaleOrder $order, OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.view') || auth()->user()?->can('tracker.view'), 403);

        $this->orderStatus = $service
            ->ensureForSaleOrder($order, auth()->user())
            ->load(['saleOrder.customer', 'items.product', 'items.supplier', 'items.purchaseOrderItem.order.supplier', 'items.documents', 'items.events.user', 'shipmentBatches.supplier', 'shipmentBatches.items.orderStatusItem', 'documents.uploader', 'documents.generator', 'documents.item', 'purchaseOrderDocuments.purchaseOrder.supplier', 'purchaseOrderDocuments.sender', 'purchaseOrderDocuments.generator', 'purchaseOrderDocuments.approvalRequest', 'events.user']);
        $this->initializePurchaseOrderGroups();
        $this->activePhase = $this->phaseForCurrentStatus();
        $this->documentPhase = $this->activePhase;
        $this->documentType = $this->defaultDocumentTypeForPhase($this->activePhase);
        $this->setDefaultQuantityForPhase();
        $this->initInvoiceFormData();
    }
}
