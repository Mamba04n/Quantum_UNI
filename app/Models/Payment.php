<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string|null $payment_reference
 * @property string|null $payment_type
 * @property string|null $payment_evidence_path
 * @property int|null $accounts_payable_id
 * @property int|null $accounts_receivable_id
 * @property int|null $registered_by
 * @property string|null $notes
 * @property string|null $payment_code
 * @property int|null $invoice_id
 * @property string|null $currency
 */
class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'amount_original' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    protected $hidden = [
        'idempotency_key',
        'request_fingerprint',
        'reference_fingerprint',
        'evidence_sha256',
    ];

    /** @return MorphTo<Model, $this> */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<AccountReceivable, $this> */
    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class, 'accounts_receivable_id');
    }

    /** @return BelongsTo<AccountPayable, $this> */
    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'accounts_payable_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
