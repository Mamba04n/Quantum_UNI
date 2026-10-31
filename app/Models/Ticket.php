<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const TYPE_SUPPORT = 'soporte_tecnico';
    public const TYPE_COMPLAINT = 'reclamo';
    public const TYPE_QUESTION = 'consulta';
    public const TYPE_PURCHASE = 'solicitud_compra';

    public const STATUS_OPEN = 'abierto';
    public const STATUS_IN_PROGRESS = 'en_proceso';
    public const STATUS_WAITING_CUSTOMER = 'esperando_cliente';
    public const STATUS_RESOLVED = 'resuelto';
    public const STATUS_CLOSED = 'cerrado';

    public const PRIORITY_LOW = 'baja';
    public const PRIORITY_MEDIUM = 'media';
    public const PRIORITY_HIGH = 'alta';
    public const PRIORITY_URGENT = 'urgente';

    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_admin_message_at' => 'datetime',
    ];

    public static array $typeLabels = [
        self::TYPE_SUPPORT => 'Soporte técnico',
        self::TYPE_COMPLAINT => 'Reclamo',
        self::TYPE_QUESTION => 'Consulta',
        self::TYPE_PURCHASE => 'Solicitud de compra',
    ];

    public static array $priorityLabels = [
        self::PRIORITY_LOW => 'Baja',
        self::PRIORITY_MEDIUM => 'Media',
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    public static array $statusLabels = [
        self::STATUS_OPEN => 'Abierto',
        self::STATUS_IN_PROGRESS => 'En proceso',
        self::STATUS_WAITING_CUSTOMER => 'Esperando cliente',
        self::STATUS_RESOLVED => 'Resuelto',
        self::STATUS_CLOSED => 'Cerrado',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function clientMachine(): BelongsTo
    {
        return $this->belongsTo(ClientMachine::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }

    public function stockMove(): BelongsTo
    {
        return $this->belongsTo(StockMove::class, 'related_stock_move_id');
    }

    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TicketNote::class)->latest();
    }
}
