<?php

namespace Tests\Feature;

use App\Livewire\Compras\SupplierPriceBook;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupplierPriceQuotation;
use App\Models\User;
use App\Services\SupplierPriceBookService;
use App\Services\SupplierPriceImport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SupplierPriceBookTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']));
        $this->actingAs($user);

        return $user;
    }

    private function payload(): array
    {
        $supplier = Entity::create(['type' => 'proveedor', 'name' => 'Proveedor de prueba '.Str::uuid()]);

        return ['supplier_id' => $supplier->id, 'quoted_at' => today()->toDateString(), 'items' => [
            ['part_number' => 'PN-'.Str::uuid(), 'description' => 'Filtro hidráulico', 'quantity' => '2.5', 'unit' => 'Unidad', 'unit_price' => '12.3456', 'total_price' => '1', 'availability' => 'available'],
        ]];
    }

    public function test_records_independent_history_in_usd_with_private_evidence_and_idempotency(): void
    {
        $this->admin();
        Storage::fake('private');
        $data = $this->payload();
        $token = (string) Str::uuid();
        $service = app(SupplierPriceBookService::class);
        $quote = $service->record($data, $token, UploadedFile::fake()->create('oferta.pdf', 10, 'application/pdf'));
        $this->assertSame($quote->id, $service->record($data, $token)->id);
        $this->assertSame('USD', $quote->currency);
        $line = $quote->items()->first();
        $this->assertSame('30.86', $line->total_price);
        $this->assertSame('12.3456', $line->unit_price);
        $this->assertSame('pending', $line->availability);
        Storage::disk('private')->assertExists($quote->attachment_path);
        $data['items'][0]['unit_price'] = '15';
        $new = $service->record($data, (string) Str::uuid());
        $this->assertNotSame($quote->id, $new->id);
        $this->assertSame('12.3456', $line->fresh()->unit_price);
        $this->get(route('purchases.supplier-prices.document', $quote))->assertOk()->assertDownload('oferta.pdf');
    }

    public function test_availability_confirmation_preserves_prices_and_rejects_stale_changes(): void
    {
        $user = $this->admin();
        $service = app(SupplierPriceBookService::class);
        $item = $service->record($this->payload(), (string) Str::uuid())->items()->first();
        $service->confirm($item->id, 'available', 'Confirmado por Ana', 0);
        $item->refresh();
        $this->assertSame('available', $item->availability);
        $this->assertSame($user->id, $item->availability_checked_by);
        $this->assertNotNull($item->availability_checked_at);
        $this->assertSame('12.3456', $item->unit_price);
        $this->expectException(ValidationException::class);
        $service->confirm($item->id, 'unavailable', '', 0);
    }

    public function test_invalid_supplier_future_dates_and_negative_costs_are_not_saved(): void
    {
        $this->admin();
        $data = $this->payload();
        Entity::whereKey($data['supplier_id'])->update(['type' => 'cliente']);
        $data['quoted_at'] = today()->addDay()->toDateString();
        $data['items'][0]['unit_price'] = '-10';
        try {
            app(SupplierPriceBookService::class)->record($data, (string) Str::uuid());
            $this->fail('Invalid offer was saved');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier_id', $e->errors());
            $this->assertArrayHasKey('quoted_at', $e->errors());
            $this->assertArrayHasKey('items.0.unit_price', $e->errors());
        }
        $this->assertFalse(SupplierPriceQuotation::where('supplier_id', $data['supplier_id'])->exists());
    }

    public function test_users_without_cost_permission_cannot_view_or_mutate_the_book(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('purchases.supplier-prices'))->assertForbidden();
        Livewire::actingAs($user)->test(SupplierPriceBook::class)->assertForbidden();
        $this->assertFalse(Gate::allows('create', SupplierPriceQuotation::class));
    }

    public function test_cost_readers_cannot_write_or_download_documents_without_the_extra_permissions(): void
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['slug' => 'supplier_bid.view_costs'], ['name' => 'Ver costos', 'module' => 'supplier_bid']);
        $user->permissions()->attach($permission);
        $this->actingAs($user);
        $this->assertTrue(Gate::allows('viewAny', SupplierPriceQuotation::class));
        $this->assertFalse(Gate::allows('create', SupplierPriceQuotation::class));
        $this->assertFalse(Gate::allows('document', new SupplierPriceQuotation));
        Livewire::test(SupplierPriceBook::class)->call('create')->assertForbidden();
    }

    public function test_livewire_form_search_and_supplier_filters(): void
    {
        $this->admin();
        $data = $this->payload();
        $widget = Livewire::test(SupplierPriceBook::class)->call('create')->set('draft', $data)
            ->call('save')->assertHasNoErrors()->assertSee($data['items'][0]['part_number']);
        $widget->set('search', 'not-a-part-'.Str::uuid())->assertDontSee($data['items'][0]['part_number']);
        $widget->set('search', $data['items'][0]['part_number'])->assertSee($data['items'][0]['part_number']);
        $widget->set('supplierFilter', '999999999')->assertDontSee($data['items'][0]['part_number']);
    }

    public function test_text_import_preserves_part_numbers_and_rejects_formulas_and_oversize_batches(): void
    {
        $parser = app(SupplierPriceImport::class);
        $head = implode("\t", SupplierPriceImport::HEADERS)."\n";
        $rows = $parser->text($head."00123\tFiltro\t2.5\tUnidad\t12.3456");
        $this->assertSame('00123', $rows[0]['part_number']);
        $this->assertSame('12.3456', $rows[0]['unit_price']);
        foreach ([$head."123\tFiltro\t1\tUnidad\t=1+2", $head.str_repeat("123\tFiltro\t1\tUnidad\t2\n", 501)] as $input) {
            try {
                $parser->text($input);
                $this->fail('Unsafe or oversize data accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_excel_import_reads_values_without_evaluating_formulas(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(SupplierPriceImport::HEADERS, null, 'A1');
        $sheet->setCellValueExplicit('A2', '00123', DataType::TYPE_STRING);
        $sheet->fromArray(['Filtro', 2.5, 'Unidad', 12.3456], null, 'B2');
        $path = tempnam(sys_get_temp_dir(), 'price_test_');
        try {
            (new Xlsx($book))->save($path);
            $rows = app(SupplierPriceImport::class)->spreadsheet($path);
            $this->assertSame('00123', $rows[0]['part_number']);
            $this->assertSame('12.3456', $rows[0]['unit_price']);
            $sheet->setCellValue('E2', '=100/2');
            (new Xlsx($book))->save($path);
            $this->expectException(ValidationException::class);
            app(SupplierPriceImport::class)->spreadsheet($path);
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_detail_and_confirmation_actions_work_without_changing_historical_amounts(): void
    {
        $this->admin();
        $quote = app(SupplierPriceBookService::class)->record($this->payload(), (string) Str::uuid());
        $item = $quote->items()->first();
        Livewire::test(SupplierPriceBook::class)->call('detail', $quote->id)->assertSee('Consultar')
            ->call('check', $item->id)->set('availability', 'available')->set('availabilityNotes', 'Confirmó ventas del proveedor')
            ->call('confirm')->assertHasNoErrors()->assertSee('Disponible al consultar');
        $this->assertSame('12.3456', $item->fresh()->unit_price);
    }
}
