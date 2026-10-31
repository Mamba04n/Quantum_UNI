<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $phone
 * @property string|null $ruc
 * @property int $account_payables_count
 * @property int $purchase_orders_count
 * @property int $supplier_bid_responses_count
 * @property-read Collection<int, EntityBankAccount> $bankAccounts
 */
class Entity extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'name', 'legal_name', 'type', 'contact_info', 'email',
        'phone_mobile', 'phone_home', 'machinery', 'part_types', 'rfc_ruc',
        'country', 'department', 'municipality', 'city', 'exact_address',
        'address', 'website', 'contact_name', 'contact_position', 'whatsapp',
        'status', 'quality_types', 'supplier_metadata', 'email_fingerprint',
        'tax_id_fingerprint',
    ];

    protected $casts = [
        'quality_types' => 'array',
        'supplier_metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountPayables()
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(ClientMachine::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EntityContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function saleOrders()
    {
        return $this->hasMany(SaleOrder::class, 'customer_id');
    }

    public function orderTrackers()
    {
        return $this->hasMany(OrderTracker::class, 'customer_id');
    }

    public function supplierBids(): HasMany
    {
        return $this->hasMany(SupplierBid::class, 'supplier_id');
    }

    public function supplierBidResponses()
    {
        return $this->hasMany(SupplierBidResponse::class, 'supplier_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class);
    }

    public function machineTypes(): BelongsToMany
    {
        return $this->belongsToMany(MachineType::class);
    }

    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class);
    }

    /** @return HasMany<EntityBankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EntityBankAccount::class);
    }
}
