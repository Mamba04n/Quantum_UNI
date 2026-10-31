<?php

namespace Tests\Feature;

use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PrivateFileResponseTest extends TestCase
{
    public function test_user_controlled_download_parameter_cannot_enable_inline_rendering(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('uploads/proof.svg', '<svg></svg>');

        foreach (['0', '1'] as $download) {
            $response = app(PrivateFileResponse::class)->make(
                'uploads/proof.svg',
                Request::create('/private-file?download='.$download)
            );

            $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('private', $cacheControl);
            $this->assertStringContainsString('no-store', $cacheControl);
            ob_start();
            $response->sendContent();
            $this->assertSame('<svg></svg>', ob_get_clean());
        }
    }

    public function test_trusted_inline_response_is_sandboxed(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('generated/report.pdf', 'trusted-pdf');

        $response = app(PrivateFileResponse::class)->make(
            'generated/report.pdf',
            Request::create('/private-file'),
            'report.pdf',
            allowInline: true
        );

        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame("sandbox; default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_active_content_cannot_be_rendered_inline_even_when_requested_by_server_code(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('uploads/content.svg', '<svg></svg>');

        $response = app(PrivateFileResponse::class)->make(
            'uploads/content.svg',
            Request::create('/private-file'),
            allowInline: true
        );

        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_private_response_never_falls_back_to_the_public_disk(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        Storage::disk('public')->put('documents/public-only.pdf', 'public');

        $this->expectException(NotFoundHttpException::class);

        app(PrivateFileResponse::class)->make(
            'documents/public-only.pdf',
            Request::create('/private-file')
        );
    }

    public function test_unsafe_paths_are_rejected(): void
    {
        Storage::fake('private');

        foreach (['../secret.pdf', '/absolute.pdf', 'C:/secret.pdf', 'https://example.test/file.pdf', "bad\0name.pdf"] as $path) {
            try {
                app(PrivateFileResponse::class)->make($path, Request::create('/private-file'));
                $this->fail("Unsafe path was accepted: {$path}");
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_download_name_cannot_inject_response_headers(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('documents/proof.pdf', 'proof');

        $response = app(PrivateFileResponse::class)->make(
            'documents/proof.pdf',
            Request::create('/private-file'),
            "proof.pdf\r\nX-Evil: injected"
        );

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertFalse($response->headers->has('X-Evil'));
    }
}
