<?php

namespace Tests\Feature;

use App\Domain\Finanzas\ExchangeRates\Enums\ExchangeRateSourceType;
use App\Livewire\Finanzas\ExchangeRateManager;
use App\Models\Entity;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Permission;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\Role;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-01 10:00:00');
        Cache::flush();
        Config::set('services.exchange_rate.max_age_days', 3);
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-08-01');
        Config::set('services.exchange_rate.manual_eur_nio_date', '2026-08-01');
        Config::set('services.exchange_rate.manual_inr_nio_date', '2026-08-01');
        Currency::where('code', 'USD')->update(['is_fixed' => false, 'automatic_updates_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_official_api_rate_is_classified_and_stored_with_expiry(): void
    {
        Config::set('services.exchange_rate.api_url', 'https://rates.test/latest');
        Config::set('services.exchange_rate.source', 'bcn-api');
        Config::set('services.exchange_rate.api_is_official', true);
        Http::fake([
            'rates.test/*' => Http::response([
                'base' => 'USD',
                'date' => '2026-08-01',
                'rates' => ['NIO' => '36.720000'],
            ]),
        ]);

        $rate = app(ExchangeRateService::class)->usdToNio(true);

        $this->assertSame('36.720000', $rate['rate']);
        $this->assertSame(ExchangeRateSourceType::Official->value, $rate['source_type']);
        $this->assertFalse($rate['is_fallback']);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USD',
            'quote_currency' => 'NIO',
            'rate_date' => '2026-08-01',
            'expires_at' => '2026-08-04',
            'source' => 'bcn-api',
            'source_type' => ExchangeRateSourceType::Official->value,
        ]);
    }

    public function test_unknown_currency_and_unsupported_pair_raise_validation_errors(): void
    {
        $service = app(ExchangeRateService::class);

        try {
            $service->convert('100.00', null);
            $this->fail('Una moneda nula no debe convertirse implicitamente como USD.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('from_currency', $exception->errors());
        }

        try {
            $service->convert('100.00', 'USD', 'EUR');
            $this->fail('Un par no soportado no debe devolver una tasa 1.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('currency_pair', $exception->errors());
        }
    }

    public function test_nio_can_be_converted_to_usd_using_the_inverse_approved_rate(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', '37.000000');
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-08-01');

        $conversion = app(ExchangeRateService::class)->convert('136345.00', 'NIO', 'USD');

        $this->assertSame('3685.00', $conversion['amount']);
        $this->assertSame('0.027027', $conversion['rate']);
        $this->assertStringStartsWith('inverse:', $conversion['source']);
    }

    public function test_api_failure_uses_a_recent_stored_rate_and_reports_its_origin(): void
    {
        Config::set('services.exchange_rate.api_url', 'https://rates.test/latest');
        Http::fake(['rates.test/*' => Http::response([], 503)]);
        $this->storedRate('36.610000', '2026-07-31', ExchangeRateSourceType::Api);

        $rate = app(ExchangeRateService::class)->usdToNio(true);

        $this->assertSame('36.610000', $rate['rate']);
        $this->assertTrue($rate['is_stored']);
        $this->assertTrue($rate['is_fallback']);
        $this->assertFalse($rate['is_stale']);
        $this->assertStringContainsString('Tasa obtenida por API', $rate['source_label']);
    }

    public function test_configured_manual_fallback_is_explicitly_classified(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', '36.500000');
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-08-01');

        $rate = app(ExchangeRateService::class)->usdToNio(true);

        $this->assertSame('36.500000', $rate['rate']);
        $this->assertSame(ExchangeRateSourceType::ConfiguredFallback->value, $rate['source_type']);
        $this->assertTrue($rate['is_fallback']);
        $this->assertFalse($rate['is_stale']);
    }

    public function test_stale_rate_is_blocked_without_an_explicit_finance_approval(): void
    {
        $this->disableUsdFallbacks();
        $this->storedRate('36.400000', '2026-07-20', ExchangeRateSourceType::Api);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('requiere aprobacion explicita');

        app(ExchangeRateService::class)->usdToNio(true);
    }

    public function test_user_without_permission_cannot_approve_a_stale_rate(): void
    {
        $this->disableUsdFallbacks();
        $this->storedRate('36.400000', '2026-07-20', ExchangeRateSourceType::Api);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no tiene permiso');

        app(ExchangeRateService::class)->usdToNio(true, User::factory()->create());
    }

    public function test_finance_can_explicitly_approve_a_stale_rate_and_it_is_audited(): void
    {
        $this->disableUsdFallbacks();
        $stored = $this->storedRate('36.400000', '2026-07-20', ExchangeRateSourceType::Api);
        $approver = $this->rateApprover();

        $rate = app(ExchangeRateService::class)->usdToNio(true, $approver);

        $this->assertTrue($rate['is_stale']);
        $this->assertTrue($rate['requires_confirmation']);
        $this->assertSame($approver->id, $rate['approved_by']);
        $this->assertDatabaseHas('exchange_rates', [
            'id' => $stored->id,
            'stale_approved_by' => $approver->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $approver->id,
            'action' => 'exchange_rate.stale_approved',
        ]);

        Cache::flush();
        $this->expectException(ValidationException::class);
        app(ExchangeRateService::class)->usdToNio(true);
    }

    public function test_manual_rate_records_approver_source_and_validity(): void
    {
        $approver = $this->rateApprover();

        $rate = app(ExchangeRateService::class)->approveManualRate(
            'USD',
            'NIO',
            '36.850000',
            '2026-08-01',
            $approver,
        );

        $this->assertSame(ExchangeRateSourceType::ManualApproved, $rate->source_type);
        $this->assertSame($approver->id, $rate->approved_by);
        $this->assertSame('2026-08-04', $rate->expires_at?->toDateString());
        $this->assertNotNull($rate->approved_at);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $approver->id,
            'action' => 'exchange_rate.manual_approved',
        ]);
    }

    public function test_approved_manual_rate_has_priority_over_api_during_its_validity(): void
    {
        $service = app(ExchangeRateService::class);
        $service->approveManualRate('USD', 'NIO', '36.850000', '2026-08-01', $this->rateApprover());
        Config::set('services.exchange_rate.api_url', 'https://rates.test/latest');
        Http::fake(['rates.test/*' => Http::response([
            'base' => 'USD',
            'date' => '2026-08-01',
            'rates' => ['NIO' => '36.720000'],
        ])]);

        $rate = $service->usdToNio(true);

        $this->assertSame('36.850000', $rate['rate']);
        $this->assertSame(ExchangeRateSourceType::ManualApproved->value, $rate['source_type']);
        Http::assertNothingSent();
    }

    public function test_apply_to_client_offer_preserves_original_cost_and_exact_conversion(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', '36.500000');
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-08-01');
        Config::set('services.exchange_rate.default_markup', '1.350000');
        [$quotation, $item, $selection] = $this->quotationWithSelectedSupplierCost('100.00');

        $quotation = app(ExchangeRateService::class)->applyToClientOffer($quotation);
        $item = $item->fresh();

        $this->assertSame('NIO', $quotation->currency);
        $this->assertSame(ExchangeRateSourceType::ConfiguredFallback->value, $quotation->exchange_rate_source_type);
        $this->assertSame('USD', $item->supplier_currency);
        $this->assertSame('100.00', $item->supplier_unit_cost_original);
        $this->assertSame('3650.00', $item->supplier_unit_cost_nio);
        $this->assertSame('4927.50', $item->unit_price);
        $this->assertSame('9855.00', $item->line_total);
        $this->assertSame('11333.25', $quotation->total_amount);
        $this->assertSame($selection->selected_unit_cost, $item->unit_cost);
    }

    public function test_supplier_cost_sync_preserves_original_and_converted_values(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_eur_nio', '40.250000');
        Config::set('services.exchange_rate.manual_eur_nio_date', '2026-08-01');
        [$quotation, $item] = $this->quotationWithSelectedSupplierCost('90.75', 'EUR');

        app(ExchangeRateService::class)->syncSupplierCostInNio($item, '90.75', 'EUR');
        $item = $item->fresh();

        $this->assertSame('EUR', $item->supplier_currency);
        $this->assertSame('90.75', $item->supplier_unit_cost_original);
        $this->assertSame('3652.69', $item->supplier_unit_cost_nio);
        $this->assertSame('40.250000', $item->exchange_rate);
        $this->assertNull($quotation->fresh()->exchange_rate);
    }

    public function test_configured_fallback_cannot_renew_itself_with_todays_date(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', '36.500000');
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-07-20');

        $this->expectException(ValidationException::class);

        try {
            app(ExchangeRateService::class)->usdToNio(true);
        } finally {
            $this->assertDatabaseMissing('exchange_rates', [
                'base_currency' => 'USD',
                'rate_date' => '2026-08-01',
            ]);
        }
    }

    public function test_future_manual_rate_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no puede estar en el futuro');

        app(ExchangeRateService::class)->approveManualRate(
            'USD',
            'NIO',
            '36.850000',
            '2026-08-02',
            $this->rateApprover(),
        );
    }

    public function test_api_response_with_wrong_or_missing_base_is_rejected(): void
    {
        $this->disableUsdFallbacks();
        Config::set('services.exchange_rate.api_url', 'https://rates.test/latest');
        Http::fake(['rates.test/*' => Http::response([
            'base' => 'EUR',
            'date' => '2026-08-01',
            'rates' => ['NIO' => '42.500000'],
        ])]);

        $this->expectException(ValidationException::class);
        app(ExchangeRateService::class)->usdToNio(true);
    }

    public function test_mixed_currency_offer_records_each_rate_and_marks_header_as_multiple(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', '36.500000');
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-08-01');
        Config::set('services.exchange_rate.manual_eur_nio', '40.250000');
        Config::set('services.exchange_rate.manual_eur_nio_date', '2026-08-01');
        [$quotation, $usdItem] = $this->quotationWithSelectedSupplierCost('100.00');
        [$eurItem] = $this->addSelectedSupplierCost($quotation, '90.75', 'EUR', 'Motor');

        $quotation = app(ExchangeRateService::class)->applyToClientOffer($quotation);
        $usdItem = $usdItem->fresh();
        $eurItem = $eurItem->fresh();

        $this->assertSame(ExchangeRateSourceType::Multiple->value, $quotation->exchange_rate_source_type);
        $this->assertNull($quotation->exchange_rate);
        $this->assertNotNull($usdItem->exchange_rate_id);
        $this->assertNotNull($eurItem->exchange_rate_id);
        $this->assertNotSame($usdItem->exchange_rate_id, $eurItem->exchange_rate_id);
        $this->assertSame('2026-08-01', $usdItem->exchange_rate_date?->toDateString());
        $this->assertSame(ExchangeRateSourceType::ConfiguredFallback->value, $eurItem->exchange_rate_source_type);
    }

    public function test_offer_recalculation_rolls_back_every_item_when_a_later_currency_fails(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', '36.500000');
        Config::set('services.exchange_rate.manual_usd_nio_date', '2026-08-01');
        [$quotation, $validItem] = $this->quotationWithSelectedSupplierCost('100.00');
        $this->addSelectedSupplierCost($quotation, '20.00', 'JPY', 'Partida invalida');

        try {
            app(ExchangeRateService::class)->applyToClientOffer($quotation);
            $this->fail('La moneda invalida debio cancelar el recalculo completo.');
        } catch (ValidationException) {
            $this->assertNull($validItem->fresh()->supplier_unit_cost_nio);
            $this->assertSame('0.00', $quotation->fresh()->subtotal);
        }
    }

    public function test_finance_user_can_register_a_manual_rate_from_the_operational_screen(): void
    {
        $approver = $this->rateApprover();
        $this->actingAs($approver);

        $this->get(route('finance.exchange-rates'))->assertOk();
        Livewire::test(ExchangeRateManager::class)
            ->set('baseCurrency', 'EUR')
            ->set('rate', '40.250000')
            ->set('rateDate', '2026-08-01')
            ->call('saveRate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'EUR',
            'quote_currency' => 'NIO',
            'rate' => '40.250000',
            'approved_by' => $approver->id,
            'source_type' => ExchangeRateSourceType::ManualApproved->value,
        ]);
    }

    public function test_finance_can_add_a_currency_and_register_its_global_rate(): void
    {
        $approver = $this->rateApprover();

        Livewire::actingAs($approver)
            ->test(ExchangeRateManager::class)
            ->set('newCurrencyCode', 'GBP')
            ->set('newCurrencyName', 'Libra esterlina')
            ->set('newCurrencySymbol', '£')
            ->call('addCurrency')
            ->assertSet('baseCurrency', 'GBP')
            ->set('rate', '49.250000')
            ->call('saveRate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('currencies', ['code' => 'GBP', 'is_active' => true]);
        $this->assertSame('4925.00', app(ExchangeRateService::class)->convert('100.00', 'GBP', 'NIO')['amount']);
    }

    public function test_automatic_refresh_supports_enabled_cross_currency(): void
    {
        Currency::where('code', 'EUR')->update(['automatic_updates_enabled' => true]);
        Config::set('services.exchange_rate.api_url', 'https://rates.test/latest');
        Http::fake(['rates.test/*' => Http::response([
            'base' => 'USD',
            'date' => '2026-08-01',
            'rates' => ['EUR' => '0.800000', 'NIO' => '36.800000'],
        ])]);

        $rate = app(ExchangeRateService::class)->refreshAutomaticRate('EUR');

        $this->assertSame('46.000000', $rate->rate);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'EUR',
            'quote_currency' => 'NIO',
            'rate' => '46.000000',
        ]);
    }

    public function test_employee_requests_rate_change_and_admin_approves_it(): void
    {
        $employee = User::factory()->create();
        $employee->permissions()->syncWithoutDetaching(Permission::where('slug', 'finance.view')->pluck('id'));

        Livewire::actingAs($employee)
            ->test(ExchangeRateManager::class)
            ->set('baseCurrency', 'USD')
            ->set('rate', '36.700000')
            ->set('rateDate', '2026-08-01')
            ->set('changeReason', 'Actualizar la tasa utilizada en una operación pendiente.')
            ->call('saveRate')
            ->assertHasNoErrors();

        $request = \App\Models\ExchangeRateChangeRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertDatabaseMissing('exchange_rates', ['rate' => '36.700000']);

        Livewire::actingAs($this->rateApprover())
            ->test(ExchangeRateManager::class)
            ->call('approveRequest', $request->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertDatabaseHas('exchange_rates', ['rate' => '36.700000']);
    }

    public function test_fixed_usd_rate_does_not_expire_or_accept_automatic_refresh(): void
    {
        Currency::where('code', 'USD')->update(['is_fixed' => true, 'automatic_updates_enabled' => false]);
        $service = app(ExchangeRateService::class);
        $stored = $service->approveManualRate('USD', 'NIO', '36.624300', '2026-08-01', $this->rateApprover());
        $this->assertNull($stored->expires_at);

        Carbon::setTestNow('2027-08-01 10:00:00');
        $this->assertSame('36.624300', $service->usdToNio()['rate']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('configurada como fija');
        $service->refreshAutomaticRate('USD');
    }

    private function disableUsdFallbacks(): void
    {
        Config::set('services.exchange_rate.api_url', null);
        Config::set('services.exchange_rate.manual_usd_nio', null);
        Config::set('services.exchange_rate.manual_usd_nio_date', null);
    }

    private function storedRate(string $rate, string $date, ExchangeRateSourceType $source): ExchangeRate
    {
        return ExchangeRate::create([
            'base_currency' => 'USD',
            'quote_currency' => 'NIO',
            'rate' => $rate,
            'rate_date' => $date,
            'expires_at' => Carbon::parse($date)->addDays(3)->toDateString(),
            'source' => 'rates.test',
            'source_type' => $source,
            'is_fallback' => false,
        ]);
    }

    private function rateApprover(): User
    {
        $user = User::factory()->create();
        $permissions = Permission::whereIn('slug', [
            'finance.exchange_rates.approve',
            'finance.view',
        ])->pluck('id');
        $user->permissions()->syncWithoutDetaching($permissions);
        $user->roles()->syncWithoutDetaching(Role::where('slug', 'admin')->pluck('id'));

        return $user->fresh();
    }

    private function quotationWithSelectedSupplierCost(string $cost, string $currency = 'USD'): array
    {
        $quotation = QuotationRequest::create([
            'reference' => 'SC-RATE-'.str()->random(6),
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente tasa',
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'tax_percent' => '15.00',
        ]);

        [$item, $selection] = $this->addSelectedSupplierCost($quotation, $cost, $currency, 'Alternador');

        return [$quotation->fresh('items.supplierBidSelection.responseItem.response.supplier'), $item, $selection];
    }

    private function addSelectedSupplierCost(
        QuotationRequest $quotation,
        string $cost,
        string $currency,
        string $name,
    ): array {
        $supplier = Entity::create([
            'name' => 'Proveedor '.str()->random(8),
            'type' => 'proveedor',
            'status' => 'activo',
        ]);

        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quotation->id,
            'part_name' => $name,
            'part_number' => 'PART-'.str()->random(5),
            'description' => $name,
            'quantity' => 2,
            'unit_price' => 0,
            'line_total' => 0,
            'is_approved' => true,
        ]);

        $response = SupplierBidResponse::create([
            'quotation_request_id' => $quotation->id,
            'supplier_id' => $supplier->id,
            'currency' => $currency,
            'status' => 'received',
        ]);

        $responseItem = SupplierBidResponseItem::create([
            'supplier_bid_response_id' => $response->id,
            'quotation_request_item_id' => $item->id,
            'quoted_quantity' => 2,
            'unit_cost' => $cost,
            'currency' => $currency,
            'delivery_time' => '10 dias',
            'item_status' => 'quoted',
        ]);

        $selection = SupplierBidSelection::create([
            'quotation_request_id' => $quotation->id,
            'quotation_request_item_id' => $item->id,
            'supplier_bid_response_id' => $response->id,
            'supplier_bid_response_item_id' => $responseItem->id,
            'supplier_id' => $supplier->id,
            'selected_supplier_id' => $supplier->id,
            'selected_quantity' => 2,
            'selected_unit_cost' => $cost,
            'currency' => $currency,
            'delivery_time' => '10 dias',
            'selected_at' => now(),
        ]);

        return [$item, $selection];
    }
}
