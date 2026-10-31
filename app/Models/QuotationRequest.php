<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property numeric-string|float|int $total */
class QuotationRequest extends Model
{
    use HasFactory;
    use LogsActivity;

    // ── Flujo Comercial: Solicitud → Licitación → Oferta → Aprobación ─────────

    // Fase 1: Solicitud Cliente
    const STATUS_RECEIVED = 'recibida';

    const STATUS_IN_REVIEW = 'en_revision';

    const STATUS_READY_FOR_BID = 'lista_para_licitar';

    const STATUS_CANCELLED = 'cancelada';

    // Fase 2: Licitación Proveedor
    const STATUS_PENDING_BID = 'pendiente_licitacion';

    const STATUS_RFQ_GENERATED = 'rfq_generada';

    const STATUS_SENT_TO_SUPPLIER = 'enviada_proveedor';

    const STATUS_SUPPLIER_RESPONSE_PENDING = 'proveedor_respuesta_pendiente';

    const STATUS_SUPPLIER_RESPONSE_RECEIVED = 'respuesta_recibida';

    const STATUS_SUPPLIER_SELECTED = 'proveedor_seleccionado';

    // Fase 3: Oferta Cliente
    const STATUS_CLIENT_OFFER_GENERATED = 'oferta_generada';

    const STATUS_CLIENT_OFFER_SENT = 'oferta_enviada_cliente';

    const STATUS_CLIENT_RESPONSE_PENDING = 'pendiente_respuesta_cliente';

    const STATUS_OFFER_DRAFT = 'draft';

    const STATUS_OFFER_PREVIEW_READY = 'preview_ready';

    const STATUS_PENDING_ADMIN_REVIEW = 'pending_admin_review';

    const STATUS_ADMIN_CHANGES_REQUESTED = 'admin_changes_requested';

    const STATUS_ADMIN_REJECTED = 'admin_rejected';

    const STATUS_APPROVED_TO_SEND = 'approved_to_send';

    const STATUS_FINAL_PDF_GENERATED = 'final_pdf_generated';

    const STATUS_SENT_TO_CUSTOMER = 'sent_to_customer';

    const STATUS_CUSTOMER_ACCEPTED_TOTAL = 'customer_accepted_total';

    const STATUS_CUSTOMER_ACCEPTED_PARTIAL = 'customer_accepted_partial';

    const STATUS_CUSTOMER_REJECTED = 'customer_rejected';

    const STATUS_CUSTOMER_CHANGES_REQUESTED = 'customer_changes_requested';

    const STATUS_REVISED_AFTER_CUSTOMER_CHANGE = 'revised_after_customer_change';

    const STATUS_CLOSED = 'closed';

    // Fase 4: Aprobación Cliente
    const STATUS_APPROVED_TOTAL = 'aprobada_total';

    const STATUS_APPROVED_PARTIAL = 'aprobada_parcial';

    const STATUS_REJECTED = 'rechazada';

    const STATUS_REQUIRES_ADJUSTMENT = 'requiere_ajuste';

    const STATUS_CLIENT_APPROVED_TOTAL = 'aprobada_cliente_total';

    const STATUS_CLIENT_APPROVED_PARTIAL = 'aprobada_cliente_parcial';

    const STATUS_CLIENT_REJECTED = 'rechazada_cliente';

    const STATUS_CLIENT_REQUIRES_ADJUSTMENT = 'requiere_ajuste_cliente';

    // ── Alias de compatibilidad (no usar en código nuevo) ────────────────────
    // STATUS_PENDING    = 'cliente_solicitud'  → eliminado (usar STATUS_RECEIVED)
    // STATUS_PROCESSING = 'cotizacion_proceso' → eliminado (usar STATUS_PENDING_BID)
    // STATUS_SENT       = 'enviado_cliente'    → eliminado (colisionaba con STATUS_CLIENT_OFFER_SENT)
    // STATUS_APPROVED   = 'aprobado_pago_oc'   → eliminado (usar STATUS_APPROVED_TOTAL)

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'delivery_date' => 'date',
        'request_quantity' => 'integer',
        'tax_percent' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'exchange_rate_date' => 'date',
        'exchange_rate_is_fallback' => 'boolean',
        'exchange_rate_is_stale' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    /** @return HasMany<QuotationRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationRequestItem::class);
    }

    public function exchangeRateApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exchange_rate_approved_by');
    }

    public function supplierBids(): HasMany
    {
        return $this->hasMany(SupplierBid::class);
    }

    /** @return HasMany<SupplierBidResponse, $this> */
    public function supplierBidResponses(): HasMany
    {
        return $this->hasMany(SupplierBidResponse::class);
    }

    /** @return HasMany<SupplierRfqDocument, $this> */
    public function supplierRfqDocuments(): HasMany
    {
        return $this->hasMany(SupplierRfqDocument::class);
    }

    /** @return HasMany<SupplierBidSelection, $this> */
    public function supplierBidSelections(): HasMany
    {
        return $this->hasMany(SupplierBidSelection::class);
    }

    /** @return HasMany<ClientOfferVersion, $this> */
    public function offerVersions(): HasMany
    {
        return $this->hasMany(ClientOfferVersion::class);
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function clientMachine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class);
    }

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return HasMany<PurchasePreparationItem, $this> */
    public function purchasePreparationItems(): HasMany
    {
        return $this->hasMany(PurchasePreparationItem::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'source_id')
            ->where('source_module', 'quotation_requests');
    }

    public function clientApprovals(): HasMany
    {
        return $this->hasMany(ClientApproval::class);
    }

    /** @return HasMany<ClientOfferReview, $this> */
    public function offerReviews(): HasMany
    {
        return $this->hasMany(ClientOfferReview::class);
    }

    public function clientOfferChangeRequests(): HasMany
    {
        return $this->hasMany(ClientOfferChangeRequest::class);
    }

    public function latestOfferReview()
    {
        return $this->hasOne(ClientOfferReview::class)->latestOfMany();
    }
}
