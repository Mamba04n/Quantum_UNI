<?php

namespace App\Domain\Pedidos\OrderStatus\Support;

use App\Models\OrderStatusDocument;
use App\Models\OrderStatusItem;
use Illuminate\Validation\ValidationException;

class OrderStatusDocumentRequirement
{
    public function assertExists(OrderStatusItem $item, string $documentType): void
    {
        $acceptedTypes = match ($documentType) {
            'purchase_order_provider_pdf' => ['purchase_order_provider_pdf', 'purchase_order_pdf'],
            default => [$documentType],
        };

        $query = OrderStatusDocument::query()
            ->where('order_status_id', $item->order_status_id)
            ->whereIn('document_type', $acceptedTypes);

        if (! $this->documentBelongsToOrder($documentType)) {
            $query->where(function ($query) use ($item) {
                $query->whereNull('order_status_item_id')->orWhere('order_status_item_id', $item->id);
            });
        }

        $exists = $query->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['document' => "Falta documento obligatorio: {$this->humanDocument($documentType)}."]);
        }
    }

    private function humanDocument(string $documentType): string
    {
        return str($documentType)->replace('_', ' ')->headline()->toString();
    }

    private function documentBelongsToOrder(string $documentType): bool
    {
        return in_array($documentType, ['purchase_order_provider_pdf', 'purchase_order_pdf'], true);
    }
}
