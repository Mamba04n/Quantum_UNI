<?php

namespace Tests\Feature;

use App\Domain\Ventas\Cotizaciones\Services\BulkItemParserService;
use App\Domain\Ventas\Cotizaciones\Services\QuotationItemManagementService;
use App\Domain\Ventas\LicitacionesProveedor\Services\SupplierBidSpreadsheetParser;
use App\Livewire\Ventas\Cotizaciones\Kanban\GestorKanban;
use App\Livewire\Ventas\Cotizaciones\OfertaCliente\GestorOfertaCliente;
use App\Livewire\Ventas\Cotizaciones\SolicitudesCliente\FormularioSolicitudCliente;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\Permission;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class QuotationItemEditingAndBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_excel_items_template(): void
    {
        $user = $this->salesUser();

        $response = $this->actingAs($user)->get(route('sales.quotations.template.excel-items'));

        $response->assertStatus(200);
        $this->assertStringContainsString('Plantilla_Items_Cotizacion_Quantum.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_can_download_supplier_response_template_with_quotation_items(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'RFQ-100',
            'part_name' => 'Bomba hidráulica',
            'description' => 'Bomba hidráulica',
            'quantity' => 2,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($user)->get(route('sales.quotations.template.supplier-response', [
            'quotationRequest' => $quote,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Respuesta_Proveedor_', (string) $response->headers->get('content-disposition'));
    }

    public function test_can_download_client_offer_price_template(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'CLIENT-100',
            'part_name' => 'Producto oferta',
            'description' => 'Producto oferta',
            'quantity' => 2,
            'unit_price' => 750,
            'line_total' => 1500,
            'warranty_type' => 'B1',
            'has_warranty_extension' => true,
        ]);

        $response = $this->actingAs($user)->get(route('sales.quotations.template.client-offer', $quote));

        $response->assertOk();
        $this->assertStringContainsString('Precios_Oferta_Cliente_', (string) $response->headers->get('content-disposition'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'client-offer-template-').'.xlsx';
        file_put_contents($path, $content);

        try {
            $sheet = IOFactory::load($path)->getSheetByName('Precios Cliente');
            $this->assertNotNull($sheet);

            $warranty = $sheet->getCell('G5')->getDataValidation();
            $this->assertSame(DataValidation::TYPE_LIST, $warranty->getType());
            $this->assertTrue($warranty->getShowDropDown());
            $this->assertSame('"Ninguna,A1,A2,B1,B2,C1,C2"', $warranty->getFormula1());

            $plus = $sheet->getCell('H5')->getDataValidation();
            $this->assertSame(DataValidation::TYPE_LIST, $plus->getType());
            $this->assertTrue($plus->getShowDropDown());
            $this->assertSame('"SI,NO"', $plus->getFormula1());
        } finally {
            @unlink($path);
        }
    }

    public function test_quote_detail_uses_its_current_workflow_stage_instead_of_the_stale_route(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);

        Livewire::actingAs($user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $quote->id])
            ->assertSet('selectedQuoteId', $quote->id)
            ->assertSet('activeTab', 'bid')
            ->assertSet('showDetail', true);
    }

    public function test_client_offer_keeps_exact_decimal_price(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED])->save();
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_name' => 'Producto decimal',
            'description' => 'Producto decimal',
            'quantity' => 3,
            'unit_price' => 0,
            'line_total' => 0,
            'is_approved' => true,
            'version' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $quote->id])
            ->call('updateClientPrice', $item->id, '821.37', 1)
            ->assertHasNoErrors();

        $item->refresh();
        $quote->refresh();
        $this->assertSame('821.37', $item->unit_price);
        $this->assertSame('2464.11', $item->line_total);
        $this->assertSame('2464.11', $quote->subtotal);
        $this->assertSame('2833.73', $quote->total_amount);
    }

    public function test_client_offer_revert_waits_for_admin_approval_before_invalidating_pdf(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill([
            'status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'commercial_phase' => 'client_offer',
            'client_offer_pdf_path' => 'client-offers/outdated.pdf',
            'client_offer_generated_at' => now(),
        ])->save();

        Livewire::actingAs($user)
            ->test(GestorOfertaCliente::class, ['quoteId' => $quote->id])
            ->call('revertStage', $quote->id)
            ->assertSet('showStageRevertRequestModal', true)
            ->set('stageRevertReason', 'Necesito corregir la selección del proveedor.')
            ->call('submitStageRevertRequest')
            ->assertHasNoErrors();

        $quote->refresh();
        $this->assertSame(QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, $quote->status);
        $this->assertSame('client_offer', $quote->commercial_phase);
        $this->assertSame('client-offers/outdated.pdf', $quote->client_offer_pdf_path);
        $this->assertNotNull($quote->client_offer_generated_at);
        $this->assertDatabaseHas('quotation_stage_change_requests', [
            'quotation_request_id' => $quote->id,
            'from_status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            'to_status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            'status' => 'pending',
        ]);
    }

    public function test_general_item_editor_persists_supplier_cost_and_client_price(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);

        app(QuotationItemManagementService::class)->updateItems($quote, [[
            'part_code' => 'PRICE-001',
            'part_name' => 'Partida con precios',
            'quantity' => 2,
            'unit' => 'PZA',
            'supplier_currency' => 'NIO',
            'supplier_unit_cost' => '125.75',
            'client_unit_price' => '210.40',
        ]], [], $user, 'Registro completo de partida');

        $item = $quote->items()->where('part_number', 'PRICE-001')->firstOrFail();

        $this->assertSame('NIO', $item->supplier_currency);
        $this->assertSame('125.75', $item->supplier_unit_cost_original);
        $this->assertSame('125.75', $item->supplier_unit_cost_nio);
        $this->assertSame('210.40', $item->unit_price);
        $this->assertSame('420.80', $item->line_total);
        $this->assertSame('420.80', $quote->fresh()->subtotal);
        $this->assertSame('483.92', $quote->fresh()->total_amount);
    }

    public function test_supplier_response_spreadsheet_parser_maps_bid_fields(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['ITEM_ID', 'ESTADO', 'CODIGO_COTIZADO', 'DESCRIPCION_COTIZADA', 'CANTIDAD_COTIZADA', 'PRECIO_UNITARIO', 'MONEDA', 'TIEMPO_ENTREGA', 'FECHA_ESTIMADA', 'MARCA', 'MODELO', 'NUMERO_SERIE', 'GARANTIA', 'NOTAS', 'CONDICIONES_PAGO', 'VALIDEZ_HASTA'],
            [42, 'ALTERNATIVA', 'ALT-42', 'Alternativa compatible', 3, 125.50, 'USD', '15 días', '', 'Parker', 'PX-10', 'SN-1', '12 meses', 'Incluye accesorios', 'Crédito 30 días', '2026-12-31'],
            [43, 'NO_DISPONIBLE', '', '', 1, '', 'USD', '', '', '', '', '', '', 'Sin existencia'],
        ]);
        $spreadsheet->getActiveSheet()->setTitle('Respuesta Proveedor');
        $control = $spreadsheet->createSheet();
        $control->setTitle('Control');
        $control->fromArray([
            ['CAMPO', 'VALOR'],
            ['COTIZACION_ID', 13],
            ['RFQ_ID', 25],
            ['PROVEEDOR_ID', 8],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'supplier-bid-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $workbook = (new SupplierBidSpreadsheetParser())->parseWorkbook($path);
        } finally {
            @unlink($path);
        }

        $rows = $workbook['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame('13', $workbook['metadata']['COTIZACION_ID']);
        $this->assertSame('25', $workbook['metadata']['RFQ_ID']);
        $this->assertSame('8', $workbook['metadata']['PROVEEDOR_ID']);
        $this->assertSame(42, $rows[0]['item_id']);
        $this->assertSame('alternative', $rows[0]['item_status']);
        $this->assertSame('ALT-42', $rows[0]['quoted_part_number']);
        $this->assertSame('125.5', $rows[0]['unit_cost']);
        $this->assertSame('12 meses', $rows[0]['warranty']);
        $this->assertSame('Crédito 30 días', $rows[0]['payment_terms']);
        $this->assertSame('not_quoted', $rows[1]['item_status']);
        $this->assertTrue($rows[1]['not_quoted']);
    }

    public function test_bulk_item_parser_handles_tsv_csv_and_pipes(): void
    {
        $parser = new BulkItemParserService();

        // 1. TSV copiado de Excel (con encabezado)
        $tsvText = "CÓDIGO\tDESCRIPCIÓN\tCANTIDAD\tUNIDAD\tMARCA\n5580101693\tEje diferencial Trasero\t2\tPZA\tCaterpillar\n5580101692\tEje diferencial Delantero\t1\tPZA\tCaterpillar";
        $tsvResult = $parser->parse($tsvText);

        $this->assertCount(2, $tsvResult);
        $this->assertSame('5580101693', $tsvResult[0]['part_code']);
        $this->assertSame('Eje diferencial Trasero', $tsvResult[0]['part_name']);
        $this->assertSame(2, $tsvResult[0]['quantity']);
        $this->assertSame('PZA', $tsvResult[0]['unit']);
        $this->assertSame('Caterpillar', $tsvResult[0]['part_brand']);

        // 2. CSV separado por comas
        $csvText = "1R-0716, Filtro de combustible, 4, UND, CAT\n1R-1808, Filtro de aceite, 6, UND, CAT";
        $csvResult = $parser->parse($csvText);
        $this->assertCount(2, $csvResult);
        $this->assertSame('1R-0716', $csvResult[0]['part_code']);
        $this->assertSame(4, $csvResult[0]['quantity']);

        // 3. Separado por pipes
        $pipeText = "P-9901 | Bomba Hidráulica Principal | 1 | PZA | Parker\nP-9902 | Válvula de Alivio | 3 | PZA | Parker";
        $pipeResult = $parser->parse($pipeText);
        $this->assertCount(2, $pipeResult);
        $this->assertSame('P-9901', $pipeResult[0]['part_code']);
        $this->assertSame('Bomba Hidráulica Principal', $pipeResult[0]['part_name']);
        $this->assertSame(1, $pipeResult[0]['quantity']);
    }

    public function test_bulk_parser_reads_excel_with_custom_column_order_and_skips_example_row(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Fila 1: Indicadores / Encabezados con orden no estándar (Cantidad, Concepto, ID, Marca, Modelo, Serie)
        $sheet->setCellValue('A1', 'CANTIDAD');
        $sheet->setCellValue('B1', 'CONCEPTO');
        $sheet->setCellValue('C1', 'ID');
        $sheet->setCellValue('D1', 'MARCA');
        $sheet->setCellValue('E1', 'MODELO');
        $sheet->setCellValue('F1', 'SERIE');

        // Fila 2: Ejemplo de muestra
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', '[EJEMPLO] Filtro muestra');
        $sheet->setCellValue('C2', 'EJ-001');
        $sheet->setCellValue('D2', 'CAT');
        $sheet->setCellValue('E2', '320D');
        $sheet->setCellValue('F2', 'SER-001');

        // Fila 3: Fila de datos 1 (debajo del ejemplo)
        $sheet->setCellValue('A3', '3');
        $sheet->setCellValue('B3', 'Válvula de alivio principal');
        $sheet->setCellValue('C3', '14622357');
        $sheet->setCellValue('D3', 'Volvo');
        $sheet->setCellValue('E3', 'EC210');
        $sheet->setCellValue('F3', 'SN-9988');

        // Fila 4: Fila de datos 2 (debajo del ejemplo)
        $sheet->setCellValue('A4', '5');
        $sheet->setCellValue('B4', 'Perno de zapata templado');
        $sheet->setCellValue('C4', '997999');
        $sheet->setCellValue('D4', 'Komatsu');
        $sheet->setCellValue('E4', 'PC200');
        $sheet->setCellValue('F4', 'SER-4455');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_custom_excel_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $parser = new BulkItemParserService();
        $items = $parser->parseExcelFile($tempPath);
        @unlink($tempPath);

        // Fila 1 fue encabezado, Fila 2 (ejemplo) fue ignorada, y se tomaron los datos debajo (Filas 3 y 4)
        $this->assertCount(2, $items);

        $this->assertSame('14622357', $items[0]['part_code']);
        $this->assertSame('Válvula de alivio principal', $items[0]['part_name']);
        $this->assertSame(3, $items[0]['quantity']);
        $this->assertSame('Volvo', $items[0]['part_brand']);
        $this->assertSame('EC210', $items[0]['part_model']);
        $this->assertSame('SN-9988', $items[0]['part_serial_number']);

        $this->assertSame('997999', $items[1]['part_code']);
        $this->assertSame('Perno de zapata templado', $items[1]['part_name']);
        $this->assertSame(5, $items[1]['quantity']);
        $this->assertSame('Komatsu', $items[1]['part_brand']);
    }

    public function test_bulk_parser_skips_example_row_and_matches_inventory_products(): void
    {
        $product = Product::factory()->create([
            'sku' => 'CAT-100',
            'part_number' => 'CAT-100',
            'name' => 'Cilindro Hidráulico CAT',
            'brand' => 'Caterpillar',
            'stock_on_hand' => 15,
        ]);

        $textWithExample = "CANTIDAD\tCONCEPTO\tID\tMARCA\n1\t[EJEMPLO] Muestra de pieza\tEJ-001\tCAT\n3\tCilindro Hidráulico CAT\tCAT-100\tCaterpillar\n2\tPieza Nueva No Existente\tMANUAL-99\tParker";

        $parser = new BulkItemParserService();
        $result = $parser->parse($textWithExample);

        // Fila 1 (encabezado) y Fila 2 (ejemplo) fueron ignoradas automáticamente
        $this->assertCount(2, $result);

        // Ítem 1 (debajo del ejemplo) coincide con producto existente en inventario
        $this->assertSame('CAT-100', $result[0]['part_code']);
        $this->assertSame('Cilindro Hidráulico CAT', $result[0]['part_name']);
        $this->assertSame(3, $result[0]['quantity']);
        $this->assertSame($product->id, $result[0]['product_id']);
        $this->assertTrue($result[0]['is_in_inventory']);
        $this->assertSame(15, $result[0]['product_stock']);

        // Ítem 2 es captura libre
        $this->assertSame('MANUAL-99', $result[1]['part_code']);
        $this->assertSame('Pieza Nueva No Existente', $result[1]['part_name']);
        $this->assertSame(2, $result[1]['quantity']);
        $this->assertNull($result[1]['product_id']);
        $this->assertFalse($result[1]['is_in_inventory']);
        $this->assertSame(0, $result[1]['product_stock']);
    }

    public function test_formulario_solicitud_cliente_bulk_item_import(): void
    {
        Storage::fake('private');
        $user = $this->salesUser();
        $client = Entity::create(['name' => 'Constructora S.A.', 'type' => 'cliente', 'is_active' => true]);

        $bulkText = "5580101693\tEje diferencial Trasero\t2\tPZA\tCaterpillar\n5580101692\tEje diferencial Delantero\t1\tPZA\tCaterpillar\n1R-0716\tFiltro de combustible\t4\tPZA\tCAT";

        Livewire::actingAs($user)
            ->test(FormularioSolicitudCliente::class)
            ->set('selectedClientId', (string) $client->id)
            ->set('machinery', 'Excavadora 320D')
            ->set('description', 'Cotización de repuestos de prueba para mantenimiento')
            ->set('sourceChannel', 'whatsapp')
            ->set('evidenceAttachment', UploadedFile::fake()->image('evidence.jpg'))
            ->call('openBulkModal')
            ->set('bulkPasteText', $bulkText)
            ->assertCount('bulkPreviewItems', 3)
            ->call('applyBulkItems')
            ->assertCount('items', 3)
            ->call('save')
            ->assertHasNoErrors();

        $quote = QuotationRequest::where('quote_to_name', 'Constructora S.A.')->first();
        $this->assertNotNull($quote);
        $this->assertCount(3, $quote->items);
        $this->assertSame(7, (int) $quote->request_quantity); // 2 + 1 + 4
        $this->assertSame('5580101693', $quote->part_code);
    }

    public function test_formulario_solicitud_cliente_bulk_excel_file_upload(): void
    {
        Storage::fake('private');
        $user = $this->salesUser();
        $client = Entity::create(['name' => 'Minería del Norte', 'type' => 'cliente', 'is_active' => true]);

        $tempExcelFile = $this->generateSampleExcelFile();

        Livewire::actingAs($user)
            ->test(FormularioSolicitudCliente::class)
            ->set('selectedClientId', (string) $client->id)
            ->set('machinery', 'Cargador Frontal')
            ->set('description', 'Cotización cargada vía Excel')
            ->set('sourceChannel', 'gmail')
            ->set('evidenceAttachment', UploadedFile::fake()->image('evidence.jpg'))
            ->call('openBulkModal')
            ->set('bulkExcelFile', $tempExcelFile)
            ->assertCount('bulkPreviewItems', 3)
            ->call('applyBulkItems')
            ->assertCount('items', 3)
            ->call('save')
            ->assertHasNoErrors();

        $quote = QuotationRequest::where('quote_to_name', 'Minería del Norte')->first();
        $this->assertNotNull($quote);
        $this->assertCount(3, $quote->items);
        $this->assertSame(7, (int) $quote->request_quantity);
    }

    public function test_can_edit_existing_quotation_items_and_recalculate_totals(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);

        $item1 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'OLD-001',
            'part_name' => 'Nombre Antiguo',
            'description' => 'Nombre Antiguo',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $item2 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'OLD-002',
            'part_name' => 'Filtro Antiguo',
            'description' => 'Filtro Antiguo',
            'quantity' => 2,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        Livewire::actingAs($user)
            ->test(GestorKanban::class)
            ->set('selectedQuoteId', $quote->id)
            ->call('openManageItemsModal')
            ->assertSet('showManageItemsModal', true)
            ->assertCount('editableQuotationItems', 2)
            // Modificar ítem 1
            ->set('editableQuotationItems.0.part_code', 'NEW-001-CORREGIDO')
            ->set('editableQuotationItems.0.part_name', 'Nombre Corregido por Pasante')
            ->set('editableQuotationItems.0.quantity', 5)
            // Modificar ítem 2
            ->set('editableQuotationItems.1.quantity', 10)
            ->set('quotationItemChangeReason', 'Cliente solicitó aumentar cantidades de repuestos')
            ->call('saveQuotationItems')
            ->assertHasNoErrors()
            ->assertSet('showManageItemsModal', false);

        $item1->refresh();
        $this->assertSame('NEW-001-CORREGIDO', $item1->part_number);
        $this->assertSame('Nombre Corregido por Pasante', $item1->part_name);
        $this->assertSame('5.00', $item1->quantity);

        $item2->refresh();
        $this->assertSame('10.00', $item2->quantity);

        $quote->refresh();
        $this->assertSame(15, (int) $quote->request_quantity); // 5 + 10

        // Auditoría registrada
        $log = ActivityLog::where('action', 'quotation_items.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString('Cliente solicitó aumentar cantidades', $log->description);
    }

    public function test_can_add_and_remove_items_from_existing_quotation(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);

        $item1 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'ITEM-1',
            'part_name' => 'Pieza Uno',
            'description' => 'Pieza Uno',
            'quantity' => 2,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $item2 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'ITEM-2-SOBRANTE',
            'part_name' => 'Pieza que se va a borrar',
            'description' => 'Pieza que se va a borrar',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        Livewire::actingAs($user)
            ->test(GestorKanban::class)
            ->set('selectedQuoteId', $quote->id)
            ->call('openManageItemsModal')
            // Eliminar ítem 2 (índice 1)
            ->call('removeEditableQuotationItem', 1)
            ->assertCount('editableQuotationItems', 1)
            // Agregar una nueva fila
            ->call('addEditableQuotationItem')
            ->assertCount('editableQuotationItems', 2)
            ->set('editableQuotationItems.1.part_code', 'ITEM-3-NUEVO')
            ->set('editableQuotationItems.1.part_name', 'Pieza Nueva Agregada')
            ->set('editableQuotationItems.1.quantity', 4)
            ->set('editableQuotationItems.1.unit', 'JGO')
            ->call('saveQuotationItems')
            ->assertHasNoErrors();

        // Verificar que item2 fue eliminado
        $this->assertDatabaseMissing('quotation_request_items', ['id' => $item2->id]);

        // Verificar que la nueva pieza existe
        $this->assertDatabaseHas('quotation_request_items', [
            'quotation_request_id' => $quote->id,
            'part_number' => 'ITEM-3-NUEVO',
            'quantity' => '4.00',
        ]);

        $quote->refresh();
        $this->assertCount(2, $quote->items);
        $this->assertSame(6, (int) $quote->request_quantity); // 2 + 4
    }

    public function test_item_editor_uses_the_real_workflow_stage_and_protects_future_prices(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'STAGE-001',
            'part_name' => 'Partida por etapa',
            'description' => 'Partida por etapa',
            'quantity' => 1,
            'unit' => 'PZA',
            'unit_price' => 325,
            'line_total' => 325,
            'status' => 'pending_review',
        ]);

        Livewire::actingAs($user)
            ->test(GestorKanban::class)
            ->set('selectedQuoteId', $quote->id)
            ->call('openManageItemsModal')
            ->assertSet('quotationItemEditorStage', 'supplier_bid')
            ->assertSee('Partidas a cotizar')
            ->assertDontSee('Precio cliente NIO')
            ->set('editableQuotationItems.0.client_unit_price', '9999')
            ->set('editableQuotationItems.0.quantity', 2)
            ->call('saveQuotationItems')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame('325.00', $item->unit_price);
        $this->assertSame('2.00', $item->quantity);

        $quote->forceFill(['status' => QuotationRequest::STATUS_OFFER_DRAFT])->save();

        Livewire::actingAs($user)
            ->test(GestorKanban::class)
            ->set('selectedQuoteId', $quote->id)
            ->call('openManageItemsModal')
            ->assertSet('quotationItemEditorStage', 'client_offer')
            ->assertSee('Partidas y precios')
            ->assertSee('Precio cliente NIO');
    }

    public function test_bulk_import_into_existing_quotation(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);

        $item1 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'ITEM-INITIAL',
            'part_name' => 'Pieza Inicial',
            'description' => 'Pieza Inicial',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $bulkText = "BULK-01\tBuje de bronce\t10\tPZA\tCAT\nBULK-02\tPerno de zapata\t50\tPZA\tCAT";

        Livewire::actingAs($user)
            ->test(GestorKanban::class)
            ->set('selectedQuoteId', $quote->id)
            ->call('openManageItemsModal')
            ->call('openBulkImportModalForQuote')
            ->set('bulkImportText', $bulkText)
            ->assertCount('bulkImportPreview', 2)
            ->call('applyBulkImportToEditableItems')
            ->assertCount('editableQuotationItems', 3) // 1 inicial + 2 masivas
            ->call('saveQuotationItems')
            ->assertHasNoErrors();

        $quote->refresh();
        $this->assertCount(3, $quote->items);
        $this->assertSame(61, (int) $quote->request_quantity); // 1 + 10 + 50
    }

    public function test_rfq_preparation_reflects_edited_items_and_cleans_orphans(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);

        $item1 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'KEEP-01',
            'part_name' => 'Pieza a conservar',
            'description' => 'Pieza a conservar',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $item2 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'DEL-02',
            'part_name' => 'Pieza a eliminar',
            'description' => 'Pieza a eliminar',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $supplier = Entity::create(['name' => 'Proveedor Mecánico', 'type' => 'proveedor']);

        $rfqDoc = SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'rfq_number' => 'RFQ-TEST-001',
            'status' => SupplierRfqDocument::STATUS_DRAFT,
            'selected_item_ids' => [$item1->id, $item2->id],
        ]);

        // Eliminar item2 a través del servicio
        $service = app(QuotationItemManagementService::class);
        $service->updateItems($quote, [
            [
                'id' => $item1->id,
                'part_code' => 'KEEP-01-ACTUALIZADO',
                'part_name' => 'Pieza conservada y renombrada',
                'quantity' => 2,
                'unit' => 'PZA',
            ],
        ], [$item2->id], $user, 'Ajuste de RFQ');

        $rfqDoc->refresh();
        // Item 2 debe haber sido removido automáticamente de selected_item_ids sin romper el RFQ
        $this->assertEquals([$item1->id], $rfqDoc->selected_item_ids);
    }

    public function test_client_offer_excel_opens_preview_before_applying_prices(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_OFFER_DRAFT])->save();

        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => '332/A2429',
            'part_name' => 'PACKING',
            'description' => 'PACKING',
            'quantity' => 2,
            'unit' => 'PZA',
            'unit_price' => 100,
            'line_total' => 200,
            'is_approved' => true,
            'status' => 'pending_review',
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [],
            [],
            [],
            ['ITEM_ID', 'PRODUCTO', 'PROVEEDOR', 'COSTO_NIO', 'CANTIDAD', 'PRECIO_CLIENTE_NIO', 'GARANTIA', '50_PLUS'],
            [$item->id, 'PACKING', 'Proveedor Hidráulica', 850, 2, 1250, 'A2', 'SI'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_client_offer_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $uploadFile = UploadedFile::fake()->createWithContent('oferta-cliente.xlsx', file_get_contents($tempPath));
        @unlink($tempPath);

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Ventas\Cotizaciones\OfertaCliente\GestorOfertaCliente::class, ['quoteId' => $quote->id])
            ->set('clientOfferBulkFile', $uploadFile)
            ->assertSet('showClientOfferBulkModal', true)
            ->assertCount('clientOfferBulkPreview', 1)
            ->assertSee('Vista previa de precios de Oferta Cliente');

        $this->assertSame('100.00', $item->fresh()->unit_price);

        $component
            ->call('applyClientOfferBulkImport')
            ->assertSet('showClientOfferBulkModal', false)
            ->assertHasNoErrors();

        $this->assertSame('1250.00', $item->fresh()->unit_price);
        $this->assertSame('A2', $item->fresh()->warranty_type);
        $this->assertTrue((bool) $item->fresh()->has_warranty_extension);
    }

    public function test_supplier_bid_bulk_import_and_save_persists_response(): void
    {
        Storage::fake('private');
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_SENT_TO_SUPPLIER])->save();

        $item1 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => '332/A2429',
            'part_name' => 'PACKING',
            'description' => 'PACKING',
            'quantity' => 2,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $item2 = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => '331/67318',
            'part_name' => 'O RING',
            'description' => 'O RING',
            'quantity' => 8,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $supplier = Entity::create(['name' => 'Proveedor Hidráulica', 'type' => 'proveedor', 'status' => 'activo']);

        $rfqDoc = SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'rfq_number' => 'RFQ-TEST-002',
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
            'selected_item_ids' => [$item1->id, $item2->id],
        ]);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['ITEM_ID', 'ESTADO', 'CODIGO_COTIZADO', 'DESCRIPCION_COTIZADA', 'CANTIDAD_COTIZADA', 'PRECIO_UNITARIO', 'MONEDA', 'TIEMPO_ENTREGA', 'FECHA_ESTIMADA', 'MARCA', 'MODELO', 'NUMERO_SERIE', 'GARANTIA', 'NOTAS', 'CONDICIONES_PAGO', 'VALIDEZ_HASTA'],
            [$item1->id, 'COTIZADO', '332/A2429', 'PACKING', 2, 500.00, 'USD', '5 días', '', 'JCB', '', '', 'Sin garantía', 'OK', 'Contado', ''],
            [$item2->id, 'COTIZADO', '331/67318', 'O RING', 8, 500.00, 'USD', '5 días', '', 'JCB', '', '', 'Sin garantía', 'OK', 'Contado', ''],
        ]);
        $spreadsheet->getActiveSheet()->setTitle('Respuesta Proveedor');
        $control = $spreadsheet->createSheet();
        $control->setTitle('Control');
        $control->fromArray([
            ['CAMPO', 'VALOR'],
            ['COTIZACION_ID', $quote->id],
            ['RFQ_ID', $rfqDoc->id],
            ['PROVEEDOR_ID', $supplier->id],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_rfq_resp_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $fileContent = file_get_contents($tempPath);
        @unlink($tempPath);

        $uploadFile = UploadedFile::fake()->createWithContent('respuesta.xlsx', $fileContent);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor::class, ['quoteId' => $quote->id])
            ->set('selectedSupplierId', (string) $supplier->id)
            ->set('supplierBidBulkFile', $uploadFile)
            ->assertSet('showSupplierBidBulkModal', true)
            ->assertCount('supplierBidBulkPreview', 2)
            ->call('saveSupplierBidBulkImport')
            ->assertSet('showSupplierBidBulkModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplier_bid_responses', [
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->assertDatabaseHas('supplier_bid_response_items', [
            'quotation_request_item_id' => $item1->id,
            'unit_cost' => '500.00',
            'quoted_quantity' => '2.00',
        ]);

        $this->assertDatabaseHas('supplier_bid_response_items', [
            'quotation_request_item_id' => $item2->id,
            'unit_cost' => '500.00',
            'quoted_quantity' => '8.00',
        ]);
    }

    public function test_supplier_bid_table_saves_complete_and_partial_rows_in_one_action(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_SENT_TO_SUPPLIER])->save();

        $firstItem = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'TEST-001',
            'part_name' => 'Filtro primario',
            'description' => 'Filtro primario',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);
        $secondItem = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'TEST-002',
            'part_name' => 'Filtro secundario',
            'description' => 'Filtro secundario',
            'quantity' => 2,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);

        $supplier = Entity::create(['name' => 'Proveedor tabla editable', 'type' => 'proveedor', 'status' => 'activo']);
        SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'rfq_number' => 'RFQ-TABLE-001',
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
            'selected_item_ids' => [$firstItem->id, $secondItem->id],
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor::class, ['quoteId' => $quote->id])
            ->set('selectedSupplierId', (string) $supplier->id)
            ->assertSeeHtml('supplierBidItems.'.$firstItem->id.'.unit_cost')
            ->assertSeeHtml('supplierBidItems.'.$secondItem->id.'.unit_cost')
            ->set('supplierBidItems.'.$firstItem->id.'.unit_cost', '125.50')
            ->set('supplierBidItems.'.$firstItem->id.'.warranty', '6 meses')
            ->set('supplierBidItems.'.$firstItem->id.'.delivery_time', '5 días')
            ->set('supplierBidItems.'.$secondItem->id.'.unit_cost', '45.00')
            ->set('supplierBidItems.'.$secondItem->id.'.warranty', '')
            ->set('supplierBidItems.'.$secondItem->id.'.delivery_time', '')
            ->call('saveSupplierResponseDraft', [$firstItem->id, $secondItem->id])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplier_bid_response_items', [
            'quotation_request_item_id' => $firstItem->id,
            'unit_cost' => '125.50',
            'warranty' => '6 meses',
            'delivery_time' => '5 días',
        ]);
        $this->assertDatabaseHas('supplier_bid_response_items', [
            'quotation_request_item_id' => $secondItem->id,
            'unit_cost' => '45.00',
            'warranty' => '',
            'delivery_time' => '',
        ]);
    }

    public function test_supplier_response_can_edit_quantity_add_breakdown_lines_and_remove_them(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_SENT_TO_SUPPLIER])->save();
        $item = QuotationRequestItem::create(['quotation_request_id' => $quote->id, 'part_number' => 'KIT-001', 'part_name' => 'Kit hidráulico', 'description' => 'Kit hidráulico', 'quantity' => 1, 'unit' => 'KIT', 'status' => 'pending_review']);
        $supplier = Entity::create(['name' => 'Proveedor desglose', 'type' => 'proveedor', 'status' => 'activo']);
        SupplierRfqDocument::create(['quotation_request_id' => $quote->id, 'supplier_id' => $supplier->id, 'rfq_number' => 'RFQ-KIT-001', 'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER, 'selected_item_ids' => [$item->id]]);

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor::class, ['quoteId' => $quote->id])
            ->set('selectedSupplierId', (string) $supplier->id)
            ->set("supplierBidItems.{$item->id}.quoted_quantity", 3)
            ->set("supplierBidItems.{$item->id}.unit_cost", 100)
            ->call('addSupplierBidResponseLine', $item->id);

        $extraItems = $component->get('supplierBidExtraItems');
        $extraKey = array_key_first($extraItems);
        $component
            ->set("supplierBidExtraItems.{$extraKey}.quoted_part_number", 'KIT-001-B')
            ->set("supplierBidExtraItems.{$extraKey}.quoted_description", 'Componente adicional')
            ->set("supplierBidExtraItems.{$extraKey}.quoted_quantity", 2)
            ->set("supplierBidExtraItems.{$extraKey}.unit_cost", 25)
            ->call('saveSupplierResponseDraft', [(string) $item->id, $extraKey])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplier_bid_response_items', ['quotation_request_item_id' => $item->id, 'quoted_quantity' => 3, 'unit_cost' => 100]);
        $this->assertDatabaseHas('supplier_bid_response_items', ['quotation_request_item_id' => $item->id, 'quoted_part_number' => 'KIT-001-B', 'quoted_quantity' => 2, 'unit_cost' => 25]);

        $component->call('removeSupplierBidResponseLine', $extraKey)->call('saveSupplierResponseDraft', ['__deleted__'])->assertHasNoErrors();
        $this->assertDatabaseMissing('supplier_bid_response_items', ['quotation_request_item_id' => $item->id, 'quoted_part_number' => 'KIT-001-B']);
    }

    public function test_supplier_bid_mass_save_keeps_valid_rows_when_another_row_is_invalid(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_SENT_TO_SUPPLIER])->save();
        $items = collect(['VALID-001', 'INVALID-001'])->map(fn (string $partNumber) => QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => $partNumber,
            'part_name' => $partNumber,
            'description' => $partNumber,
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]));
        $supplier = Entity::create(['name' => 'Proveedor validación por fila', 'type' => 'proveedor', 'status' => 'activo']);
        SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'rfq_number' => 'RFQ-VALIDATION-001',
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
            'selected_item_ids' => $items->pluck('id')->all(),
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor::class, ['quoteId' => $quote->id])
            ->set('selectedSupplierId', (string) $supplier->id)
            ->set('supplierBidItems.'.$items[0]->id.'.unit_cost', '75.25')
            ->set('supplierBidItems.'.$items[1]->id.'.unit_cost', 'precio inválido')
            ->call('saveSupplierResponseDraft', $items->pluck('id')->all())
            ->assertHasErrors(['supplierBidItems.'.$items[1]->id]);

        $this->assertDatabaseHas('supplier_bid_response_items', [
            'quotation_request_item_id' => $items[0]->id,
            'unit_cost' => '75.25',
        ]);
        $this->assertDatabaseMissing('supplier_bid_response_items', [
            'quotation_request_item_id' => $items[1]->id,
        ]);
    }

    public function test_supplier_bid_mass_save_detects_a_concurrent_row_change_without_overwriting_it(): void
    {
        $user = $this->salesUser();
        $quote = $this->quotation($user);
        $quote->forceFill(['status' => QuotationRequest::STATUS_SENT_TO_SUPPLIER])->save();
        $item = QuotationRequestItem::create([
            'quotation_request_id' => $quote->id,
            'part_number' => 'CONCURRENT-001',
            'part_name' => 'Partida concurrente',
            'description' => 'Partida concurrente',
            'quantity' => 1,
            'unit' => 'PZA',
            'status' => 'pending_review',
        ]);
        $supplier = Entity::create(['name' => 'Proveedor concurrencia', 'type' => 'proveedor', 'status' => 'activo']);
        SupplierRfqDocument::create([
            'quotation_request_id' => $quote->id,
            'supplier_id' => $supplier->id,
            'rfq_number' => 'RFQ-CONCURRENT-001',
            'status' => SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER,
            'selected_item_ids' => [$item->id],
        ]);

        $component = Livewire::actingAs($user)
            ->test(\App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor::class, ['quoteId' => $quote->id])
            ->set('selectedSupplierId', (string) $supplier->id)
            ->set('supplierBidItems.'.$item->id.'.unit_cost', '100.00')
            ->call('saveSupplierResponseDraft', [$item->id])
            ->assertHasNoErrors();

        $savedItem = \App\Models\SupplierBidResponseItem::query()
            ->where('quotation_request_item_id', $item->id)
            ->firstOrFail();
        $savedItem->forceFill(['unit_cost' => 175, 'updated_at' => now()->addSecond()])->saveQuietly();

        $component
            ->set('supplierBidItems.'.$item->id.'.unit_cost', '125.00')
            ->call('saveSupplierResponseDraft', [$item->id])
            ->assertHasErrors(['supplierBidItems.'.$item->id]);

        $this->assertSame('175.00', $savedItem->fresh()->unit_cost);
    }

    public function test_supplier_bid_editable_table_renders_one_row_per_item_with_five_twenty_sixty_and_one_hundred_items(): void
    {
        $user = $this->salesUser();

        foreach ([5, 20, 60, 100] as $itemCount) {
            $quote = $this->quotation($user);
            $quote->forceFill(['status' => QuotationRequest::STATUS_SENT_TO_SUPPLIER])->save();

            $items = collect();
            foreach (range(1, $itemCount) as $index) {
                $items->push(QuotationRequestItem::create([
                    'quotation_request_id' => $quote->id,
                    'part_number' => "LOAD-{$itemCount}-{$index}",
                    'part_name' => "Partida de prueba {$index}",
                    'description' => "Partida de prueba {$index}",
                    'quantity' => 1,
                    'unit' => 'PZA',
                    'status' => 'pending_review',
                ]));
            }

            $component = Livewire::actingAs($user)
                ->test(\App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor::class, ['quoteId' => $quote->id]);

            $this->assertSame($itemCount, substr_count($component->html(), '.unit_cost'));
            $this->assertSame($itemCount, substr_count($component->html(), 'supplier-bid-table-row-'));
        }
    }

    private function generateSampleExcelFile(): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'CODIGO_PARTE');
        $sheet->setCellValue('B1', 'DESCRIPCION');
        $sheet->setCellValue('C1', 'CANTIDAD');
        $sheet->setCellValue('D1', 'UNIDAD');
        $sheet->setCellValue('E1', 'MARCA');

        $sheet->setCellValue('A2', '5580101693');
        $sheet->setCellValue('B2', 'Eje diferencial Trasero');
        $sheet->setCellValue('C2', '2');
        $sheet->setCellValue('D2', 'PZA');
        $sheet->setCellValue('E2', 'Caterpillar');

        $sheet->setCellValue('A3', '5580101692');
        $sheet->setCellValue('B3', 'Eje diferencial Delantero');
        $sheet->setCellValue('C3', '1');
        $sheet->setCellValue('D3', 'PZA');
        $sheet->setCellValue('E3', 'Caterpillar');

        $sheet->setCellValue('A4', '1R-0716');
        $sheet->setCellValue('B4', 'Filtro de combustible');
        $sheet->setCellValue('C4', '4');
        $sheet->setCellValue('D4', 'PZA');
        $sheet->setCellValue('E4', 'CAT');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return UploadedFile::fake()->createWithContent('partidas.xlsx', $content);
    }

    private function salesUser(): User
    {
        $user = User::factory()->create();
        $p1 = Permission::query()->firstOrCreate(
            ['slug' => 'customer_request.create'],
            ['name' => 'Crear solicitudes', 'module' => 'commercial'],
        );
        $p2 = Permission::query()->firstOrCreate(
            ['slug' => 'customer_request.mark_in_review'],
            ['name' => 'Revisar solicitudes', 'module' => 'commercial'],
        );
        $p3 = Permission::query()->firstOrCreate(
            ['slug' => 'supplier_bid.create'],
            ['name' => 'Crear RFQ', 'module' => 'commercial'],
        );
        $p4 = Permission::query()->firstOrCreate(
            ['slug' => 'supplier_bid.add_supplier_response'],
            ['name' => 'Agregar respuesta de proveedor', 'module' => 'commercial'],
        );
        $p5 = Permission::query()->firstOrCreate(
            ['slug' => 'supplier_bid.view'],
            ['name' => 'Ver licitaciones', 'module' => 'commercial'],
        );
        $p6 = Permission::query()->firstOrCreate(
            ['slug' => 'client_offer.view'],
            ['name' => 'Ver oferta cliente', 'module' => 'commercial'],
        );
        $user->permissions()->sync([$p1->id, $p2->id, $p3->id, $p4->id, $p5->id, $p6->id]);

        return $user->fresh();
    }

    private function quotation(User $user): QuotationRequest
    {
        return QuotationRequest::create([
            'reference' => 'COT-TEST-'.str()->random(6),
            'submission_token' => (string) str()->uuid(),
            'date' => now()->toDateString(),
            'quote_to_name' => 'Cliente Prueba',
            'requested_by' => $user->name,
            'department' => 'ventas',
            'source_channel' => 'whatsapp',
            'machinery' => 'Caterpillar 320D',
            'part_code' => 'INIT-001',
            'request_quantity' => 1,
            'request_urgency' => 'media',
            'subtotal' => 0,
            'total_amount' => 0,
            'status' => QuotationRequest::STATUS_READY_FOR_BID,
            'notes' => 'Notas de prueba',
            'user_id' => $user->id,
        ]);
    }
}
