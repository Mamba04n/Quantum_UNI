<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;

class FinanceDocumentController extends Controller
{
    public function __construct(private readonly PrivateFileResponse $files) {}

    public function __invoke(Request $request, string $type, int $id)
    {
        [$model, $path, $name] = match ($type) {
            'cxp' => $this->payable($id),
            'cxc' => $this->receivable($id),
            'invoice' => $this->invoice($id),
            'payment' => $this->payment($id),
            default => abort(404),
        };

        $this->authorize('viewDocument', $model);

        return $this->files->make($path, $request, $name);
    }

    /** @return array{AccountPayable, string|null, string} */
    private function payable(int $id): array
    {
        $model = AccountPayable::query()->findOrFail($id);

        return [$model, $model->support_document_path, 'CXP-'.$model->id.'-soporte.'.pathinfo($model->support_document_path ?? '', PATHINFO_EXTENSION)];
    }

    /** @return array{AccountReceivable, string|null, string} */
    private function receivable(int $id): array
    {
        $model = AccountReceivable::query()->findOrFail($id);

        return [$model, $model->support_document_path, 'CXC-'.$model->id.'-soporte.'.pathinfo($model->support_document_path ?? '', PATHINFO_EXTENSION)];
    }

    /** @return array{Invoice, string|null, string} */
    private function invoice(int $id): array
    {
        $model = Invoice::query()->findOrFail($id);

        return [$model, $model->invoice_file_path, 'FACT-'.$model->invoice_number.'.'.pathinfo($model->invoice_file_path ?? '', PATHINFO_EXTENSION)];
    }

    /** @return array{Payment, string|null, string} */
    private function payment(int $id): array
    {
        $model = Payment::query()->findOrFail($id);

        return [$model, $model->payment_evidence_path, 'PAY-'.$model->id.'-evidencia.'.pathinfo($model->payment_evidence_path ?? '', PATHINFO_EXTENSION)];
    }
}
