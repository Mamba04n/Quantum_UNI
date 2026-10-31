<?php

namespace App\Domain\Finanzas\Documents\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceDocumentService
{
    public const MAX_KB = 10240;

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'docx'];

    public function store(UploadedFile $file, string $category, string $code, ?string $partyName = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::MIMES, true)) {
            throw ValidationException::withMessages(['document' => 'Tipo de archivo no permitido. Usa PDF, imagen, XLSX o DOCX.']);
        }

        if ($file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages(['document' => 'El archivo no puede superar 10 MB.']);
        }

        $folder = sprintf('finance/%s/%s/%s', $category, now()->format('Y'), now()->format('m'));
        $safeCode = Str::slug($code ?: strtoupper($category).'-'.now()->format('YmdHis'));
        $safeParty = Str::slug($partyName ?: 'documento');
        $filename = strtoupper($category).'-'.$safeCode.'-'.now()->format('Ymd').'-'.$safeParty.'-'.Str::lower(Str::ulid()).'.'.$extension;

        return $file->storeAs($folder, $filename, 'private');
    }

    public function assertExists(?string $path): void
    {
        if (! $path || ! Storage::disk('private')->exists($path)) {
            throw ValidationException::withMessages(['document' => 'El documento financiero no existe o no fue adjuntado correctamente.']);
        }
    }
}
