<?php

namespace App\Services;

use App\Models\SupplierPriceItem;
use App\Models\SupplierPriceQuotation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierPriceBookService
{
    public function record(array $data, string $token, ?UploadedFile $attachment = null): SupplierPriceQuotation
    {
        Gate::authorize('create', SupplierPriceQuotation::class);
        $data = Validator::make(array_merge(['reference' => null, 'valid_until' => null, 'notes' => null], $data, ['submission_token' => $token, 'attachment' => $attachment]), [
            'submission_token' => ['required', 'uuid'],
            'supplier_id' => ['required', Rule::exists('entities', 'id')->where('type', 'proveedor')],
            'reference' => ['nullable', 'string', 'max:100'],
            'quoted_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:quoted_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,xlsx,xls,csv,txt'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.part_number' => ['required', 'string', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:1000'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,3'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,4'],
        ], [], [
            'supplier_id' => 'proveedor', 'reference' => 'referencia', 'quoted_at' => 'fecha de cotización',
            'valid_until' => 'vigencia', 'notes' => 'observaciones', 'attachment' => 'oferta original',
            'items' => 'partidas', 'items.*.part_number' => 'número de parte',
            'items.*.description' => 'descripción', 'items.*.unit' => 'unidad de medida',
            'items.*.quantity' => 'cantidad', 'items.*.unit_price' => 'precio unitario en USD',
        ])->validate();
        $path = null;
        try {
            return DB::transaction(function () use ($data, $token, $attachment, &$path) {
                // Serializes submissions by this user, including double-click retries.
                DB::table('users')->where('id', auth()->id())->lockForUpdate()->first();
                if ($existing = SupplierPriceQuotation::where('submission_token', $token)->first()) {
                    abort_unless($existing->created_by === auth()->id(), 403);

                    return $existing;
                }
                if ($attachment) {
                    $path = $attachment->store('supplier-price-quotations', 'private');
                    throw_unless($path, \RuntimeException::class, 'No se pudo guardar el archivo de respaldo.');
                }
                $quote = SupplierPriceQuotation::create([
                    'submission_token' => $token, 'supplier_id' => $data['supplier_id'],
                    'reference' => $data['reference'] ?: null, 'quoted_at' => $data['quoted_at'],
                    'valid_until' => $data['valid_until'] ?: null, 'currency' => 'USD',
                    'notes' => $data['notes'] ?: null, 'created_by' => auth()->id(),
                    'attachment_path' => $path,
                    'attachment_name' => $attachment ? mb_substr(basename($attachment->getClientOriginalName()), 0, 200) : null,
                ]);
                foreach ($data['items'] as $item) {
                    $quote->items()->create([
                        'part_number' => trim($item['part_number']), 'description' => trim($item['description']),
                        'quantity' => $item['quantity'], 'unit' => trim($item['unit']), 'unit_price' => $item['unit_price'],
                        // Never trust a total or an availability value supplied by the browser.
                        'total_price' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
                        'availability' => 'pending',
                    ]);
                }

                return $quote;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }
    }

    public function confirm(int $itemId, string $availability, string $notes, int $expectedVersion): void
    {
        Gate::authorize('confirmAvailability', SupplierPriceQuotation::class);
        Validator::make(compact('availability', 'notes'), [
            'availability' => ['required', Rule::in(['available', 'unavailable', 'pending'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        DB::transaction(function () use ($itemId, $availability, $notes, $expectedVersion) {
            $item = SupplierPriceItem::lockForUpdate()->findOrFail($itemId);
            if ($item->availability_version !== $expectedVersion) {
                throw ValidationException::withMessages(['availability' => 'Otro usuario actualizó esta disponibilidad. Abre de nuevo el registro.']);
            }
            $item->update([
                'availability' => $availability, 'availability_notes' => $notes ?: null,
                'availability_checked_at' => now(), 'availability_checked_by' => auth()->id(),
                'availability_version' => $item->availability_version + 1,
            ]);
        });
    }
}
