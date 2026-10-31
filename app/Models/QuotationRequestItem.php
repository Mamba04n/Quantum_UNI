<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuotationRequestItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'supplier_unit_cost_original' => 'decimal:2',
        'supplier_unit_cost_nio' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'exchange_rate_date' => 'date',
        'exchange_rate_is_fallback' => 'boolean',
        'exchange_rate_is_stale' => 'boolean',
        'is_approved' => 'boolean',
        'has_warranty_extension' => 'boolean',
        'version' => 'integer',
    ];

    /** @return BelongsTo<QuotationRequest, $this> */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /** @return BelongsTo<ExchangeRate, $this> */
    public function exchangeRateRecord(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class, 'exchange_rate_id');
    }

    /** @return BelongsTo<User, $this> */
    public function exchangeRateApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exchange_rate_approved_by');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ClientMachine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class, 'client_machine_id');
    }

    /** @return BelongsTo<ClientMachine, $this> */
    public function clientMachine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class);
    }

    /** @return HasMany<SupplierBidItem, $this> */
    public function supplierBidItems(): HasMany
    {
        return $this->hasMany(SupplierBidItem::class);
    }

    /** @return HasMany<SupplierBidResponseItem, $this> */
    public function supplierBidResponseItems(): HasMany
    {
        return $this->hasMany(SupplierBidResponseItem::class);
    }

    /** @return HasOne<SupplierBidSelection, $this> */
    public function supplierBidSelection(): HasOne
    {
        return $this->hasOne(SupplierBidSelection::class);
    }

    /** @return BelongsTo<SupplierBidItem, $this> */
    public function selectedSupplierBidItem(): BelongsTo
    {
        return $this->belongsTo(SupplierBidItem::class, 'selected_supplier_bid_item_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function selectedSupplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'selected_supplier_id');
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }

    /** @return HasOne<PurchasePreparationItem, $this> */
    public function purchasePreparationItem(): HasOne
    {
        return $this->hasOne(PurchasePreparationItem::class);
    }
}
