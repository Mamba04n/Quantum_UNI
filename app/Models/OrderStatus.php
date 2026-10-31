<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderStatus extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'date',
        'estimated_arrival_date' => 'date',
        'total_amount' => 'decimal:2',
        'progress_percentage' => 'integer',
        'tracking_groups' => 'array',
    ];

    // Alias for backward compatibility with 300+ legacy calls
    public function getCurrentStatusAttribute()
    {
        return $this->attributes['administrative_status'] ?? null;
    }

    public function setCurrentStatusAttribute($value)
    {
        $this->attributes['administrative_status'] = $value;
    }

    public function getCurrentPhaseAttribute()
    {
        return $this->attributes['administrative_phase'] ?? null;
    }

    public function setCurrentPhaseAttribute($value)
    {
        $this->attributes['administrative_phase'] = $value;
    }

    /** @return BelongsTo<SaleOrder, $this> */
    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<OrderTracker, $this> */
    public function tracker(): BelongsTo
    {
        return $this->belongsTo(OrderTracker::class, 'order_tracker_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return HasMany<OrderStatusItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderStatusItem::class);
    }

    /** @return HasMany<OrderStatusDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(OrderStatusDocument::class);
    }

    /** @return HasMany<PurchaseOrderDocument, $this> */
    public function purchaseOrderDocuments(): HasMany
    {
        // Los documentos vigentes pertenecen a una OC concreta. Los registros
        // heredados sin OC eran PDFs consolidados y no deben usarse operativamente.
        return $this->hasMany(PurchaseOrderDocument::class)
            ->whereNotNull('purchase_order_id');
    }

    /** @return HasMany<ShipmentBatch, $this> */
    public function shipmentBatches(): HasMany
    {
        return $this->hasMany(ShipmentBatch::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->latest();
    }
}
