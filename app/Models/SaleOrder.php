<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class SaleOrder extends Model
{
    use HasFactory, LogsActivity, Notifiable;

    protected $guarded = []; // Permitir asignacion masiva en este modulo controlado.

    protected $casts = [
        'date' => 'date',
        'invoice_date' => 'date',
        'approved_at' => 'datetime',
        'generated_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    /** @return HasMany<SaleOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class);
    }

    /** @return BelongsTo<Entity, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    public function clientOffer()
    {
        return $this->belongsTo(ClientOfferVersion::class, 'client_offer_version_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function sourceQuotationRequest()
    {
        return $this->hasOne(QuotationRequest::class);
    }

    /** @return HasOne<OrderStatus, $this> */
    public function orderStatus(): HasOne
    {
        return $this->hasOne(OrderStatus::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'sales_order_id');
    }

    /** @return HasOne<AccountReceivable, $this> */
    public function accountReceivable(): HasOne
    {
        return $this->hasOne(AccountReceivable::class, 'sales_order_id');
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::created(function ($order) {
            if (! empty($order->client_email)) {
                try {
                    $order->notify(new OrderStatusUpdated($order));
                } catch (\Exception $e) {
                    // Ignorar error de correo para no romper la creación del pedido
                    Log::error('SMTP Error on created: '.$e->getMessage());
                }
            }
        });

        static::updated(function ($order) {
            if ($order->isDirty('status') && ! empty($order->client_email)) {
                try {
                    $order->notify(new OrderStatusUpdated($order));
                } catch (\Exception $e) {
                    // Ignorar error de correo para no romper la actualización del pedido
                    Log::error('SMTP Error on updated: '.$e->getMessage());
                }
            }
        });
    }

    /**
     * Route notifications for the mail channel.
     *
     * @return array<string, string>|string
     */
    public function routeNotificationForMail($notification)
    {
        // Return email address only if it exists
        return $this->client_email;
    }
}
