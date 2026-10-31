<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finanzas\Api\Queries\FinanceRecordScope;
use App\Domain\Finanzas\Api\Support\ApiPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Finance\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(Request $request, FinanceRecordScope $scope): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return PaymentResource::collection(
            $scope->payments($user)->latest('id')->paginate(ApiPagination::perPage($request))
        );
    }

    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('viewDocument', $payment);

        return new PaymentResource($payment);
    }
}
