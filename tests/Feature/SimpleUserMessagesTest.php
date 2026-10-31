<?php

namespace Tests\Feature;

use App\Livewire\Finanzas\FinanceHub;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use App\Support\UserMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SimpleUserMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_failure_is_clear_without_showing_the_exception_or_registering_payment(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['slug' => 'finance.view'], ['name' => 'Finanzas', 'module' => 'finance']);
        $user->permissions()->syncWithoutDetaching([$permission->id]);
        Payment::creating(function () {
            throw new \RuntimeException('SQLSTATE private credentials');
        });
        Livewire::actingAs($user)->test(FinanceHub::class)
            ->set('direct_expense_concept', 'Servicio de agua')
            ->set('direct_expense_amount', '50.00')
            ->set('direct_expense_reference', 'PRUEBA-ERROR-01')
            ->call('saveDirectExpense')
            ->assertSee('No se pudo registrar el gasto. Revisa los datos e intenta de nuevo.')
            ->assertDontSee('SQLSTATE');
        $this->assertDatabaseMissing('payments', ['payment_reference' => 'PRUEBA-ERROR-01']);
        $this->assertTrue(AuditLog::where('action', 'application.exception')->exists());
    }

    public function test_validation_uses_field_names_and_keeps_useful_business_errors(): void
    {
        $validator = Validator::make([], ['direct_expense_amount' => 'required']);
        $this->assertSame('Falta el monto del gasto.', $validator->errors()->first());
        $error = ValidationException::withMessages(['quantity' => 'No hay existencias suficientes.']);
        $this->assertSame('No hay existencias suficientes.', UserMessage::error($error));
        $unsafe = ValidationException::withMessages(['quantity' => 'SQLSTATE database details']);
        $this->assertSame('Revisa la cantidad.', UserMessage::error($unsafe, 'Revisa la cantidad.'));
    }

    public function test_web_errors_are_spanish_and_keep_http_status_and_allow_header(): void
    {
        $this->get('/livewire/update')->assertStatus(405)->assertSee('Esta dirección no permite esa acción.')
            ->assertHeader('Allow')->assertDontSee('Oops');
        $this->getJson('/livewire/update')->assertStatus(405)->assertJsonPath('message', UserMessage::http(405));
        Route::get('/ui-message-test/error', fn () => throw new \RuntimeException('SQLSTATE credentials'));
        config(['app.debug' => false]);
        $this->getJson('/ui-message-test/error')->assertStatus(500)
            ->assertJsonPath('message', UserMessage::http(500))->assertDontSee('SQLSTATE');
    }

    public function test_api_response_contract_is_not_rewritten(): void
    {
        Route::get('/api/message-contract-test', fn () => abort(409, 'API contract error'));
        $this->getJson('/api/message-contract-test')->assertStatus(409)->assertJsonPath('message', 'API contract error');
    }
}
