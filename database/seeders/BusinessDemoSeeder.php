<?php

namespace Database\Seeders;

use App\Models\ClientMachine;
use App\Models\Entity;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SupplierBid;
use App\Models\SupplierBidItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class BusinessDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (SaleOrder::where('reference', 'OV-2026-0001')->exists()) {
            $this->createNewCustomerRequest();
            $this->command?->info('Solicitud nueva de cinco productos verificada.');

            return;
        }

        DB::transaction(function (): void {
            $admin = User::where('email', 'admin@quantum-uni.test')->firstOrFail();

            $clientUser = User::create([
                'name' => 'María Fernanda López',
                'email' => 'compras@constructoradelpacifico.com',
                'password' => Hash::make('ClienteDemo#2026'),
                'role' => User::ROLE_CLIENTE_TIENDA,
                'phone' => '+505 8888 4100',
                'address' => 'Km 12.5 Carretera a Masaya, Managua',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $clientRole = \App\Models\Role::where('slug', User::ROLE_CLIENTE_TIENDA)->first();
            if ($clientRole) {
                $clientUser->roles()->sync([$clientRole->id]);
            }

            $client = Entity::create([
                'user_id' => $clientUser->id,
                'name' => 'Constructora del Pacífico',
                'legal_name' => 'Constructora del Pacífico, S.A.',
                'type' => 'cliente',
                'contact_info' => 'María Fernanda López - Gerente de Compras',
                'email' => $clientUser->email,
                'phone_mobile' => '+505 8888 4100',
                'phone_home' => '+505 2276 4100',
                'rfc_ruc' => 'J0310000145623',
                'country' => 'Nicaragua',
                'department' => 'Managua',
                'municipality' => 'Managua',
                'city' => 'Managua',
                'exact_address' => 'Km 12.5 Carretera a Masaya, complejo industrial Bodega 4',
                'address' => 'Km 12.5 Carretera a Masaya, complejo industrial Bodega 4, Managua',
                'website' => 'https://constructoradelpacifico.example',
                'contact_name' => 'María Fernanda López',
                'contact_position' => 'Gerente de Compras',
                'whatsapp' => '+505 8888 4100',
                'machinery' => 'Excavadoras, cargadores frontales y generadores',
                'part_types' => 'Filtros, repuestos hidráulicos, eléctricos y componentes de motor',
                'status' => 'active',
            ]);

            $machine = ClientMachine::create([
                'entity_id' => $client->id,
                'machine_type' => 'Excavadora',
                'name' => 'Excavadora CAT 320D',
                'brand' => 'Caterpillar',
                'model' => '320D',
                'serial_number' => 'CAT0320DABCD12345',
                'internal_code' => 'EQ-EXC-001',
                'engine_serial' => 'C6.4-ENG-90871',
                'year' => 2021,
                'hourmeter' => 6840,
                'hourmeter_date' => now()->toDateString(),
                'status' => 'activa',
                'notes' => 'Equipo de movimiento de tierra asignado al proyecto principal.',
                'technical_details' => 'Motor CAT C6.4; sistema hidráulico de alta presión; mantenimiento cada 500 horas.',
            ]);

            $suppliers = collect([
                [
                    'name' => 'Global Heavy Parts',
                    'legal_name' => 'Global Heavy Parts LLC',
                    'rfc_ruc' => 'US-GHP-784512',
                    'country' => 'Estados Unidos',
                    'city' => 'Miami',
                    'address' => '8200 NW 25th Street, Miami, FL 33122',
                    'phone' => '+1 305 555 0142',
                    'email' => 'sales@globalheavyparts.example',
                    'website' => 'https://globalheavyparts.example',
                    'contact_name' => 'Daniel Carter',
                    'contact_position' => 'Export Sales Manager',
                    'whatsapp' => '+1 305 555 0142',
                    'brands' => ['Caterpillar', 'Donaldson', 'Fleetguard'],
                    'product_types' => ['Filtros', 'Repuestos mecanicos', 'Motores'],
                    'machine_types' => ['Excavadora', 'Cargador frontal', 'Generador'],
                    'payment_methods' => ['Transferencia internacional', 'Credito comercial'],
                    'bank' => ['Bank of America', 'Global Heavy Parts LLC', '9876543210', 'Checking', 'USD', 'BOFAUS3N', 'Estados Unidos'],
                ],
                [
                    'name' => 'Hidráulica Centroamericana',
                    'legal_name' => 'Hidráulica Centroamericana, S.A.',
                    'rfc_ruc' => 'CR-3-101-889921',
                    'country' => 'Costa Rica',
                    'city' => 'San José',
                    'address' => 'Zona Industrial La Uruca, San José',
                    'phone' => '+506 2201 7788',
                    'email' => 'cotizaciones@hidraulicaca.example',
                    'website' => 'https://hidraulicaca.example',
                    'contact_name' => 'Andrea Vargas',
                    'contact_position' => 'Ejecutiva Regional',
                    'whatsapp' => '+506 8801 7788',
                    'brands' => ['Parker', 'Bosch Rexroth', 'Gates'],
                    'product_types' => ['Hidraulicos', 'Repuestos mecanicos', 'Lubricantes'],
                    'machine_types' => ['Excavadora', 'Montacargas', 'Tractor'],
                    'payment_methods' => ['Transferencia internacional', 'Tarjeta'],
                    'bank' => ['Banco BAC San José', 'Hidráulica Centroamericana S.A.', '001-889921-7', 'Corriente', 'USD', 'BSNJCRSJ', 'Costa Rica'],
                ],
                [
                    'name' => 'TecnoRepuestos Nicaragua',
                    'legal_name' => 'TecnoRepuestos Nicaragua, S.A.',
                    'rfc_ruc' => 'J0310000098765',
                    'country' => 'Nicaragua',
                    'city' => 'Managua',
                    'address' => 'Carretera Norte, parque industrial módulo C-12',
                    'phone' => '+505 2251 9040',
                    'email' => 'ventas@tecnorepuestos.example',
                    'website' => 'https://tecnorepuestos.example',
                    'contact_name' => 'Carlos Mendoza',
                    'contact_position' => 'Jefe de Ventas',
                    'whatsapp' => '+505 8851 9040',
                    'brands' => ['SKF', 'Timken', 'Denso'],
                    'product_types' => ['Repuestos electricos', 'Repuestos mecanicos', 'Herramientas'],
                    'machine_types' => ['Excavadora', 'Camion', 'Cargador frontal'],
                    'payment_methods' => ['Transferencia nacional', 'Credito comercial', 'Contra entrega'],
                    'bank' => ['Banco de la Producción', 'TecnoRepuestos Nicaragua S.A.', '1002003004005', 'Corriente', 'USD', 'BAPRNIMAXXX', 'Nicaragua'],
                ],
            ])->map(function (array $data): Entity {
                $supplier = Entity::create([
                    'name' => $data['name'],
                    'legal_name' => $data['legal_name'],
                    'type' => 'proveedor',
                    'contact_info' => $data['contact_name'].' - '.$data['contact_position'],
                    'rfc_ruc' => $data['rfc_ruc'],
                    'country' => $data['country'],
                    'city' => $data['city'],
                    'exact_address' => $data['address'],
                    'address' => $data['address'],
                    'phone_mobile' => $data['phone'],
                    'email' => $data['email'],
                    'website' => $data['website'],
                    'contact_name' => $data['contact_name'],
                    'contact_position' => $data['contact_position'],
                    'whatsapp' => $data['whatsapp'],
                    'status' => 'activo',
                ]);

                foreach ($data['brands'] as $value) {
                    $b = \App\Models\Brand::firstOrCreate(['name' => $value], ['slug' => \Illuminate\Support\Str::slug($value)]);
                    $supplier->brands()->syncWithoutDetaching([$b->id]);
                }
                foreach ($data['product_types'] as $value) {
                    $p = \App\Models\ProductType::firstOrCreate(['name' => $value], ['slug' => \Illuminate\Support\Str::slug($value)]);
                    $supplier->productTypes()->syncWithoutDetaching([$p->id]);
                }
                foreach ($data['machine_types'] as $value) {
                    $m = \App\Models\MachineType::firstOrCreate(['name' => $value], ['slug' => \Illuminate\Support\Str::slug($value)]);
                    $supplier->machineTypes()->syncWithoutDetaching([$m->id]);
                }
                foreach ($data['payment_methods'] as $value) {
                    $pm = \App\Models\PaymentMethod::firstOrCreate(['name' => $value], ['slug' => \Illuminate\Support\Str::slug($value)]);
                    $supplier->paymentMethods()->syncWithoutDetaching([$pm->id]);
                }

                \App\Models\EntityBankAccount::create([
                    'entity_id' => $supplier->id,
                    'bank_name' => $data['bank'][0],
                    'account_holder' => $data['bank'][1],
                    'account_number' => $data['bank'][2],
                    'account_type' => $data['bank'][3],
                    'currency' => $data['bank'][4],
                    'swift' => $data['bank'][5],
                    'country' => $data['bank'][6],
                    'notes' => 'Cuenta verificada para pagos de órdenes de compra.',
                ]);

                return $supplier;
            });

            $productData = [
                ['Filtro de aceite de motor', 'QS-FLT-001', '1R-1808', 'Fleetguard', 'filtracion', 28, 18.50, 32.00],
                ['Filtro hidráulico de retorno', 'QS-FLT-002', '1G-8878', 'Donaldson', 'filtracion', 16, 42.00, 68.00],
                ['Kit de sellos cilindro boom', 'QS-HYD-003', '320D-BSK-01', 'Parker', 'hidraulico', 8, 185.00, 275.00],
                ['Sensor de presión hidráulica', 'QS-ELC-004', '274-6721', 'Denso', 'electrico', 12, 96.00, 148.00],
                ['Rodamiento de giro principal', 'QS-MEC-005', 'CAT-320D-SB', 'SKF', 'mecanico', 4, 640.00, 895.00],
            ];

            $products = collect($productData)->map(fn (array $item) => Product::create([
                'name' => $item[0],
                'sku' => $item[1],
                'part_number' => $item[2],
                'description' => $item[0].' compatible con excavadora Caterpillar 320D.',
                'type' => 'pieza',
                'piece_rotation_type' => 'alta_rotacion',
                'technical_category' => $item[4],
                'brand' => $item[3],
                'cost' => $item[6],
                'sales_price' => $item[7],
                'stock_on_hand' => $item[5],
                'total_cost' => $item[5] * $item[6],
                'active' => true,
            ]));

            $request = QuotationRequest::create([
                'reference' => 'SC-2026-0001',
                'date' => now()->toDateString(),
                'quote_to_name' => $client->legal_name,
                'quote_to_email' => $client->email,
                'quote_to_email_alt' => 'proyectos@constructoradelpacifico.com',
                'consignee_name' => 'Bodega Central - Constructora del Pacífico',
                'consignee_handling_notes' => 'Llamar 30 minutos antes. Entregar con factura y packing list.',
                'consignee_address' => $client->exact_address,
                'delivery_date' => now()->addDays(15)->toDateString(),
                'ship_via' => 'Terrestre',
                'shipping_conditions' => 'Entrega DDP Managua',
                'payment_terms' => '50% anticipo y 50% contra entrega',
                'validity_days' => '30',
                'requested_by' => 'María Fernanda López',
                'approved_by' => 'Ing. Roberto Castillo',
                'department' => 'Compras y Mantenimiento',
                'source_channel' => 'portal_cliente',
                'machinery' => $machine->name,
                'machine_brand' => $machine->brand,
                'machine_model' => $machine->model,
                'part_code' => 'SOL-MANT-320D',
                'request_quantity' => 5,
                'request_urgency' => 'alta',
                'subtotal' => 0,
                'tax_percent' => 15,
                'total_amount' => 0,
                'currency' => 'USD',
                'exchange_rate' => 36.624300,
                'exchange_rate_date' => now()->toDateString(),
                'exchange_rate_source' => 'manual_demo',
                'exchange_rate_is_fallback' => false,
                'status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                'commercial_phase' => 'licitacion_proveedor',
                'notes' => 'Solicitud completa de cinco repuestos para mantenimiento preventivo y correctivo.',
                'user_id' => $admin->id,
                'client_machine_id' => $machine->id,
            ]);

            $quantities = [4, 3, 2, 2, 1];
            $requestItems = $products->values()->map(function (Product $product, int $index) use ($request, $machine, $quantities): QuotationRequestItem {
                $quantity = $quantities[$index];
                $lineTotal = $quantity * (float) $product->sales_price;

                return QuotationRequestItem::create([
                    'quotation_request_id' => $request->id,
                    'product_id' => $product->id,
                    'client_machine_id' => $machine->id,
                    'part_name' => $product->name,
                    'part_number' => $product->part_number,
                    'description' => $product->description,
                    'brand' => $product->brand,
                    'model' => 'CAT 320D',
                    'serial_number' => $machine->serial_number,
                    'machine_brand' => $machine->brand,
                    'machine_model' => $machine->model,
                    'quantity' => $quantity,
                    'unit' => 'unidad',
                    'unit_cost' => $product->cost,
                    'unit_price' => $product->sales_price,
                    'line_total' => $lineTotal,
                    'notes' => 'Repuesto nuevo, original u OEM de calidad equivalente.',
                    'status' => 'supplier_quoted',
                    'supplier_currency' => 'USD',
                    'supplier_unit_cost_original' => $product->cost,
                    'exchange_rate' => 36.624300,
                    'supplier_unit_cost_nio' => (float) $product->cost * 36.624300,
                    'client_currency' => 'USD',
                    'warranty_type' => '6 meses contra defectos de fabricación',
                    'has_warranty_extension' => false,
                    'is_approved' => true,
                ]);
            });

            $requestSubtotal = (float) $requestItems->sum('line_total');
            $request->update([
                'subtotal' => $requestSubtotal,
                'total_amount' => $requestSubtotal * 1.15,
            ]);

            $suppliers->values()->each(function (Entity $supplier, int $supplierIndex) use ($request, $requestItems, $admin): void {
                $costFactor = [0.96, 0.91, 0.94][$supplierIndex];
                $bid = SupplierBid::create([
                    'quotation_request_id' => $request->id,
                    'supplier_id' => $supplier->id,
                    'reference' => 'LPR-2026-0001-P'.($supplierIndex + 1),
                    'status' => 'received',
                    'currency' => 'USD',
                    'exchange_rate' => 36.6243,
                    'payment_terms' => $supplierIndex === 2 ? 'Crédito 30 días' : '50% anticipo, saldo antes de embarque',
                    'delivery_time' => [12, 18, 7][$supplierIndex].' días calendario',
                    'warranty' => '6 meses contra defectos de fabricación',
                    'valid_until' => now()->addDays(30)->toDateString(),
                    'notes' => 'Cotización completa recibida para los cinco productos solicitados.',
                    'is_winner' => $supplierIndex === 2,
                    'winner_reason' => $supplierIndex === 2 ? 'Mejor combinación de precio, disponibilidad local y crédito.' : null,
                    'created_by' => $admin->id,
                    'selected_by' => $supplierIndex === 2 ? $admin->id : null,
                    'selected_at' => $supplierIndex === 2 ? now() : null,
                ]);

                foreach ($requestItems as $requestItem) {
                    $unitCost = round((float) $requestItem->unit_cost * $costFactor, 2);
                    SupplierBidItem::create([
                        'supplier_bid_id' => $bid->id,
                        'quotation_request_item_id' => $requestItem->id,
                        'quantity' => $requestItem->quantity,
                        'unit_cost' => $unitCost,
                        'line_total' => $unitCost * (float) $requestItem->quantity,
                        'currency' => 'USD',
                        'delivery_time' => [12, 18, 7][$supplierIndex].' días',
                        'warranty' => '6 meses',
                        'notes' => 'Precio incluye empaque comercial y documentación.',
                    ]);
                }
            });

            $orderSubtotal = $requestSubtotal;
            $order = SaleOrder::withoutEvents(fn () => SaleOrder::create([
                'reference' => 'OV-2026-0001',
                'invoice_number' => 'PROFORMA-2026-0001',
                'invoice_date' => now()->toDateString(),
                'date' => now()->toDateString(),
                'customer_id' => $client->id,
                'client_name' => $client->legal_name,
                'client_email' => $client->email,
                'client_ruc' => $client->rfc_ruc,
                'shipping_address' => $client->exact_address,
                'provider_name' => $suppliers[2]->legal_name,
                'provider_ruc' => $suppliers[2]->rfc_ruc,
                'payment_type' => 'Crédito',
                'status' => 'generated',
                'source_type' => 'approved_customer_request',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'generated_by' => $admin->id,
                'generated_at' => now(),
                'subtotal' => $orderSubtotal,
                'tax_percent' => 15,
                'tax_amount' => $orderSubtotal * 0.15,
                'total_amount' => $orderSubtotal * 1.15,
                'currency' => 'USD',
                'terms_and_conditions' => 'Entrega DDP Managua. Crédito comercial a 30 días.',
                'notes' => 'Orden demo completa generada desde la solicitud SC-2026-0001.',
            ]));

            foreach ($requestItems as $requestItem) {
                SaleOrderItem::create([
                    'sale_order_id' => $order->id,
                    'quotation_request_item_id' => $requestItem->id,
                    'product_id' => $requestItem->product_id,
                    'part_number' => $requestItem->part_number,
                    'description' => $requestItem->part_name,
                    'brand' => $requestItem->brand,
                    'model' => $requestItem->model,
                    'quantity' => $requestItem->quantity,
                    'unit_price' => $requestItem->unit_price,
                    'total_price' => $requestItem->line_total,
                    'item_status' => 'Pendiente',
                    'source_type' => 'customer_request',
                    'inventory_status' => 'pending_reservation',
                    'purchase_status' => 'supplier_selected',
                ]);
            }

            $request->update(['sale_order_id' => $order->id]);
        });

        $this->createNewCustomerRequest();

        $this->command?->info('Datos demo creados: 1 cliente, 3 proveedores, 5 productos y 1 orden con 5 items.');
    }

    private function createNewCustomerRequest(): void
    {
        if (QuotationRequest::where('reference', 'SC-00002')->exists()) {
            return;
        }

        $client = Entity::where('type', 'cliente')
            ->where('email', 'compras@constructoradelpacifico.com')
            ->firstOrFail();
        $machine = ClientMachine::where('entity_id', $client->id)->firstOrFail();
        $admin = User::where('email', 'admin@quantum-uni.test')->firstOrFail();
        $products = Product::whereIn('sku', [
            'QS-FLE-FIL-0001',
            'QS-DON-FIL-0001',
            'QS-PAR-HID-0001',
            'QS-DEN-ELE-0001',
            'QS-SKF-MEC-0001',
        ])->orderBy('id')->get();

        if ($products->count() !== 5) {
            throw new \RuntimeException('No se encontraron los cinco productos demo para crear la solicitud.');
        }

        $attachmentPath = 'customer-requests/solicitud-sc-00002.txt';
        Storage::disk('public')->put(
            $attachmentPath,
            'Evidencia demo para la solicitud SC-00002 de cinco productos.'
        );

        DB::transaction(function () use ($admin, $attachmentPath, $client, $machine, $products): void {
            $request = QuotationRequest::create([
                'reference' => 'SC-00002',
                'date' => now()->toDateString(),
                'quote_to_name' => $client->name,
                'quote_to_email' => $client->email,
                'requested_by' => $admin->name,
                'department' => 'ventas',
                'source_channel' => 'whatsapp',
                'evidence_attachment_path' => $attachmentPath,
                'machinery' => $machine->machine_type,
                'machine_brand' => $machine->brand,
                'machine_model' => $machine->model,
                'equipment_serial' => $machine->serial_number,
                'part_code' => $products->first()->part_number,
                'request_quantity' => 12,
                'request_urgency' => 'alta',
                'subtotal' => 0,
                'total_amount' => 0,
                'status' => QuotationRequest::STATUS_RECEIVED,
                'notes' => 'Nueva solicitud del cliente con cinco productos para revisión comercial.',
                'user_id' => $admin->id,
                'client_machine_id' => $machine->id,
            ]);

            $quantities = [4, 3, 2, 2, 1];

            foreach ($products->values() as $index => $product) {
                QuotationRequestItem::create([
                    'quotation_request_id' => $request->id,
                    'product_id' => $product->id,
                    'client_machine_id' => $machine->id,
                    'part_name' => $product->name,
                    'part_number' => $product->part_number,
                    'description' => $product->name,
                    'quantity' => $quantities[$index],
                    'unit' => 'unidad',
                    'notes' => 'Producto solicitado por el cliente; pendiente de revisión.',
                    'unit_price' => 0,
                    'line_total' => 0,
                    'internal_request_ref' => $request->reference,
                    'brand' => $product->brand,
                    'model' => $machine->model,
                    'serial_number' => $machine->serial_number,
                    'machine_brand' => $machine->brand,
                    'machine_model' => $machine->model,
                    'image_path' => $attachmentPath,
                    'status' => 'pending_review',
                ]);
            }
        });
    }
}
