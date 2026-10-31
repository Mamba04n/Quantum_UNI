<?php

namespace App\Providers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ApprovalRequest;
use App\Models\ClientMachine;
use App\Models\Invoice;
use App\Models\OrderTracker;
use App\Models\Payment;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\StockMove;
use App\Models\Ticket;
use App\Policies\AccountPayablePolicy;
use App\Policies\AccountReceivablePolicy;
use App\Policies\CustomerRequestPolicy;
use App\Policies\DirectOrderPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\KardexPolicy;
use App\Policies\MachinePolicy;
use App\Policies\OrderApprovalPolicy;
use App\Policies\OrderTrackerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\SupplierBidPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        QuotationRequest::class => CustomerRequestPolicy::class,
        ApprovalRequest::class => OrderApprovalPolicy::class,
        SaleOrder::class => DirectOrderPolicy::class,
        ClientMachine::class => MachinePolicy::class,
        StockMove::class => KardexPolicy::class,
        OrderTracker::class => OrderTrackerPolicy::class,
        AccountPayable::class => AccountPayablePolicy::class,
        AccountReceivable::class => AccountReceivablePolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability, ?array $arguments = null) {
            if (! method_exists($user, 'hasPermissionTo')) {
                return null;
            }

            if ($user->hasPermissionTo('full_access')) {
                return true;
            }

            if (! empty($arguments)) {
                return null;
            }

            $knownPermissions = collect(config('rbac.permissions', []))
                ->flatMap(fn ($permissions) => array_keys($permissions))
                ->merge(array_keys(config('rbac.legacy_aliases', [])))
                ->all();

            if (in_array($ability, $knownPermissions, true)) {
                return $user->hasPermissionTo($ability);
            }

            return null;
        });

        foreach (config('rbac.permissions', []) as $permissions) {
            foreach (array_keys($permissions) as $permission) {
                Gate::define($permission, function ($user, ...$arguments) use ($permission) {
                    if (! $user->hasPermissionTo($permission)) {
                        return false;
                    }

                    $resource = $arguments[0] ?? null;

                    if (! $resource) {
                        return true;
                    }

                    return match ($permission) {
                        'customer_request.send_to_bid' => $resource instanceof QuotationRequest
                            ? app(CustomerRequestPolicy::class)->sendToBid($user, $resource)
                            : true,
                        'customer_request.mark_in_review' => $resource instanceof QuotationRequest
                            ? app(CustomerRequestPolicy::class)->markInReview($user, $resource)
                            : true,
                        'customer_request.mark_ready_for_bid' => $resource instanceof QuotationRequest
                            ? app(CustomerRequestPolicy::class)->markReadyForBid($user, $resource)
                            : true,
                        'supplier_bid.add_supplier_response' => $resource instanceof QuotationRequest
                            ? app(SupplierBidPolicy::class)->addSupplierResponse($user, $resource)
                            : true,
                        'supplier_bid.select_winner' => $resource instanceof QuotationRequest
                            ? app(SupplierBidPolicy::class)->selectWinner($user, $resource)
                            : true,
                        'supplier_bid.send_to_approval' => $resource instanceof QuotationRequest
                            ? app(SupplierBidPolicy::class)->sendToApproval($user, $resource)
                            : true,
                        'tracker.change_phase' => $resource instanceof OrderTracker
                            ? app(OrderTrackerPolicy::class)->changePhase($user, $resource)
                            : true,
                        'tracker.reopen_phase' => $resource instanceof OrderTracker
                            ? app(OrderTrackerPolicy::class)->reopenPhase($user, $resource)
                            : true,
                        default => true,
                    };
                });
            }
        }

        foreach (array_keys(config('rbac.legacy_aliases', [])) as $legacyPermission) {
            Gate::define($legacyPermission, fn ($user, ...$arguments) => $user->hasPermissionTo($legacyPermission));
        }
    }
}
