<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;

class DocumentoFinanzasController extends Controller
{
    public function __construct(private readonly PrivateFileResponse $files) {}

    public function download(string $type, int $id, Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        [$model, $path, $name] = match ($type) {
            'cxp' => $this->cxp($id),
            'cxc' => $this->cxc($id),
            'invoice' => $this->invoice($id),
            'payment' => $this->payment($id),
            default => abort(404),
        };

        $this->authorize('viewDocument', $model);

        return $this->files->make($path, $request, $name);
    }

    private function cxp(int $id): array
    {
        $model = AccountPayable::findOrFail($id);

        return [$model, $model->support_document_path, 'CXP-'.$model->id.'-soporte.'.pathinfo($model->support_document_path ?? '', PATHINFO_EXTENSION)];
    }

    private function cxc(int $id): array
    {
        $model = AccountReceivable::findOrFail($id);

        return [$model, $model->support_document_path, 'CXC-'.$model->id.'-soporte.'.pathinfo($model->support_document_path ?? '', PATHINFO_EXTENSION)];
    }

    private function invoice(int $id): array
    {
        $model = Invoice::findOrFail($id);

        return [$model, $model->invoice_file_path, 'FACT-'.$model->invoice_number.'.'.pathinfo($model->invoice_file_path ?? '', PATHINFO_EXTENSION)];
    }

    private function payment(int $id): array
    {
        $model = Payment::findOrFail($id);

        return [$model, $model->payment_evidence_path, 'PAY-'.$model->id.'-evidencia.'.pathinfo($model->payment_evidence_path ?? '', PATHINFO_EXTENSION)];
    }

    public function downloadChangeRequestSupport(\App\Models\FinanceChangeRequest $changeRequest, Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $path = $changeRequest->support_document_path ?? ($changeRequest->original_data['support_document_path'] ?? null);
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('private')->exists($path), 404);

        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $filename = 'MODIF-'.$changeRequest->id.'-soporte.'.$ext;

        return $this->files->make($path, $request, $filename);
    }
}
