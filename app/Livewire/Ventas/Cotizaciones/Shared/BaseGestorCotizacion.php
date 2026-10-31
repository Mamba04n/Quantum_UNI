<?php

namespace App\Livewire\Ventas\Cotizaciones\Shared;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\ApprovalRequest;
use App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers\ClientOfferHelpers;
use App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers\ManagesClientOfferForm;
use App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers\ManagesClientOfferItems;
use App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers\ManagesClientOfferBulkImport;
use App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers\ManagesClientOfferStatus;
use App\Livewire\Ventas\Concerns\QuotationProcess\ManagesCommercialWorkflow;
use App\Livewire\Ventas\Concerns\QuotationProcess\ManagesProcessComments;
use App\Livewire\Ventas\Concerns\QuotationProcess\ManagesPurchasePreparationFlow;
use App\Livewire\Ventas\Concerns\QuotationProcess\ManagesQuotationCancellation;
use App\Livewire\Ventas\Concerns\QuotationProcess\ManagesQuotationNavigation;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidForm;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidBulkImport;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidSelectionFlow;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidSuppliers;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\PersistsSupplierBidResponses;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierRfq\SupplierRfqHelpers;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierRfq\ManagesSupplierRfqForm;
use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierRfq\ManagesSupplierRfqStatus;
use App\Livewire\Ventas\Concerns\QuotationProcess\RendersQuotationProcess;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\ExchangeRateService;
use App\Services\PdfService;
use App\Services\InventoryReservationService;
use App\Services\OrderTrackerService;
use App\Services\PurchasePreparationService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidResponseService;
use App\Models\SupplierRfqDocument;
use App\Services\SupplierRfqReviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

abstract class BaseGestorCotizacion extends Component
{
    use WithFileUploads;
    use ClientOfferHelpers;
    use ManagesClientOfferForm;
    use ManagesClientOfferItems;
    use ManagesClientOfferBulkImport;
    use ManagesClientOfferStatus;
    use ManagesCommercialWorkflow;
    use ManagesProcessComments;
    use ManagesPurchasePreparationFlow;
    use ManagesQuotationCancellation;
    use ManagesQuotationNavigation;
    use ManagesSupplierBidForm;
    use ManagesSupplierBidBulkImport;
    use ManagesSupplierBidSelectionFlow;
    use ManagesSupplierBidSuppliers;
    use PersistsSupplierBidResponses;
    use \App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesPreliquidation;
    use SupplierRfqHelpers;
    use ManagesSupplierRfqForm;
    use ManagesSupplierRfqStatus;
    use \App\Livewire\Ventas\Concerns\QuotationProcess\ManagesQuotationItems;
    use RendersQuotationProcess;

    public string $activeTab = 'bid';
    public string $search = '';
    public string $stageFilter = '';
    public string $supplierFilter = '';
    public string $alertFilter = '';
    public string $supplierSearch = '';
    public string $changeSupplierReason = '';
    public array $selectedFinalSupplierQuote = [];
    public ?int $selectedQuoteId = null;
    public ?int $comparingOfferItemId = null;
    public bool $showDetail = false;
    public bool $showAllProductsInResponse = false;
    public string $selectedSupplierId = '';
    public string $supplierContactName = '';
    public string $supplierCurrency = 'USD';
    public string $supplierCurrencyExchangeRate = '0';
    public string $supplierPaymentTerms = '';
    public string $supplierDeliveryTime = '';
    public string $supplierWarranty = 'Sin garantía';
    public string $supplierValidUntil = '';
    public string $supplierNotes = '';
    public string $winnerReason = '';
    public string $responseSupplierName = '';
    public string $approvalReason = '';
    public array $supplierBidItems = [];
    public array $supplierBidItemVersions = [];
    public array $supplierBidExtraItems = [];
    public array $supplierBidDeletedItemIds = [];
    public array $supplierSelectionExclusionReasons = [];
    public int|string|null $expandedSupplierBidItemId = null;
    public ?int $activeSupplierBidResponseId = null;
    public int $bidStep = 1;
    public ?int $editingSupplierResponseId = null;
    public ?int $editingRfqDocumentId = null;
    public $supplierProformas = [];
    public array $supplierItemProformas = [];
    public string $newComment = '';
    public string $commentVisibility = 'internal';
    public string $companyIssuer = 'quantum';
    public string $clientOfferTaxPercent = '15';
    public string $offerValidity = '15 dias';
    public string $offerPaymentTerms = 'Contado';
    public string $offerDeliveryTime = '';
    public string $offerCurrency = 'USD';
    public string $offerNotes = '';
    public string $offerReviewNotes = '';
    public $clientOfferBulkFile = null;
    public string $rfqQuoteToName = 'Quantum Soluciones S.A. - Compras';
    public string $rfqConsigneeName = 'Quantum Soluciones S.A.';

    // Quick Supplier Quote properties
    public ?int $activePurchasePreparationItemId = null;
    public ?int $activeQuickQuoteItemId = null;
    public string $quickQuoteSupplierName = '';
    public string $quickQuoteProductName = '';
    public string $quickQuoteUnitCost = '';
    public string $quickQuoteCurrency = 'USD';
    public string $purchaseOrderCurrency = 'USD';
    public string $quickQuoteWarranty = 'Sin garantía';
    public string $quickQuoteTaxPercent = '15';
    public string $rfqConsigneeContactName = 'Departamento de Compras';
    public string $rfqConsigneeComment = '';
    public string $rfqConsigneeUrgentComment = '';
    public string $rfqConsigneeAddress1 = 'Rotonda Jean Paul Genie 100m al Este, 50m al Sur';
    public string $rfqConsigneeAddress2 = 'Edificio Quantum, Managua';
    public string $rfqConsigneeCountry = 'Nicaragua';
    public string $rfqConsigneeCity = 'Managua';
    public string $rfqConsigneePhone = '+505 7669 8164';
    public string $rfqConsigneeEmails = 'compras@quantumsolucionesnic.com, operaciones@quantumsolucionesnic.com';
    public string $rfqResponseRequirements = "Precio unitario y total.\nMoneda de la oferta.\nTiempo de entrega estimado.\nCondiciones de garantia.\nForma de pago requerida.\nTiempo de validez de la oferta.";
    public string $rfqSpecifications = '';
    public array $selectedRfqItemIds = [];
    public array $rfqItemSpecifications = [];
    public array $rfqItemQuantities = [];
    public array $editingRfqItemQuantities = [];
    public string $customerChangeType = 'comentario_general';
    public string $customerChangeReason = '';
    public string $customerChangeComment = '';
    public array $customerChangeAffectedItems = [];
    public $customerChangeAttachment = null;
    public string $newOfferItemName = '';
    public string $newOfferItemPartNumber = '';
    public string $newOfferItemBrand = '';
    public string $newOfferItemModel = '';
    public string $newOfferItemQuantity = '1';
    public string $newOfferSupplierId = '';
    public string $newOfferSupplierName = '';
    public string $newOfferSupplierContact = '';
    public string $newOfferSupplierUnitCost = '0';
    public string $newOfferSupplierCurrency = 'USD';
    public string $newOfferSupplierWarranty = 'Sin garantía';
    public array $newOfferSupplierDocuments = [];
    public string $newOfferItemUnitPrice = '0';
    public bool $showCancelQuoteModal = false;
    public string $cancelQuoteCategory = 'proveedor_sin_stock';
    public string $cancelQuoteReason = '';

    public array $cancelQuoteCategories = [
        'proveedor_sin_stock' => 'Proveedor sin stock',
        'pieza_inexistente' => 'Pieza inexistente',
        'dificil_de_conseguir' => 'Dificil de conseguir',
        'precio_no_viable' => 'Precio no viable',
        'cliente_no_continua' => 'Cliente no continua',
        'datos_insuficientes' => 'Datos insuficientes',
        'duplicada' => 'Solicitud duplicada',
        'otro' => 'Otro motivo',
    ];

    public array $confirmSummary = [
        'items_count' => 0,
        'excluded_count' => 0,
        'total' => 0,
        'client' => '',
    ];

    protected $listeners = ['refresh' => '$refresh'];

    public array $tabs = [
        'bid'      => ['label' => 'Licitacion Proveedor',    'permission' => null],
        'offer'    => ['label' => 'Oferta Cliente',          'permission' => null],
        'approval' => ['label' => 'Aprobacion Cliente',      'permission' => null],
    ];

    public array $flowStages = [
        'supplier_bid' => [
            'label' => 'Licitacion Proveedor',
            'visual' => 'En licitacion',
            'action' => 'Preparar RFQ',
            'tab' => 'bid',
        ],
        'supplier_response' => [
            'label' => 'Preliquidación',
            'visual' => 'Preliquidación',
            'action' => 'Registrar respuesta proveedor',
            'tab' => 'bid',
        ],
        'client_offer' => [
            'label' => 'Oferta Cliente',
            'visual' => 'Oferta cliente',
            'action' => 'Generar y enviar oferta',
            'tab' => 'offer',
        ],
        'customer_approval' => [
            'label' => 'Aprobacion Cliente',
            'visual' => 'Esperando respuesta cliente',
            'action' => 'Registrar decision cliente',
            'tab' => 'approval',
        ],
        'purchase_preparation' => [
            'label' => 'Preparacion Orden de Compra',
            'visual' => 'Preparando orden de compra',
            'action' => 'Confirmar proveedor final',
            'tab' => 'approval',
        ],
        'purchase_order_generated' => [
            'label' => 'Aprobacion Administrativa de OC',
            'visual' => 'Esperando aprobacion admin',
            'action' => 'Ver estado',
            'tab' => 'approval',
        ],
    ];

    public function getQuoteUrl(\App\Models\QuotationRequest $quote): string
    {
        $stageKey = $this->stageKeyForQuote($quote);
        $tab = $this->flowStages[$stageKey]['tab'] ?? 'bid';

        $routePrefix = match($tab) {
            'request' => 'sales.quotations.solicitud',
            'bid' => 'sales.quotations.licitacion',
            'offer' => 'sales.quotations.oferta',
            'approval' => 'sales.quotations.aprobacion',
            default => 'sales.quotations.kanban',
        };

        return route($routePrefix, ['quoteId' => $quote->id]);
    }
}
