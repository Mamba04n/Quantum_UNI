<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finanzas\Api\Support\ApiPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Finance\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExchangeRateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermissionTo('finance.view'), 403);

        return ExchangeRateResource::collection(
            ExchangeRate::query()->latest('rate_date')->latest('id')->paginate(ApiPagination::perPage($request))
        );
    }
}
