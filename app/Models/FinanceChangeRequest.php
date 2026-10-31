<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceChangeRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_CXP = 'cxp';
    public const TYPE_CXC = 'cxc';

    protected $guarded = [];

    protected $casts = [
        'original_data' => 'array',
        'requested_data' => 'array',
        'changes_diff' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountPayable, $this> */
    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class);
    }

    /** @return BelongsTo<AccountReceivable, $this> */
    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accountCode(): string
    {
        if ($this->type === self::TYPE_CXP) {
            return $this->accountPayable?->payable_code ?: ('CXP #' . $this->account_payable_id);
        }

        return $this->accountReceivable?->receivable_code ?: ('CXC #' . $this->account_receivable_id);
    }

    public function partyName(): string
    {
        if ($this->type === self::TYPE_CXP) {
            return $this->accountPayable?->resolvedSupplier()?->name ?? $this->accountPayable?->entity?->name ?? '—';
        }

        return $this->accountReceivable?->entity?->name ?? '—';
    }

    public function accountConcept(): string
    {
        if ($this->type === self::TYPE_CXP) {
            return $this->accountPayable?->concept ?? 'Cuenta por pagar';
        }

        return $this->accountReceivable?->concept ?? 'Cuenta por cobrar';
    }
}
