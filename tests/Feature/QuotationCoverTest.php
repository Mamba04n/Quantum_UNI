<?php

namespace Tests\Feature;

use App\Domain\Administracion\Rbac\Services\UserAccessResolver;
use App\Models\Permission;
use App\Models\QuotationRequest;
use App\Models\SupplierBidResponse;
use App\Models\User;
use App\Support\QuotationCover;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationCoverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cover_requires_permission_and_is_never_public(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $path = UploadedFile::fake()->image('part.png')->store('covers', 'private');
        $quote = QuotationRequest::factory()->create(['evidence_attachment_path' => $path]);
        $url = route('private.quotation-cover', $quote);
        $this->get($url)->assertRedirect(route('login'));
        $user = User::factory()->create(['role' => 'ventas']);
        $this->actingAs($user)->get($url)->assertForbidden();
        $permission = Permission::firstOrCreate(['slug' => 'customer_request.view'], ['name' => 'Ver solicitudes', 'module' => 'ventas']);
        $user->permissions()->attach($permission);
        app(UserAccessResolver::class)->forget($user);
        $this->actingAs($user->fresh())->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        Storage::disk('public')->assertMissing($path);
    }

    public function test_active_content_missing_files_and_traversal_are_not_covers(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        Storage::disk('private')->put('fake.png', '<html>Not an image</html>');
        $quote = new QuotationRequest;
        $quote->setRelation('items', collect());
        $quote->setRelation('supplierBidResponses', collect());
        foreach (['fake.png', 'missing.jpg', '../outside.png', 'https://example.com/image.png', 'upload.svg', 'document.pdf'] as $path) {
            $quote->evidence_attachment_path = $path;
            $this->assertNull(app(QuotationCover::class)->resolve($quote));
        }
    }

    public function test_supplier_images_are_excluded_unless_explicitly_authorized(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $path = UploadedFile::fake()->image('supplier.png')->store('covers', 'private');
        $quote = new QuotationRequest;
        $quote->setRelation('items', collect());
        $response = new SupplierBidResponse(['attachment_path' => $path]);
        $response->setRelation('supplierDocumentAttachments', collect());
        $quote->setRelation('supplierBidResponses', collect([$response]));
        $this->assertNull(app(QuotationCover::class)->resolve($quote));
        $this->assertSame(['disk' => 'private', 'path' => $path], app(QuotationCover::class)->resolve($quote, true));
    }
}
