<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finanzas\Api\Queries\FinanceRecordScope;
use App\Domain\Finanzas\Api\Support\ApiPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Finance\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function index(Request $request, FinanceRecordScope $scope): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return InvoiceResource::collection(
            $scope->invoices($user)->with('entity')->latest('id')->paginate(ApiPagination::perPage($request))
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('viewDocument', $invoice);

        return new InvoiceResource($invoice->load('entity'));
    }
}
