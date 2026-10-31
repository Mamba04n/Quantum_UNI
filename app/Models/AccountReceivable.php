<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $collected_at
 * @property int|null $created_by
 * @property string|null $receivable_code
 * @property string|null $support_document_path
 * @property string|null $currency
 * @property numeric-string|null $exchange_rate
 */
class AccountReceivable extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'invoice_date' => 'date',
        'collected_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'lock_version' => 'integer',
    ];

    // Relación con Entidad (Cliente)
    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<SaleOrder, $this> */
    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sales_order_id');
    }

    /**
     * Compatibility link for historical records created before
     * sales_order_id was populated.
     *
     * @return HasOne<SaleOrder, $this>
     */
    public function saleOrderByReference(): HasOne
    {
        return $this->hasOne(SaleOrder::class, 'reference', 'invoice_number');
    }

    /** @return HasOne<QuotationRequest, $this> */
    public function quotationRequest(): HasOne
    {
        return $this->hasOne(QuotationRequest::class, 'sale_order_id', 'sales_order_id');
    }

    public function resolvedSaleOrder(): ?SaleOrder
    {
        return $this->saleOrder ?? $this->saleOrderByReference;
    }

    public function saleOrderNumber(): ?string
    {
        return $this->resolvedSaleOrder()?->reference;
    }

    public function isGeneratedFromOrder(): bool
    {
        return filled($this->sales_order_id);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Historial de abonos recibidos (pagos hechos a esta cuenta por cobrar)
    /** @return MorphMany<Payment, $this> */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getCollectedAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingBalance(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && now()->startOfDay()->gt($this->due_date) && $this->getRemainingBalance() > 0;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<FinanceChangeRequest, $this> */
    public function changeRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinanceChangeRequest::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<FinanceChangeRequest, $this> */
    public function pendingChangeRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FinanceChangeRequest::class)->where('status', FinanceChangeRequest::STATUS_PENDING);
    }

    public function markAsCollectedIfFullyCovered(): void
    {
        $this->forceFill([
            'status' => $this->getRemainingBalance() <= 0 ? 'pagado' : ($this->paid_amount > 0 ? 'parcial' : 'pendiente'),
            'collected_at' => $this->getRemainingBalance() <= 0 ? now() : $this->collected_at,
        ])->save();
    }
}
