<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finanzas\Api\Queries\FinanceRecordScope;
use App\Domain\Finanzas\Api\Support\ApiPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Finance\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountReceivableController extends Controller
{
    public function index(Request $request, FinanceRecordScope $scope): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AccountReceivableResource::collection(
            $scope->receivables($user)->with('entity')->latest('id')->paginate(ApiPagination::perPage($request))
        );
    }

    public function show(AccountReceivable $receivable): AccountReceivableResource
    {
        $this->authorize('viewDocument', $receivable);

        return new AccountReceivableResource($receivable->load('entity'));
    }
}
