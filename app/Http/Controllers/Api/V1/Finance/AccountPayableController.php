<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finanzas\Api\Queries\FinanceRecordScope;
use App\Domain\Finanzas\Api\Support\ApiPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Finance\AccountPayableResource;
use App\Models\AccountPayable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountPayableController extends Controller
{
    public function index(Request $request, FinanceRecordScope $scope): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AccountPayableResource::collection(
            $scope->payables($user)->with('entity')->latest('id')->paginate(ApiPagination::perPage($request))
        );
    }

    public function show(AccountPayable $payable): AccountPayableResource
    {
        $this->authorize('viewDocument', $payable);

        return new AccountPayableResource($payable->load('entity'));
    }
}
