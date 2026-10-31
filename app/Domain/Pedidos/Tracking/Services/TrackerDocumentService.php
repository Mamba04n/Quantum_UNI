<?php

namespace App\Domain\Pedidos\Tracking\Services;

use App\Models\ActivityLog;
use App\Models\OrderTracker;
use App\Models\OrderTrackerAttachment;
use App\Services\OrderTrackerService;
use Illuminate\Support\Facades\Storage;

class TrackerDocumentService
{
    public function __construct(private readonly OrderTrackerService $trackerService) {}

    public function attachGeneratedPdfToTracker(
        int|OrderTracker $tracker,
        string $sourceModule,
        int $sourceId,
        string $phase,
        string $filePath,
        string $fileName,
        ?int $generatedBy = null,
        ?string $activityAction = null,
        ?string $activityDescription = null
    ): OrderTrackerAttachment {
        $tracker = $tracker instanceof OrderTracker ? $tracker : OrderTracker::findOrFail($tracker);

        $activity = $this->trackerService->recordActivity(
            $tracker,
            $activityAction ?: 'generated_pdf_attached',
            $activityDescription ?: "PDF generado en fase {$phase}: {$fileName}",
            $phase,
            $sourceModule,
            $sourceId,
            null,
            null,
            auth()->user()
        );

        $attachment = $tracker->attachments()->create([
            'activity_id' => $activity->id,
            'phase' => $phase,
            'source_module' => $sourceModule,
            'source_id' => $sourceId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => 'pdf',
            'file_size' => $this->fileSize($filePath),
            'uploaded_by' => null,
            'generated_by' => $generatedBy ?: auth()->id(),
            'comment' => 'Documento generado automaticamente por el flujo comercial.',
        ]);

        ActivityLog::log('ventas', 'tracker_pdf_attached', "PDF {$fileName} vinculado al tracker {$tracker->tracking_code}", [
            'tracker_id' => $tracker->id,
            'tracking_code' => $tracker->tracking_code,
            'attachment_id' => $attachment->id,
            'source_module' => $sourceModule,
            'source_id' => $sourceId,
            'phase' => $phase,
            'file_path' => $filePath,
        ]);

        return $attachment;
    }

    private function fileSize(string $filePath): int
    {
        if (Storage::disk('private')->exists($filePath)) {
            return (int) Storage::disk('private')->size($filePath);
        }

        return 0;
    }
}
