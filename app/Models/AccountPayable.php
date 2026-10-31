<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $paid_at
 * @property int|null $created_by
 * @property string|null $payable_code
 * @property string|null $support_document_path
 * @property string|null $currency
 * @property numeric-string|null $exchange_rate
 */
class AccountPayable extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'invoice_date' => 'date',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'lock_version' => 'integer',
    ];

    public function isGeneratedFromOrder(): bool
    {
        return filled($this->purchase_order_id);
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Compatibility link for historical records created before
     * purchase_order_id was populated.
     *
     * @return HasOne<PurchaseOrder, $this>
     */
    public function purchaseOrderByReference(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class, 'reference_number', 'invoice_number');
    }

    public function resolvedPurchaseOrder(): ?PurchaseOrder
    {
        return $this->purchaseOrder ?? $this->purchaseOrderByReference;
    }

    public function purchaseOrderNumber(): ?string
    {
        return $this->resolvedPurchaseOrder()?->reference_number;
    }

    public function resolvedSupplier(): ?Entity
    {
        return $this->resolvedPurchaseOrder()?->supplier ?? $this->supplier ?? $this->entity;
    }

    public function supplierName(): ?string
    {
        return $this->resolvedSupplier()?->name;
    }

    public function paymentRecipientName(): ?string
    {
        $supplier = $this->resolvedSupplier();
        if (! $supplier) {
            return null;
        }

        $activeAccounts = $supplier->bankAccounts
            ->where('is_active', true)
            ->values();

        if ($activeAccounts->count() === 1 && filled($activeAccounts->first()->account_holder)) {
            return trim((string) $activeAccounts->first()->account_holder);
        }

        return $supplier->legal_name ?: $supplier->name;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Payment, $this> */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getPaidAmount(): float
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

    public function markAsPaidIfFullyCovered(): void
    {
        $this->forceFill([
            'status' => $this->getRemainingBalance() <= 0 ? 'pagado' : ($this->paid_amount > 0 ? 'parcial' : 'pendiente'),
            'paid_at' => $this->getRemainingBalance() <= 0 ? now() : $this->paid_at,
        ])->save();
    }
}
