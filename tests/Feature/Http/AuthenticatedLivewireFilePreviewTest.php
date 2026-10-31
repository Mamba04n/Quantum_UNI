<?php

namespace Tests\Feature\Http;

use App\Http\Controllers\AuthenticatedLivewireFilePreviewController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Tests\TestCase;

class AuthenticatedLivewireFilePreviewTest extends TestCase
{
    public function test_livewire_preview_route_uses_the_authenticated_fallback_controller(): void
    {
        $route = Route::getRoutes()->getByName('livewire.preview-file');

        $this->assertNotNull($route);
        $this->assertSame(
            AuthenticatedLivewireFilePreviewController::class.'@handle',
            $route->getActionName()
        );
    }

    public function test_authenticated_user_can_preview_an_existing_temporary_image_without_query_signature(): void
    {
        $filename = $this->storeTemporaryPng();
        $user = User::factory()->make(['id' => 9001]);

        $this->actingAs($user)
            ->get('/livewire/preview-file/'.$filename)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    public function test_guest_cannot_preview_a_temporary_image_without_a_valid_signature(): void
    {
        $filename = $this->storeTemporaryPng();

        $this->get('/livewire/preview-file/'.$filename)->assertUnauthorized();
    }

    public function test_guest_can_still_use_a_valid_livewire_preview_signature(): void
    {
        $filename = $this->storeTemporaryPng();
        $url = URL::temporarySignedRoute(
            'livewire.preview-file',
            now()->addMinute(),
            ['filename' => $filename]
        );

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_authenticated_preview_returns_not_found_when_temporary_file_is_missing(): void
    {
        $user = User::factory()->make(['id' => 9002]);

        $this->actingAs($user)
            ->get('/livewire/preview-file/missing-preview.png')
            ->assertNotFound();
    }

    private function storeTemporaryPng(): string
    {
        $filename = 'preview-'.str()->random(20).'.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($filename), $png);

        return $filename;
    }
}
