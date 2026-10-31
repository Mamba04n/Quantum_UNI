<?php

namespace App\Domain\Pedidos\Logistics\Services;

use App\Models\OrderStatus;
use App\Models\OrderStatusDocument;
use App\Models\Product;
use App\Notifications\PackingListReady;
use App\Services\CompanyProfileService;
use App\Services\OrderStatusService;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PackingListService
{
    public function __construct(
        private readonly PdfDocumentService $pdfDocumentService,
        private readonly CompanyProfileService $companyProfileService
    ) {}

    public function generateAndNotify(OrderStatus $status, ?int $userId = null): ?OrderStatusDocument
    {
        return $this->generateAndNotifyForDispatch($status, null, $userId);
    }

    public function generateAndNotifyForDispatch(OrderStatus $status, ?array $dispatchQuantities = null, ?int $userId = null): ?OrderStatusDocument
    {
        $status->loadMissing([
            'saleOrder.items.product',
            'items.product',
            'customer',
            'shipmentBatches.items.orderStatusItem',
        ]);

        $items = $this->packingItems($status, $dispatchQuantities);
        if ($items === []) {
            return null;
        }

        $dispatchCode = now()->format('Ymd_His').($dispatchQuantities ? '_'.substr(sha1(json_encode($dispatchQuantities)), 0, 6) : '');
        $fileName = 'Packing_List_'.$status->order_code.'_'.$dispatchCode.'.pdf';
        $filePath = 'order-status/packing-lists/'.$fileName;

        $this->pdfDocumentService->renderPackingList([
            'company' => $this->companyProfileService->getCompanyData(),
            'footerData' => $this->companyProfileService->getFooterData(),
            'logoBase64' => $this->logoBase64(),
            'order' => [
                'reference' => $status->order_code,
                'tracking_code' => $status->tracker?->tracking_code,
                'customer' => $status->saleOrder?->client_name ?: $status->customer?->name,
                'email' => $status->saleOrder?->client_email,
                'shipping_address' => $status->saleOrder?->shipping_address,
                'date' => now()->format('d/m/Y'),
                'status' => 'Salida de bodega / En ruta cliente',
            ],
            'shipment' => $this->shipmentData($status),
            'items' => $items,
        ], $filePath);

        $document = OrderStatusDocument::create(
            [
                'order_status_id' => $status->id,
                'order_status_item_id' => null,
                'phase' => OrderStatusService::PHASE_LOGISTICS,
                'document_type' => 'packing_list_pdf',
                'file_name' => $fileName,
                'file_path' => $filePath,
                'generated_by' => $userId,
                'notes' => $this->documentNotes($items, $dispatchQuantities !== null),
            ]
        );

        if ($status->saleOrder?->client_email) {
            try {
                $status->saleOrder->notify(new PackingListReady($status->fresh(['saleOrder.items.product', 'customer']), $document, $items));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar packing list al cliente: '.$e->getMessage(), [
                    'order_status_id' => $status->id,
                    'document_id' => $document->id,
                ]);
            }
        }

        return $document;
    }

    private function documentNotes(array $items, bool $exactDispatch): string
    {
        $summary = collect($items)
            ->map(fn ($item) => ($item['part_number'] ?: $item['description']).' x '.number_format((float) $item['quantity_dispatched'], 2, '.', ''))
            ->implode('; ');

        return ($exactDispatch
            ? 'Packing list generado automaticamente para esta salida exacta.'
            : 'Packing list generado automaticamente al despachar desde bodega.')
            .($summary ? ' Items: '.$summary : '');
    }

    private function packingItems(OrderStatus $status, ?array $dispatchQuantities = null): array
    {
        $normalizedDispatch = collect($dispatchQuantities ?? [])
            ->mapWithKeys(fn ($quantity, $itemId) => [(int) $itemId => (float) $quantity])
            ->filter(fn ($quantity) => $quantity > 0);

        return $status->items
            ->filter(fn ($item) => $dispatchQuantities === null
                ? (float) $item->quantity_dispatched > 0
                : $normalizedDispatch->has((int) $item->id))
            ->map(function ($item) use ($dispatchQuantities, $normalizedDispatch) {
                $product = $item->product;
                $image = $this->productImage($product);
                $dispatchedNow = $dispatchQuantities === null
                    ? (float) $item->quantity_dispatched
                    : (float) $normalizedDispatch->get((int) $item->id, 0);

                return [
                    'part_number' => $item->part_number ?: $product?->part_number ?: $product?->sku,
                    'description' => $item->part_name ?: $product?->name ?: 'Producto',
                    'short_description' => \App\Support\OrderEmailPresentation::summary($product?->description, $item->part_name ?: $product?->name ?: 'Producto', $product?->brand),
                    'brand' => $item->brand ?: $product?->brand,
                    'model' => $item->model,
                    'quantity_ordered' => (float) $item->quantity_ordered,
                    'quantity_dispatched' => $dispatchedNow,
                    'quantity_dispatched_total' => (float) $item->quantity_dispatched,
                    'quantity_kardex_entry' => (float) $item->quantity_kardex_entry,
                    'quantity_kardex_output' => (float) $item->quantity_kardex_output,
                    'image_src' => $image['src'] ?? null,
                    'image_path' => $image['path'] ?? null,
                    'image_data_uri' => $image['data_uri'] ?? null,
                    'image_cid' => $image['cid'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function shipmentData(OrderStatus $status): array
    {
        $shipment = $status->shipmentBatches->sortByDesc('created_at')->first();

        return [
            'code' => $shipment?->shipment_code ?: $status->tracker?->tracking_code,
            'carrier' => $shipment?->carrier_name,
            'tracking_number' => $shipment?->tracking_number,
            'destination_country' => $shipment?->destination_country ?: 'Nicaragua',
            'estimated_arrival_date' => optional($shipment?->estimated_arrival_date)->format('d/m/Y'),
        ];
    }

    private function productImage(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        if ($product->image_path) {
            $path = public_path('storage/'.$product->image_path);
            $storagePath = storage_path('app/public/'.$product->image_path);

            if (! is_file($path) && is_file($storagePath)) {
                $path = $storagePath;
            }

            return $this->inlineImage($path, 'product-'.$product->id) ?: ['src' => asset('storage/'.$product->image_path)];
        }

        return null;
    }

    private function inlineImage(string $path, string $key): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $cid = Str::slug($key).'-'.substr(sha1($path), 0, 10).'@quantumsolucionesnic.com';
        $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';

        return [
            'src' => 'cid:'.$cid,
            'path' => str_replace('\\', '/', $path),
            'cid' => $cid,
            'mime' => $mime,
            'data_uri' => 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path)),
        ];
    }

    private function logoBase64(): ?string
    {
        foreach ([public_path('images/logo-email.png'), public_path('images/logo-quantum.png'), public_path('storefront/images/logo-completo.png')] as $path) {
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
            }
        }

        return null;
    }
}
