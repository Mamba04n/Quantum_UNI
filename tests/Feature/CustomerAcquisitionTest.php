<?php

namespace Tests\Feature;

use App\Livewire\Crm\AcquisitionBoard;
use App\Models\ActivityLog;
use App\Models\CrmProspect;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerAcquisitionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerAcquisitionTest extends TestCase
{
    use DatabaseTransactions;

    private function actor(bool $admin = true, array $permissions = []): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $role = Role::firstOrCreate(['slug' => $admin ? 'admin' : 'acquisition-test'], ['name' => 'Prueba captación']);
        $u->roles()->attach($role);
        foreach ($permissions as $slug) {
            $u->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'prospects']));
        }
        $this->actingAs($u);

        return $u;
    }

    private function data(): array
    {
        return ['name' => 'Captación '.Str::uuid(), 'source' => 'LinkedIn', 'responsible_id' => auth()->id(), 'contacts' => [], 'machines' => [], 'comment' => 'Comentario inicial'];
    }

    private function create(array $extra = []): CrmProspect
    {
        return app(CustomerAcquisitionService::class)->create(array_replace($this->data(), $extra), (string) Str::uuid());
    }

    private function qualified(): CrmProspect
    {
        $p = $this->create(['need' => 'Filtros Cummins', 'products' => 'Filtros', 'context' => ['problem' => 'Paros de flota'], 'classification_criteria' => ['purchase_capacity' => 'Alta'], 'classification' => 'A', 'machines' => [['type' => 'Motor', 'brand' => 'Cummins', 'model' => '6BT', 'quantity' => 3]]]);
        $s = app(CustomerAcquisitionService::class);
        $p = $s->contact($p->id, $p->version, ['summary' => 'Revisión de necesidades de mantenimiento', 'channel' => 'WhatsApp', 'date' => now()->subMinute()->format('Y-m-d\TH:i'), 'person' => 'Ana', 'result' => 'Solicitó información', 'next_action' => 'Solicitar placa', 'next_contact_at' => today()->addDay()->format('Y-m-d')]);

        return $s->move($p->id, $p->version, 'calificados');
    }

    public function test_registration_is_idempotent_and_does_not_create_a_customer(): void
    {
        $this->actor();
        $before = Entity::count();
        $s = app(CustomerAcquisitionService::class);
        $data = $this->data();
        $token = (string) Str::uuid();
        $p = $s->create($data, $token);
        $this->assertSame($p->id, $s->create($data, $token)->id);
        $this->assertSame($before, Entity::count());
        $this->assertSame('prospectos', $p->stage);
        $this->assertTrue(ActivityLog::where('module', 'customer_acquisition')->where('metadata->prospect_id', $p->id)->exists());
    }

    public function test_every_transition_is_validated_in_backend(): void
    {
        $this->actor();
        $p = $this->create();
        $s = app(CustomerAcquisitionService::class);
        foreach (['contactados', 'interesados', 'calificados', 'negociacion'] as $stage) {
            try {
                $s->move($p->id, 0, $stage);
                $this->fail('Accepted invalid transition');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $p = $s->contact($p->id, 0, ['summary' => 'Primer acercamiento', 'channel' => 'Llamada', 'date' => now()->subMinute()->format('Y-m-d\TH:i'), 'person' => 'Ana', 'result' => 'Primer contacto', 'next_action' => 'Visitar', 'next_contact_at' => today()->format('Y-m-d')]);
        $p = $s->move($p->id, $p->version, 'contactados');
        $this->assertSame('contactados', $p->stage);
        $this->expectException(ValidationException::class);
        $s->move($p->id, $p->version, 'interesados');
    }

    public function test_qualification_needs_classification_and_stale_writes_are_rejected(): void
    {
        $this->actor();
        $p = $this->qualified();
        $this->assertSame('calificados', $p->stage);
        try {
            app(CustomerAcquisitionService::class)->update($p->id, $p->version, array_replace($p->toArray(), ['classification' => null, 'next_contact_at' => $p->next_contact_at->format('Y-m-d')]));
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stage', $e->errors());
        }
        $this->expectException(ValidationException::class);
        app(CustomerAcquisitionService::class)->move($p->id, 0, 'prospectos');
    }

    public function test_discard_preserves_history_and_excludes_mutations(): void
    {
        $this->actor();
        $p = $this->create();
        $s = app(CustomerAcquisitionService::class);
        $p = $s->discard($p->id, 0, 'No desea contacto', '');
        $this->assertNotNull($p->discarded_at);
        $this->assertNotNull(CrmProspect::find($p->id));
        Livewire::test(AcquisitionBoard::class)->set('search', $p->name)->assertDontSee($p->name)->set('discarded', 'discarded')->assertSee($p->name);
        $this->expectException(ValidationException::class);
        $s->move($p->id, $p->version, 'prospectos');
    }

    public function test_conversion_is_idempotent_keeps_origin_and_copies_contacts_and_equipment(): void
    {
        $this->actor();
        $p = $this->qualified();
        $s = app(CustomerAcquisitionService::class);
        $o = $s->convert($p->id, $p->version, null, false);
        $this->assertSame($o->id, $s->convert($p->id, $p->version, null, false)->id);
        $this->assertSame($p->id, $o->prospect_id);
        $this->assertNotNull($p->fresh());
        $this->assertSame('cliente', $o->entity->type);
        $this->assertSame('Ana', $o->entity->contacts()->first()->name);
        $this->assertSame('6BT', $o->entity->machines()->first()->model);
        $this->assertSame('A', $o->snapshot['classification']);
        $this->assertSame('calificados', $p->fresh()->stage);
    }

    public function test_existing_customer_is_reused_and_never_overwritten(): void
    {
        $this->actor();
        $p = $this->qualified();
        $client = Entity::create(['type' => 'cliente', 'name' => $p->name, 'email' => 'customer-'.Str::uuid().'@example.test']);
        $before = Entity::count();
        $o = app(CustomerAcquisitionService::class)->convert($p->id, $p->version, $client->id, false);
        $this->assertSame($before, Entity::count());
        $this->assertSame($client->id, $o->entity_id);
        $this->assertSame($client->email, $client->fresh()->email);
    }

    public function test_exact_duplicates_are_blocked_even_with_acknowledgment(): void
    {
        $this->actor();
        $phone = '505'.random_int(10000000, 99999999);
        $this->create(['phone' => $phone]);
        $this->expectException(ValidationException::class);
        app(CustomerAcquisitionService::class)->create($this->data() + ['phone' => $phone], (string) Str::uuid(), true);
    }

    public function test_owner_scope_permissions_and_hidden_duplicate_privacy(): void
    {
        $this->actor();
        $p = $this->create(['email' => 'private-'.Str::uuid().'@example.test']);
        $this->actor(false, ['prospects.view', 'prospects.create']);
        $s = app(CustomerAcquisitionService::class);
        $this->assertFalse($s->visible()->whereKey($p->id)->exists());
        $this->assertSame('Registro de otro responsable', $s->duplicates(['email' => $p->email])[0]['name']);
        try {
            Livewire::test(AcquisitionBoard::class)->call('open', $p->id);
            $this->fail('Foreign record was visible');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
        $this->expectException(HttpException::class);
        $s->move($p->id, $p->version, 'contactados');
    }

    public function test_search_finds_contact_and_machinery_and_filters_by_classification(): void
    {
        $this->actor();
        $p = $this->qualified();
        Livewire::test(AcquisitionBoard::class)->set('search', '6BT')->assertSee($p->name)->set('classification', 'C')->assertDontSee($p->name)->set('classification', 'A')->set('search', 'Ana')->assertSee($p->name);
    }

    public function test_names_warn_but_can_be_confirmed_and_unqualified_conversion_is_blocked(): void
    {
        $this->actor();
        $p = $this->create();
        $s = app(CustomerAcquisitionService::class);
        try {
            $s->create(array_replace($this->data(), ['name' => $p->name]), (string) Str::uuid());
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('duplicate', $e->errors());
        }
        $other = $s->create(array_replace($this->data(), ['name' => $p->name]), (string) Str::uuid(), true);
        $this->assertNotSame($p->id, $other->id);
        $this->expectException(ValidationException::class);
        $s->convert($p->id, $p->version, null, false);
    }

    public function test_classification_and_reassignment_require_separate_permissions(): void
    {
        $this->actor(false, ['prospects.view', 'prospects.create', 'prospects.edit']);
        $p = $this->create();
        $s = app(CustomerAcquisitionService::class);
        $data = array_replace($this->data(), ['name' => $p->name, 'classification' => 'A']);
        try {
            $s->update($p->id, $p->version, $data);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $data['classification'] = null;
        $data['responsible_id'] = $this->actor()->id;
        $owner = User::find($p->responsible_id);
        $this->actingAs($owner);
        $this->expectException(HttpException::class);
        $s->update($p->id, $p->version, $data);
    }

    public function test_own_scope_cannot_move_another_owners_prospect_even_with_move_permission(): void
    {
        $this->actor();
        $p = $this->create();
        $this->actor(false, ['prospects.view', 'prospects.move']);
        $this->expectException(ModelNotFoundException::class);
        app(CustomerAcquisitionService::class)->move($p->id, $p->version, 'prospectos');
    }

    public function test_no_view_permission_denies_component_and_service(): void
    {
        $this->actor(false, []);
        Livewire::test(AcquisitionBoard::class)->assertForbidden();
        $this->expectException(HttpException::class);
        app(CustomerAcquisitionService::class)->visible();
    }

    public function test_context_contacts_roles_criteria_and_engine_survive_conversion(): void
    {
        $this->actor();
        $p = $this->qualified();
        $s = app(CustomerAcquisitionService::class);
        $data = array_replace($p->toArray(), [
            'next_contact_at' => $p->next_contact_at->format('Y-m-d'),
            'contacts' => [
                ['name' => 'Ana', 'commercial_role' => 'Decisor', 'buying_functions' => ['decision']],
                ['name' => 'Luis', 'commercial_role' => 'Compras', 'buying_functions' => ['decision', 'buyer']],
            ],
            'machines' => [['type' => 'Excavadora', 'model' => '320D', 'engine' => 'CAT C6.4', 'quantity' => 3]],
            'context' => ['problem' => 'Paros frecuentes', 'current_supplier' => 'Proveedor de prueba', 'purchase_frequency' => 'Mensual', 'detected_opportunity' => 'Suministro recurrente'],
        ]);
        $p = $s->update($p->id, $p->version, $data);
        $o = $s->convert($p->id, $p->version, null, false);
        $this->assertSame('Mensual', $o->snapshot['context']['purchase_frequency']);
        $this->assertSame('Alta', $o->snapshot['classification_criteria']['purchase_capacity']);
        $this->assertSame('A', $o->snapshot['classification']);
        $this->assertSame('Decisor · Decide', $o->entity->contacts()->where('name', 'Ana')->first()->notes);
        $this->assertSame('Motor conocido: CAT C6.4', $o->entity->machines()->first()->technical_details);
    }

    public function test_new_leads_stay_quick_but_qualification_requires_commercial_context(): void
    {
        $this->actor();
        $p = $this->qualified();
        $s = app(CustomerAcquisitionService::class);
        $data = array_replace($p->toArray(), ['next_contact_at' => $p->next_contact_at->format('Y-m-d'), 'context' => [], 'classification_criteria' => []]);
        try {
            $s->update($p->id, $p->version, $data);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stage', $e->errors());
        }
        $this->assertSame('A', $p->fresh()->classification);
        $p = $s->move($p->id, $p->version, 'contactados');
        $data['need'] = null;
        $p = $s->update($p->id, $p->version, $data);
        $this->expectException(ValidationException::class);
        $s->move($p->id, $p->version, 'interesados');
    }

    public function test_campaign_creation_is_idempotent_and_filters_keep_four_columns(): void
    {
        $this->actor();
        $s = app(CustomerAcquisitionService::class);
        $token = (string) Str::uuid();
        $c = $s->createCampaign(['name' => 'Campaña '.Str::uuid()], $token);
        $this->assertSame($c->id, $s->createCampaign(['name' => 'Reintento'], $token)->id);
        $this->assertSame($c->id, $s->createCampaign(['name' => $c->name], (string) Str::uuid())->id);
        $p = $this->create(['campaign_id' => $c->id]);
        $other = $this->create();
        Livewire::test(AcquisitionBoard::class)->set('campaign', (string) $c->id)->assertSee($p->name)->assertDontSee($other->name)->assertSee('Total empresas:')->assertSee('Pendientes:');
        $this->assertCount(4, config('acquisition.stages'));
    }

    public function test_campaign_members_and_counts_do_not_leak_other_owners(): void
    {
        $this->actor();
        $s = app(CustomerAcquisitionService::class);
        $c = $s->createCampaign(['name' => 'Privada '.Str::uuid()], (string) Str::uuid());
        $p = $this->create(['campaign_id' => $c->id]);
        $u = $this->actor(false, ['prospects.view', 'prospects.create']);
        $this->assertFalse($s->campaigns()->whereKey($c->id)->exists());
        try {
            $this->create(['campaign_id' => $c->id]);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('campaign_id', $e->errors());
        }
        // Sharing a campaign through an assigned prospect exposes only owned members.
        $p->responsible_id = $u->id;
        $p->save();
        $this->assertTrue($s->campaigns()->whereKey($c->id)->exists());
        $this->assertSame(1, $s->visible()->where('campaign_id', $c->id)->count());
    }

    public function test_interaction_records_summary_actual_date_and_responsible_in_existing_audit(): void
    {
        $u = $this->actor();
        $p = $this->create();
        $when = now()->subDays(2)->format('Y-m-d\TH:i');
        $p = app(CustomerAcquisitionService::class)->contact($p->id, $p->version, ['channel' => 'Reunión', 'date' => $when, 'person' => 'María', 'position' => 'Gerencia', 'summary' => 'Revisamos flota y operación', 'result' => 'Interés en filtros', 'next_action' => 'Visitar planta', 'next_contact_at' => today()->addDay()->format('Y-m-d')]);
        $log = ActivityLog::where('module', 'customer_acquisition')->where('metadata->prospect_id', $p->id)->where('action', 'contact')->firstOrFail();
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame($when, $log->metadata['date']);
        $this->assertSame('Revisamos flota y operación', $log->metadata['summary']);
        Livewire::test(AcquisitionBoard::class)->call('open', $p->id)->assertSee('Resumen: Revisamos flota y operación')->assertSee('Cargo: Gerencia');
    }

    public function test_invalid_roles_levels_and_hidden_criteria_mutations_are_rejected(): void
    {
        $this->actor();
        try {
            $this->create(['classification_criteria' => ['purchase_capacity' => 'Excelente'], 'contacts' => [['name' => 'Ana', 'commercial_role' => 'Inventado']]]);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('classification_criteria.purchase_capacity', $e->errors());
            $this->assertArrayHasKey('contacts.0.commercial_role', $e->errors());
        }
        $this->actor(false, ['prospects.view', 'prospects.create']);
        $this->expectException(HttpException::class);
        $this->create(['classification_criteria' => ['purchase_capacity' => 'Alta']]);
    }
}
