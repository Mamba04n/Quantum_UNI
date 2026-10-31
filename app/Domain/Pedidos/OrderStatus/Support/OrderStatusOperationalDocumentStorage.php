<?php

namespace App\Domain\Pedidos\OrderStatus\Support;

use App\Models\OrderStatus;
use App\Models\OrderStatusDocument;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class OrderStatusOperationalDocumentStorage
{
    public function store(
        OrderStatus $status,
        OrderStatusItem $item,
        string $phase,
        string $documentType,
        UploadedFile|string|null $file,
        ?User $user = null,
        ?string $notes = null
    ): ?string {
        if (! $file) {
            return null;
        }

        if ($file instanceof UploadedFile) {
            $directory = "order-status/{$status->id}/items/{$item->id}";
            $path = $file->store($directory, 'private');
            $fileName = $file->getClientOriginalName();
        } else {
            $path = $file;
            $fileName = basename($file);
        }

        OrderStatusDocument::create([
            'order_status_id' => $status->id,
            'order_status_item_id' => $item->id,
            'phase' => $phase,
            'document_type' => $documentType,
            'file_name' => $fileName,
            'file_path' => $path,
            'uploaded_by' => $user?->id,
            'generated_by' => $user?->id,
            'notes' => $notes,
        ]);

        return $path;
    }
}
