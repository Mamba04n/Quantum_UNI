<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Livewire\Drawer\Utils;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedLivewireFilePreviewController extends Controller
{
    public function handle(string $filename): Response
    {
        $hasValidSignature = request()->hasValidSignature();
        abort_unless($hasValidSignature || Auth::check(), 401);
        abort_unless($this->isSafeFilename($filename), 404);

        $path = FileUploadConfiguration::path($filename);
        abort_unless(FileUploadConfiguration::storage()->exists($path), 404);

        $response = Utils::pretendPreviewResponseIsPreviewFile($filename);

        if (! $hasValidSignature) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function isSafeFilename(string $filename): bool
    {
        return $filename !== ''
            && ! str_contains($filename, "\0")
            && ! str_contains($filename, '/')
            && ! str_contains($filename, '\\')
            && basename($filename) === $filename;
    }
}
