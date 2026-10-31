<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRfqDocument extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_ADMIN_REVIEW = 'pending_admin_review';

    public const STATUS_ADMIN_CHANGES_REQUESTED = 'admin_changes_requested';

    public const STATUS_ADMIN_APPROVED = 'admin_approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_PDF_GENERATED = self::STATUS_GENERATED;

    public const STATUS_SENT_TO_SUPPLIER = 'sent_to_supplier';

    public const STATUS_SUPPLIER_RESPONSE_RECEIVED = 'supplier_response_received';

    public const STATUS_NO_RESPONSE = 'no_response';

    public const DEFAULT_QUOTE_TO_NAME = 'Admin';

    public const DEFAULT_RESPONSE_REQUIREMENTS = "Precio unitario y total.\nMoneda de la oferta.\nTiempo de entrega estimado.\nCondiciones de garantia.\nForma de pago requerida.\nTiempo de validez de la oferta.";

    protected $guarded = [];

    protected $casts = [
        'admin_reviewed_at' => 'datetime',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'selected_item_ids' => 'array',
        'item_specifications' => 'array',
        'rfq_item_quantities' => 'array',
    ];

    /** @return BelongsTo<QuotationRequest, $this> */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /** @return BelongsTo<SupplierBid, $this> */
    public function supplierBid(): BelongsTo
    {
        return $this->belongsTo(SupplierBid::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function adminReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING_ADMIN_REVIEW => 'Pendiente de PDF',
            self::STATUS_ADMIN_CHANGES_REQUESTED => 'Requiere ajuste',
            self::STATUS_ADMIN_APPROVED => 'Listo para PDF',
            self::STATUS_REJECTED => 'Requiere ajuste',
            self::STATUS_GENERATED => 'PDF generado',
            self::STATUS_SENT_TO_SUPPLIER => 'Enviado al proveedor',
            self::STATUS_SUPPLIER_RESPONSE_RECEIVED => 'Respuesta recibida',
            self::STATUS_NO_RESPONSE => 'Sin respuesta',
            default => str($this->status)->replace('_', ' ')->headline()->toString(),
        };
    }

    public function getEffectiveRfqNumberAttribute(): string
    {
        if (filled($this->rfq_number)) {
            return $this->rfq_number;
        }

        return sprintf(
            'RFQ-%s-%05d-%04d',
            now()->format('y'),
            (int) $this->quotation_request_id,
            $this->supplier_id ? sprintf('%04d', (int) $this->supplier_id) : 'GEN'
        );
    }

    public function getEffectiveResponseRequirementsAttribute(): string
    {
        return filled($this->rfq_response_requirements)
            ? trim((string) $this->rfq_response_requirements)
            : self::DEFAULT_RESPONSE_REQUIREMENTS;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_ADMIN_REVIEW,
            self::STATUS_ADMIN_CHANGES_REQUESTED,
            self::STATUS_ADMIN_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_GENERATED,
            self::STATUS_SENT_TO_SUPPLIER,
            self::STATUS_NO_RESPONSE,
            self::STATUS_SUPPLIER_RESPONSE_RECEIVED,
        ], true);
    }
}
