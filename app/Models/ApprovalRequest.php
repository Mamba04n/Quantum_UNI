<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const TYPE_FOREIGN_ORDER = 'foreign_order_conversion';

    public const TYPE_CLIENT_OFFER_APPROVAL = 'client_offer_approval';

    public const TYPE_INTERNAL_PURCHASE = 'internal_purchase';

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** @return BelongsTo<OrderTracker, $this> */
    public function tracker(): BelongsTo
    {
        return $this->belongsTo(OrderTracker::class, 'tracker_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<QuotationRequest, $this> */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class, 'source_id');
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'approval_request_id');
    }
}
