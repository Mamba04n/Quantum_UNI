<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses\ManagesSupplierBidForm;
use App\Models\OrderTracker;
use App\Models\OrderTrackerAttachment;
use App\Models\Permission;
use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\Pdf\Mappers\SupplierRfqMapper;
use App\Services\Pdf\PdfDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentRoutesAndSupplierBidAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_and_download_private_tracker_attachment(): void
    {
        Storage::fake('private');
        $user = $this->userWithPermissions(['tracker.view', 'tracker.view_supplier_quotes']);
        $attachment = $this->trackerAttachment('supplier-evidence.pdf', '%PDF-test');

        $inline = $this->actingAs($user)->get(route('tracker.attachments.show', $attachment));
        $inline->assertOk();
        $this->assertStringStartsWith('inline;', (string) $inline->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-test', $inline->streamedContent());

        $download = $this->actingAs($user)->get(route('tracker.attachments.show', [
            'attachment' => $attachment,
            'download' => 1,
        ]));
        $download->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));
    }

    public function test_tracker_attachment_rejects_unauthorized_missing_and_unsafe_paths(): void
    {
        Storage::fake('private');
        $viewer = $this->userWithPermissions(['tracker.view']);
        $authorized = $this->userWithPermissions(['tracker.view', 'tracker.view_supplier_quotes']);
        $attachment = $this->trackerAttachment('private.txt', 'private');

        $this->actingAs($viewer)
            ->get(route('tracker.attachments.show', $attachment))
            ->assertForbidden();

        Storage::disk('private')->delete($attachment->file_path);
        $this->actingAs($authorized)
            ->get(route('tracker.attachments.show', $attachment))
            ->assertNotFound();

        $attachment->forceFill(['file_path' => '../.env'])->save();
        $this->actingAs($authorized)
            ->get(route('tracker.attachments.show', $attachment))
            ->assertNotFound();
    }

    public function test_tracker_attachment_requires_authentication(): void
    {
        $attachment = $this->trackerAttachment('auth.pdf', '%PDF-auth');

        $this->get(route('tracker.attachments.show', $attachment))
            ->assertRedirect(route('login'));
    }

    public function test_rfq_pdf_supports_spanish_english_inline_and_download(): void
    {
        Storage::fake('private');
        $user = $this->userWithPermissions(['supplier_bid.view']);
        $document = $this->rfqDocument($user);
        Storage::disk('private')->put($document->pdf_path, '%PDF-es');

        $spanish = $this->actingAs($user)->get(route('sales.rfq-documents.pdf', [
            'document' => $document,
            'lang' => 'es',
        ]));
        $spanish->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $spanish->headers->get('Content-Disposition'));

        $download = $this->actingAs($user)->get(route('sales.rfq-documents.pdf', [
            'document' => $document,
            'lang' => 'es',
            'download' => 1,
        ]));
        $download->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));

        $this->mock(SupplierRfqMapper::class, function (MockInterface $mock): void {
            $mock->shouldReceive('map')->once()->withArgs(fn ($document, $lang) => $lang === 'en')->andReturn([]);
        });
        $this->mock(PdfDocumentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('supplierRfqContent')->once()->andReturn('%PDF-en');
        });

        $english = $this->actingAs($user)->get(route('sales.rfq-documents.pdf', [
            'document' => $document,
            'lang' => 'en',
        ]));
        $english->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $english->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-en', $english->getContent());
    }

    public function test_rfq_pdf_rejects_unauthenticated_and_unauthorized_users(): void
    {
        $owner = User::factory()->create();
        $document = $this->rfqDocument($owner);

        $this->get(route('sales.rfq-documents.pdf', $document))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('sales.rfq-documents.pdf', $document))
            ->assertForbidden();
    }

    public function test_supplier_bid_trait_uses_laravel_authentication_and_preserves_permissions(): void
    {
        Livewire::test(SupplierBidAuthProbe::class)
            ->call('addSupplierBidStandaloneItem')
            ->assertSet('supplierBidExtraItems', []);

        Livewire::actingAs(User::factory()->create())
            ->test(SupplierBidAuthProbe::class)
            ->call('addSupplierBidStandaloneItem')
            ->assertSet('supplierBidExtraItems', []);

        $authorized = $this->userWithPermissions(['supplier_bid.add_supplier_response']);
        Livewire::actingAs($authorized)
            ->test(SupplierBidAuthProbe::class)
            ->call('addSupplierBidStandaloneItem')
            ->assertCount('supplierBidExtraItems', 1);
    }

    private function trackerAttachment(string $name, string $contents): OrderTrackerAttachment
    {
        $tracker = OrderTracker::query()->create([
            'operational_tracking_code' => 'TRK-'.str()->upper(str()->random(8)),
            'operational_phase' => 'licitacion',
            'operational_status' => 'en_proceso',
        ]);
        $path = 'tracker-tests/'.$name;
        Storage::disk('private')->put($path, $contents);

        return OrderTrackerAttachment::query()->create([
            'tracker_id' => $tracker->id,
            'phase' => 'supplier_bid',
            'file_name' => $name,
            'file_path' => $path,
            'file_type' => 'application/pdf',
            'file_size' => strlen($contents),
        ]);
    }

    private function rfqDocument(User $user): SupplierRfqDocument
    {
        $quotation = QuotationRequest::query()->create([
            'reference' => 'COT-'.str()->upper(str()->random(8)),
            'submission_token' => (string) str()->uuid(),
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente de prueba',
            'requested_by' => $user->name,
            'department' => 'ventas',
            'source_channel' => 'correo',
            'machinery' => 'Equipo de prueba',
            'part_code' => 'TEST-001',
            'request_quantity' => 1,
            'request_urgency' => 'media',
            'subtotal' => 0,
            'total_amount' => 0,
            'status' => QuotationRequest::STATUS_READY_FOR_BID,
            'user_id' => $user->id,
        ]);

        return SupplierRfqDocument::query()->create([
            'quotation_request_id' => $quotation->id,
            'rfq_number' => 'RFQ-'.str()->upper(str()->random(8)),
            'status' => SupplierRfqDocument::STATUS_GENERATED,
            'pdf_path' => 'rfq-tests/document.pdf',
        ]);
    }

    private function userWithPermissions(array $slugs): User
    {
        $user = User::factory()->create();
        $permissionIds = collect($slugs)->map(function (string $slug) {
            return Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->headline(), 'module' => 'tests']
            )->id;
        })->all();
        $user->syncPermissions($permissionIds);

        return $user->fresh();
    }
}

class SupplierBidAuthProbe extends Component
{
    use ManagesSupplierBidForm;

    public array $supplierBidExtraItems = [];

    public function render()
    {
        return Blade::render('<div></div>');
    }
}
