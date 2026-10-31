<?php

use App\Http\Controllers\Testing\E2ETestingController;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'testing'])) {
    Route::prefix('testing/e2e')->name('testing.e2e.')->group(function (): void {
        Route::post('/setup', [E2ETestingController::class, 'setup'])->name('setup');
        Route::post('/setup-base', [E2ETestingController::class, 'setupBase'])->name('setup-base');
        Route::post('/cleanup', [E2ETestingController::class, 'cleanup'])->name('cleanup');
        Route::post('/create-customer', [E2ETestingController::class, 'createCustomer'])->name('create-customer');
        Route::post('/create-supplier', [E2ETestingController::class, 'createSupplier'])->name('create-supplier');
        Route::post('/create-product', [E2ETestingController::class, 'createProduct'])->name('create-product');
        Route::post('/create-customer-request-ready-for-bid', [E2ETestingController::class, 'createCustomerRequestReadyForBid'])->name('create-customer-request-ready-for-bid');
        Route::post('/create-supplier-bid-ready-for-response', [E2ETestingController::class, 'createSupplierBidReadyForResponse'])->name('create-supplier-bid-ready-for-response');
        Route::post('/create-client-offer-ready-for-review', [E2ETestingController::class, 'createClientOfferReadyForReview'])->name('create-client-offer-ready-for-review');
        Route::post('/create-admin-approval-pending', [E2ETestingController::class, 'createAdminApprovalPending'])->name('create-admin-approval-pending');
        Route::post('/create-order-tracker-with-status', [E2ETestingController::class, 'createOrderTrackerWithStatus'])->name('create-order-tracker-with-status');
        Route::post('/create-order-status-partial-delivery', [E2ETestingController::class, 'createOrderStatusPartialDelivery'])->name('create-order-status-partial-delivery');
        Route::post('/create-order-status-packing-list-flow', [E2ETestingController::class, 'createOrderStatusPackingListFlow'])->name('create-order-status-packing-list-flow');
        Route::post('/create-finance-cxp', [E2ETestingController::class, 'createFinanceCxp'])->name('create-finance-cxp');
        Route::post('/create-finance-cxc', [E2ETestingController::class, 'createFinanceCxc'])->name('create-finance-cxc');
        Route::post('/login-user-data', [E2ETestingController::class, 'loginUserData'])->name('login-user-data');
    });
}
