<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceDocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_finance_document_permission_can_download_any_record_in_single_company_scope(): void
    {
        Storage::fake('private');
        $creator = User::factory()->create();
        $document = $this->payableDocument($creator);
        $user = User::factory()->create();
        $this->grant($user, 'finance.documents.view_all');

        $response = $this->actingAs($user)
            ->get(route('finance.documents.download', ['type' => 'cxp', 'id' => $document]));

        $response->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('financial-proof', $response->streamedContent());
    }

    public function test_assigned_permission_can_download_own_document(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $document = $this->payableDocument($user);
        $this->grant($user, 'finance.documents.view_assigned');

        $this->actingAs($user)
            ->get(route('finance.documents.download', ['type' => 'cxp', 'id' => $document]))
            ->assertOk();
    }

    public function test_assigned_permission_cannot_download_another_users_document(): void
    {
        Storage::fake('private');
        $document = $this->payableDocument(User::factory()->create());
        $user = User::factory()->create();
        $this->grant($user, 'finance.documents.view_assigned');

        $this->actingAs($user)
            ->get(route('finance.documents.download', ['type' => 'cxp', 'id' => $document]))
            ->assertForbidden();
    }

    public function test_module_permission_alone_does_not_authorize_document_download(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $document = $this->payableDocument($user);
        $this->grant($user, 'finance.view');

        $this->actingAs($user)
            ->get(route('finance.documents.download', ['type' => 'cxp', 'id' => $document]))
            ->assertForbidden();
    }

    public function test_assigned_scope_is_enforced_for_every_finance_document_type(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->grant($owner, 'finance.documents.view_assigned');

        $ownedDocuments = [
            new AccountPayable(['created_by' => $owner->id]),
            new AccountReceivable(['created_by' => $owner->id]),
            new Invoice(['generated_by' => $owner->id]),
            new Payment(['registered_by' => $owner->id]),
        ];
        $otherDocuments = [
            new AccountPayable(['created_by' => $other->id]),
            new AccountReceivable(['created_by' => $other->id]),
            new Invoice(['uploaded_by' => $other->id]),
            new Payment(['registered_by' => $other->id]),
        ];

        foreach ($ownedDocuments as $document) {
            $this->assertTrue(Gate::forUser($owner)->allows('viewDocument', $document));
        }

        foreach ($otherDocuments as $document) {
            $this->assertFalse(Gate::forUser($owner)->allows('viewDocument', $document));
        }
    }

    public function test_unknown_finance_document_does_not_disclose_information(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'finance.documents.view_all');

        $this->actingAs($user)
            ->get(route('finance.documents.download', ['type' => 'cxp', 'id' => 999999]))
            ->assertNotFound();
    }

    private function payableDocument(User $creator): AccountPayable
    {
        $path = 'finance/cxp/proof.pdf';
        Storage::disk('private')->put($path, 'financial-proof');
        $entity = Entity::create(['name' => 'Proveedor', 'type' => 'proveedor']);

        return AccountPayable::create([
            'entity_id' => $entity->id,
            'concept' => 'Documento confidencial',
            'total_amount' => 100,
            'paid_amount' => 0,
            'due_date' => now()->addDay()->toDateString(),
            'status' => 'pendiente',
            'support_document_path' => $path,
            'created_by' => $creator->id,
        ]);
    }

    private function grant(User $user, string $slug): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('.', ' ')->headline()->toString(), 'module' => 'finance']
        );
        $user->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
