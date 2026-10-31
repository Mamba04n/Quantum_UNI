<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PrivateFileResponse
{
    public function make(
        ?string $path,
        Request $request,
        ?string $downloadName = null,
        bool $allowInline = false
    ): \Symfony\Component\HttpFoundation\Response {
        $path = $this->safeRelativePath($path);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('private');

        abort_unless($disk->exists($path), 404);

        $fileName = $this->safeFileName($downloadName ?: basename($path));
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $inlineMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $inline = $allowInline && in_array($mimeType, $inlineMimeTypes, true);
        $disposition = $inline ? 'inline' : 'attachment';

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $disk->size($path),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                $fileName,
                Str::ascii($fileName) ?: 'document'
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
        ];

        if ($inline) {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'";

            if (str_starts_with($mimeType, 'image/')) {
                $lastModified = $disk->lastModified($path);
                $etag = '"'.md5($path.'-'.$lastModified.'-'.$disk->size($path)).'"';

                if ($request->headers->get('If-None-Match') === $etag) {
                    return response('', 304, [
                        'ETag' => $etag,
                        'Cache-Control' => 'private, max-age=86400, must-revalidate',
                    ]);
                }

                $headers['Cache-Control'] = 'private, max-age=86400, must-revalidate';
                $headers['ETag'] = $etag;
                $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $lastModified).' GMT';
                unset($headers['Pragma']);
            }
        }

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            abort_unless(is_resource($stream), 404);

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function exists(?string $path): bool
    {
        try {
            return Storage::disk('private')->exists($this->safeRelativePath($path));
        } catch (HttpException) {
            return false;
        }
    }

    private function safeRelativePath(?string $path): string
    {
        $path = str_replace('\\', '/', trim((string) $path));

        abort_if(
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_contains($path, "\0")
            || in_array('..', explode('/', $path), true),
            404
        );

        return str_starts_with($path, './') ? substr($path, 2) : $path;
    }

    private function safeFileName(string $fileName): string
    {
        $fileName = basename(str_replace(["\r", "\n", '"', '\\'], '', $fileName));

        return $fileName !== '' ? $fileName : 'document';
    }
}
