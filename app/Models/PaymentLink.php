<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLink extends Model
{
    protected $guarded = [];

    protected $hidden = ['public_token', 'internal_note', 'issuance_key', 'issuance_fingerprint'];

    protected $casts = ['expires_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime', 'total' => 'decimal:2', 'exchange_rate' => 'decimal:6'];

    public function items()
    {
        return $this->hasMany(PaymentLinkItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function receivable()
    {
        return $this->belongsTo(AccountReceivable::class, 'account_receivable_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function publicUrl(): string
    {
        return route('public.payment.show', $this->public_token);
    }

    public function effectiveStatus(): string
    {
        return $this->status === 'pending' && $this->expires_at->isPast() ? 'expired' : $this->status;
    }
}
