<?php

namespace App\Http\Controllers\Testing;

use App\Http\Controllers\Controller;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ClientOfferReview;
use App\Models\ClientOfferVersion;
use App\Models\ClientOfferVersionItem;
use App\Models\Entity;
use App\Models\OrderStatus;
use App\Models\OrderStatusDocument;
use App\Models\OrderStatusEvent;
use App\Models\OrderStatusItem;
use App\Models\OrderTracker;
use App\Models\OrderTrackerAttachment;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\ShipmentBatch;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierBidSelection;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class E2ETestingController extends Controller
{
    private const MARKER = 'E2E';

    private function guardEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            abort(404);
        }
    }

    public function setup(): JsonResponse
    {
        $this->guardEnvironment();

        Artisan::call('db:seed', ['--class' => 'E2EVisualSeeder', '--no-interaction' => true]);

        return response()->json(['status' => 'ok', 'message' => 'E2E seeder executed.']);
    }

    public function setupBase(): JsonResponse
    {
        return $this->setup();
    }

    public function cleanup(): JsonResponse
    {
        $this->guardEnvironment();

        DB::transaction(function () {
            AccountReceivable::where('concept', 'like', self::MARKER.'%')->delete();
            AccountPayable::where('concept', 'like', self::MARKER.'%')->delete();

            SaleOrder::where('reference', 'like', 'E2E-ORD-%')->delete();
            QuotationRequest::where('reference', 'like', 'E2E-QR-%')->delete();
            OrderTracker::where('main_reference', 'like', 'E2E-%')->delete();

            Entity::where('email', 'like', '%@e2e.test')
                ->whereNotIn('email', ['proveedor.e2e@quantum.test', 'cliente.e2e@quantum.test'])
                ->delete();

            $productIds = Product::where('part_number', 'like', 'E2E-%')
                ->orWhere('sku', 'like', 'E2E-%')
                ->orWhere('name', 'like', '%E2E%')
                ->pluck('id');

            if ($productIds->isNotEmpty() && Schema::hasTable('purchase_order_items')) {
                DB::table('purchase_order_items')
                    ->whereIn('product_id', $productIds)
                    ->delete();
            }

            Product::where('part_number', 'like', 'E2E-%')
                ->orWhere('sku', 'like', 'E2E-%')
                ->orWhere('name', 'like', '%E2E%')
                ->delete();

            if (Schema::hasTable('tech_blog_posts')) {
                DB::table('tech_blog_posts')
                    ->where('titulo', 'like', '%E2E%')
                    ->orWhere('slug', 'like', '%e2e%')
                    ->delete();
            }

            if (Schema::hasTable('catalogs')) {
                DB::table('catalogs')
                    ->where('nombre', 'like', '%E2E%')
                    ->orWhere('titulo', 'like', '%E2E%')
                    ->delete();
            }
        });

        return response()->json(['status' => 'ok', 'message' => 'E2E data cleaned.']);
    }

    public function createCustomer(Request $request): JsonResponse
    {
        $this->guardEnvironment();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'contactName' => 'nullable|string|max:255',
            'email' => 'nullable|not_regex:/[\r\n]/|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'country' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:30',
        ]);

        $reference = $this->reference('CUST');
        $email = $validated['email'] ?? strtolower($reference).'@e2e.test';
        $entity = Entity::updateOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'] ?? 'Cliente '.$reference,
                'type' => 'cliente',
                'status' => $this->normalizeEntityStatus($validated['status'] ?? 'activo'),
                'contact_name' => $validated['contactName'] ?? 'Contacto E2E',
                'phone_mobile' => $validated['phone'] ?? '+505 0000 0000',
                'address' => $validated['address'] ?? 'Managua, Nicaragua',
                'country' => $validated['country'] ?? 'Nicaragua',
            ]
        );

        return response()->json(['id' => $entity->id, 'name' => $entity->name, 'email' => $entity->email]);
    }

    public function createSupplier(Request $request): JsonResponse
    {
        $this->guardEnvironment();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'contactName' => 'nullable|string|max:255',
            'email' => 'nullable|not_regex:/[\r\n]/|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $reference = $this->reference('SUP');
        $email = $validated['email'] ?? strtolower($reference).'@e2e.test';
        $entity = Entity::updateOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'] ?? 'Proveedor '.$reference,
                'type' => 'proveedor',
                'status' => 'activo',
                'contact_name' => $validated['contactName'] ?? 'Proveedor E2E',
                'phone_mobile' => $validated['phone'] ?? '+1 000 000 0000',
                'country' => $validated['country'] ?? 'Estados Unidos',
            ]
        );

        return response()->json(['id' => $entity->id, 'name' => $entity->name, 'email' => $entity->email]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $this->guardEnvironment();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'partNumber' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
        ]);

        $product = $this->makeProduct(
            $validated['partNumber'] ?? $this->reference('PN'),
            $validated['name'] ?? 'Producto E2E',
            $validated['brand'] ?? 'Quantum',
            $validated['model'] ?? 'QA'
        );

        return response()->json(['id' => $product->id, 'name' => $product->name, 'partNumber' => $product->part_number]);
    }

    public function createCustomerRequestReadyForBid(): JsonResponse
    {
        $this->guardEnvironment();

        return response()->json($this->commercialScenario(QuotationRequest::STATUS_READY_FOR_BID));
    }

    public function createSupplierBidReadyForResponse(): JsonResponse
    {
        $this->guardEnvironment();

        return response()->json($this->commercialScenario(QuotationRequest::STATUS_SUPPLIER_SELECTED));
    }

    public function createClientOfferReadyForReview(Request $request): JsonResponse
    {
        $this->guardEnvironment();

        return response()->json($this->commercialScenario(
            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            $request->boolean('pendingReview')
        ));
    }

    public function createAdminApprovalPending(ApprovalService $approvalService): JsonResponse
    {
        $this->guardEnvironment();

        $fixture = $this->commercialScenario(QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL, false, true);
        $quotation = QuotationRequest::with('items', 'offerVersions')->findOrFail($fixture['quotationId']);
        $admin = $this->user('admin@quantum.test', 'admin');

        $approval = $approvalService->requestInternalApproval($quotation, $admin, 'Fixture E2E: aprobacion interna pendiente.');

        return response()->json([
            ...$fixture,
            'approvalId' => $approval->id,
            'trackerId' => $approval->tracker_id,
            'trackingCode' => $approval->tracker?->tracking_code,
        ]);
    }

    public function createOrderTrackerWithStatus(OrderTrackerService $trackerService, OrderStatusService $statusService): JsonResponse
    {
        $this->guardEnvironment();

        return response()->json($this->orderScenario($trackerService, $statusService));
    }

    public function createOrderStatusPartialDelivery(OrderTrackerService $trackerService, OrderStatusService $statusService): JsonResponse
    {
        $this->guardEnvironment();

        $fixture = $this->orderScenario($trackerService, $statusService);
        $status = OrderStatus::with('items')->findOrFail((int) $fixture['orderStatusId']);
        $supplier = Entity::find($fixture['supplierId']);
        $admin = $this->user('admin@quantum.test', 'admin');
        $items = $status->items->values();

        if ($items->count() >= 1) {
            $items[0]->forceFill([
                'quantity_ordered' => 4,
                'quantity_confirmed' => 4,
                'quantity_shipped' => 4,
                'quantity_received' => 2,
                'quantity_pending' => 2,
                'supplier_id' => $supplier?->id,
                'estimated_arrival_date' => now()->addDays(5)->toDateString(),
                'warehouse_received_date' => now()->toDateString(),
                'status' => OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL,
            ])->save();
        }

        if ($items->count() >= 2) {
            $items[1]->forceFill([
                'quantity_ordered' => 2,
                'quantity_confirmed' => 2,
                'quantity_shipped' => 2,
                'quantity_received' => 0,
                'quantity_pending' => 2,
                'supplier_id' => $supplier?->id,
                'estimated_arrival_date' => now()->addDays(12)->toDateString(),
                'actual_supplier_dispatch_date' => now()->subDays(2)->toDateString(),
                'status' => OrderStatusService::STATUS_IN_TRANSIT,
            ])->save();
        }

        if ($items->count() >= 3) {
            $items[2]->forceFill([
                'quantity_ordered' => 1,
                'quantity_cancelled' => 1,
                'quantity_pending' => 0,
                'supplier_id' => $supplier?->id,
                'cancellation_reason' => 'Proveedor sin stock. Fixture E2E.',
                'status' => OrderStatusService::STATUS_CANCELLED_SUPPLIER,
            ])->save();
        }

        $batch = ShipmentBatch::create([
            'order_status_id' => $status->id,
            'supplier_id' => $supplier?->id,
            'shipment_code' => $this->reference('SHIP'),
            'carrier_name' => 'DHL E2E',
            'tracking_number' => $this->reference('AWB'),
            'origin_country' => 'USA',
            'destination_country' => 'Nicaragua',
            'departure_date' => now()->subDays(2)->toDateString(),
            'estimated_arrival_date' => now()->addDays(10)->toDateString(),
            'status' => OrderStatusService::STATUS_IN_TRANSIT,
            'notes' => 'Embarque parcial creado por fixture E2E.',
        ]);

        foreach ($items->take(2) as $item) {
            $batch->items()->firstOrCreate(
                ['order_status_item_id' => $item->id],
                ['quantity_shipped' => (float) $item->quantity_shipped ?: 1]
            );
        }

        foreach ([
            [OrderStatusService::PHASE_PURCHASE, 'purchase_order_pdf', null],
            [OrderStatusService::PHASE_TRANSIT, 'shipping_document', $items->get(1)?->id],
            [OrderStatusService::PHASE_WAREHOUSE, 'warehouse_receipt', $items->get(0)?->id],
        ] as [$phase, $type, $itemId]) {
            OrderStatusDocument::create([
                'order_status_id' => $status->id,
                'order_status_item_id' => $itemId,
                'phase' => $phase,
                'document_type' => $type,
                'file_name' => "{$type}.pdf",
                'file_path' => "e2e/{$type}.pdf",
                'uploaded_by' => $admin->id,
                'generated_by' => $admin->id,
                'notes' => 'Documento fixture E2E.',
            ]);
        }

        OrderStatusEvent::create([
            'order_status_id' => $status->id,
            'event_type' => 'e2e.partial_delivery_ready',
            'phase' => OrderStatusService::PHASE_WAREHOUSE,
            'user_id' => $admin->id,
            'description' => 'Escenario E2E con entrega parcial, transito y cancelacion por proveedor.',
        ]);

        $statusService->recalculate($status);

        return response()->json([
            ...$fixture,
            'itemIds' => $status->refresh()->load('items')->items->pluck('id')->values()->all(),
        ]);
    }

    public function createOrderStatusPackingListFlow(OrderTrackerService $trackerService, OrderStatusService $statusService): JsonResponse
    {
        $this->guardEnvironment();

        $fixture = $this->orderScenario($trackerService, $statusService);
        $status = OrderStatus::with(['saleOrder', 'items.product'])->findOrFail((int) $fixture['orderStatusId']);
        $admin = $this->user('admin@quantum.test', 'admin');

        $status->saleOrder->forceFill([
            'client_email' => 'cliente.packing@e2e.test',
            'shipping_address' => 'Direccion oficina, entregar a bodega junto con factura',
        ])->save();

        $this->attachProductReferenceImages($status);
        $this->attachOrderStatusFixtureDocuments($status, $admin);

        foreach ($status->refresh()->load(['items.product'])->items as $item) {
            $quantity = (float) $item->quantity_ordered;
            $statusService->registerCustomsEntry($item->refresh(), $quantity, $admin, 'E2E packing: entrada aduana.');
            $statusService->registerWarehouseReceipt($item->refresh(), $quantity, $admin, 'E2E packing: recepcion bodega.');
            $statusService->createKardexEntry($item->refresh(), $quantity, (float) ($item->product?->cost ?: 60), $admin);
            $statusService->createKardexOutput($item->refresh(), $quantity, $admin);
            $statusService->registerDeliveryDispatch($item->refresh(), $quantity, $admin, 'E2E packing: salida de bodega a cliente.');
        }

        $status = $status->refresh()->load(['items', 'documents']);
        $packing = $status->documents->firstWhere('document_type', 'packing_list_pdf');

        return response()->json([
            ...$fixture,
            'orderStatusUrl' => route('orders.status.show', $status->sale_order_id, false),
            'packingListDocumentId' => $packing?->id,
            'packingListUrl' => $packing ? route('order-status-documents.download', $packing, false) : null,
            'itemIds' => $status->items->pluck('id')->values()->all(),
        ]);
    }

    public function createFinanceCxp(): JsonResponse
    {
        $this->guardEnvironment();

        $supplier = $this->entity('proveedor');
        $cxp = AccountPayable::create([
            'entity_id' => $supplier->id,
            'concept' => self::MARKER.' CXP fixture',
            'total_amount' => 450,
            'paid_amount' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'pendiente',
        ]);

        return response()->json(['id' => $cxp->id, 'supplierId' => $supplier->id]);
    }

    public function createFinanceCxc(): JsonResponse
    {
        $this->guardEnvironment();

        $customer = $this->entity('cliente');
        $cxc = AccountReceivable::create([
            'entity_id' => $customer->id,
            'concept' => self::MARKER.' CXC fixture',
            'total_amount' => 650,
            'paid_amount' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'pendiente',
        ]);

        return response()->json(['id' => $cxc->id, 'customerId' => $customer->id]);
    }

    public function loginUserData(Request $request): JsonResponse
    {
        $this->guardEnvironment();

        $roleMap = [
            'admin' => 'admin@quantum.test',
            'ventas' => 'ventas@quantum.test',
            'compras' => 'compras@quantum.test',
            'agente_finanzas' => 'finanzas@quantum.test',
            'finanzas' => 'finanzas@quantum.test',
            'bodega' => 'bodega@quantum.test',
            'soporte' => 'soporte@quantum.test',
            'cliente_tienda' => 'cliente.test@quantum.test',
            'cliente' => 'cliente.test@quantum.test',
        ];

        $role = $request->input('role', 'admin');

        return response()->json([
            'email' => $roleMap[$role] ?? 'admin@quantum.test',
            'password' => 'Password123!',
        ]);
    }

    private function commercialScenario(string $status, bool $withReview = false, bool $readyForApproval = false): array
    {
        return DB::transaction(function () use ($status, $withReview, $readyForApproval) {
            $sales = $this->user('ventas@quantum.test', 'ventas');
            $customer = $this->entity('cliente');
            $supplier = $this->entity('proveedor');
            $reference = $this->reference('QR');
            $products = [
                $this->makeProduct($this->reference('PN-A'), 'Filtro hidraulico E2E', 'Fleetguard', 'HF'.$this->tinyToken()),
                $this->makeProduct($this->reference('PN-B'), 'Alternador E2E', 'Bosch', 'ALT'.$this->tinyToken()),
            ];

            $quotation = QuotationRequest::create([
                'reference' => $reference,
                'date' => now()->toDateString(),
                'quote_to_name' => $customer->name,
                'quote_to_email' => $customer->email,
                'delivery_date' => now()->addDays(15)->toDateString(),
                'ship_via' => 'Aereo',
                'shipping_conditions' => 'DAP Managua',
                'payment_terms' => 'Contado',
                'requested_by' => 'Fixture E2E',
                'department' => 'QA',
                'subtotal' => 0,
                'total_amount' => 0,
                'tax_percent' => 15,
                'status' => $status,
                'commercial_phase' => 'client_offer',
                'client_offer_pdf_path' => $readyForApproval ? 'e2e/client-offer-final.pdf' : null,
                'client_offer_generated_at' => $readyForApproval ? now() : null,
                'notes' => 'Solicitud generada por fixture E2E.',
                'user_id' => $sales->id,
            ]);

            $items = collect($products)->map(function (Product $product, int $index) use ($quotation, $supplier) {
                $quantity = $index === 0 ? 2 : 1;
                $unitCost = $index === 0 ? 80 : 220;
                $unitPrice = $index === 0 ? 180 : 340;

                return QuotationRequestItem::create([
                    'quotation_request_id' => $quotation->id,
                    'product_id' => $product->id,
                    'part_number' => $product->part_number,
                    'description' => $product->name,
                    'part_name' => $product->name,
                    'brand' => $product->brand ?? 'Quantum',
                    'model' => $product->model ?? null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'line_total' => $quantity * $unitPrice,
                    'status' => 'approved',
                    'is_approved' => true,
                    'selected_supplier_id' => $supplier->id,
                ]);
            });

            $response = SupplierBidResponse::create([
                'quotation_request_id' => $quotation->id,
                'supplier_id' => $supplier->id,
                'contact_name' => $supplier->contact_name,
                'currency' => 'USD',
                'payment_terms' => '50% anticipo',
                'general_warranty' => '90 dias',
                'general_delivery_time' => '12 dias',
                'valid_until' => now()->addDays(20)->toDateString(),
                'attachment_path' => 'e2e/proforma.pdf',
                'notes' => 'Respuesta proveedor fixture E2E.',
                'status' => 'received',
                'created_by' => $sales->id,
            ]);

            $items->each(function (QuotationRequestItem $item) use ($response, $supplier, $sales, $quotation) {
                $cost = (float) $item->unit_cost;
                $responseItem = SupplierBidResponseItem::create([
                    'supplier_bid_response_id' => $response->id,
                    'quotation_request_item_id' => $item->id,
                    'customer_request_item_id' => $item->id,
                    'quoted_part_number' => $item->part_number,
                    'quoted_description' => $item->description,
                    'quoted_model' => $item->model,
                    'quoted_brand' => $item->brand,
                    'quoted_quantity' => $item->quantity,
                    'unit_cost' => $cost,
                    'currency' => 'USD',
                    'estimated_arrival_date' => now()->addDays(12)->toDateString(),
                    'delivery_time' => '12 dias',
                    'warranty' => '90 dias',
                    'item_status' => 'quoted',
                ]);

                SupplierBidSelection::create([
                    'quotation_request_id' => $quotation->id,
                    'quotation_request_item_id' => $item->id,
                    'customer_request_item_id' => $item->id,
                    'supplier_bid_response_item_id' => $responseItem->id,
                    'selected_supplier_id' => $supplier->id,
                    'selected_quantity' => $item->quantity,
                    'selected_unit_cost' => $cost,
                    'currency' => 'USD',
                    'estimated_arrival_date' => now()->addDays(12)->toDateString(),
                    'delivery_time' => '12 dias',
                    'warranty' => '90 dias',
                    'selected_by' => $sales->id,
                    'selected_at' => now(),
                ]);
            });

            $subtotal = (float) $items->sum('line_total');
            $taxAmount = round($subtotal * 0.15, 2);
            $quotation->forceFill([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + $taxAmount,
            ])->save();

            $offer = $this->createOfferVersion($quotation, $items, $sales);
            $review = null;

            if ($withReview) {
                $review = ClientOfferReview::create([
                    'quotation_request_id' => $quotation->id,
                    'client_offer_version_id' => $offer->id,
                    'requested_by' => $sales->id,
                    'status' => ClientOfferReview::STATUS_PENDING,
                    'review_notes' => 'Oferta lista para revision admin. Fixture E2E.',
                    'requested_at' => now(),
                ]);

                $quotation->forceFill([
                    'status' => QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
                    'admin_review_requested_at' => now(),
                    'offer_review_notes' => 'Oferta lista para revision admin. Fixture E2E.',
                ])->save();
            }

            $tracker = app(OrderTrackerService::class)->trackerForQuotation($quotation);

            return [
                'quotationId' => $quotation->id,
                'reference' => $quotation->reference,
                'customerId' => $customer->id,
                'supplierId' => $supplier->id,
                'itemIds' => $items->pluck('id')->values()->all(),
                'reviewId' => $review?->id,
                'trackerId' => $tracker?->id,
                'trackingCode' => $tracker?->tracking_code,
            ];
        });
    }

    private function orderScenario(OrderTrackerService $trackerService, OrderStatusService $statusService): array
    {
        return DB::transaction(function () use ($trackerService, $statusService) {
            $admin = $this->user('admin@quantum.test', 'admin');
            $customer = $this->entity('cliente');
            $supplier = $this->entity('proveedor');
            $reference = $this->reference('ORD');
            $products = [
                $this->makeProduct($this->reference('PN-OS-A'), 'Bomba hidraulica E2E', 'Parker', 'P'.$this->tinyToken()),
                $this->makeProduct($this->reference('PN-OS-B'), 'Sensor presion E2E', 'CAT', 'S'.$this->tinyToken()),
                $this->makeProduct($this->reference('PN-OS-C'), 'Kit sellos E2E', 'Komatsu', 'K'.$this->tinyToken()),
            ];

            $order = SaleOrder::create([
                'reference' => $reference,
                'date' => now()->toDateString(),
                'customer_id' => $customer->id,
                'client_name' => $customer->name,
                'shipping_address' => $customer->address,
                'provider_name' => $supplier->name,
                'payment_type' => 'Contado',
                'status' => 'solicitado_proveedor',
                'source_type' => 'e2e_fixture',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'generated_by' => $admin->id,
                'generated_at' => now(),
                'subtotal' => 1720,
                'tax_percent' => 15,
                'tax_amount' => 258,
                'total_amount' => 1978,
                'currency' => 'USD',
                'notes' => 'Orden generada por fixture E2E.',
            ]);

            $orderItems = collect($products)->map(function (Product $product, int $index) use ($order) {
                $quantity = [4, 2, 1][$index] ?? 1;
                $unitPrice = [280, 190, 220][$index] ?? 100;

                return SaleOrderItem::create([
                    'sale_order_id' => $order->id,
                    'product_id' => $product->id,
                    'part_number' => $product->part_number,
                    'description' => $product->name,
                    'brand' => $product->brand ?? null,
                    'model' => $product->model ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'source_type' => 'e2e_fixture',
                    'inventory_status' => 'pending_creation',
                    'purchase_status' => 'required',
                ]);
            });

            $tracker = $trackerService->trackerForOrder($order);
            $tracker->forceFill([
                'customer_id' => $customer->id,
                'supplier_id' => $supplier->id,
                'operational_phase' => OrderStatusService::PHASE_TRANSIT,
                'operational_status' => OrderStatusService::STATUS_IN_TRANSIT,
                'main_reference' => $order->reference,
            ])->save();

            $activity = $trackerService->recordActivity(
                $tracker,
                'e2e_order_fixture_ready',
                "Fixture E2E creado para pedido {$order->reference}.",
                OrderStatusService::PHASE_TRANSIT,
                'sale_orders',
                $order->id,
                null,
                OrderStatusService::STATUS_IN_TRANSIT,
                $admin
            );

            OrderTrackerAttachment::create([
                'tracker_id' => $tracker->id,
                'activity_id' => $activity->id,
                'phase' => OrderStatusService::PHASE_PURCHASE,
                'source_module' => 'sale_orders',
                'source_id' => $order->id,
                'file_name' => 'orden-compra-e2e.pdf',
                'file_path' => 'e2e/orden-compra-e2e.pdf',
                'file_type' => 'pdf',
                'file_size' => 1024,
                'uploaded_by' => $admin->id,
                'comment' => 'Documento fixture E2E.',
            ]);

            $status = $statusService->ensureForSaleOrder($order, $admin);
            $status->forceFill([
                'order_tracker_id' => $tracker->id,
                'tracking_code' => $tracker->tracking_code,
                'customer_id' => $customer->id,
                'responsible_id' => $admin->id,
                'administrative_phase' => OrderStatusService::PHASE_TRANSIT,
                'administrative_status' => OrderStatusService::STATUS_IN_TRANSIT,
                'estimated_arrival_date' => now()->addDays(10)->toDateString(),
            ])->save();

            $status->items()->each(function (OrderStatusItem $item) use ($supplier) {
                $item->forceFill([
                    'supplier_id' => $supplier->id,
                    'quantity_confirmed' => $item->quantity_ordered,
                    'quantity_shipped' => $item->quantity_ordered,
                    'quantity_pending' => 0,
                    'status' => OrderStatusService::STATUS_IN_TRANSIT,
                    'estimated_arrival_date' => now()->addDays(10)->toDateString(),
                ])->save();
            });

            OrderStatusDocument::create([
                'order_status_id' => $status->id,
                'phase' => OrderStatusService::PHASE_PURCHASE,
                'document_type' => 'purchase_order_pdf',
                'file_name' => 'purchase-order-e2e.pdf',
                'file_path' => 'e2e/purchase-order-e2e.pdf',
                'uploaded_by' => $admin->id,
                'generated_by' => $admin->id,
                'notes' => 'Documento fixture E2E.',
            ]);

            OrderStatusEvent::create([
                'order_status_id' => $status->id,
                'event_type' => 'e2e.order_tracker_ready',
                'phase' => OrderStatusService::PHASE_TRANSIT,
                'user_id' => $admin->id,
                'description' => 'Tracker y Order Status preparados por fixture E2E.',
            ]);

            return [
                'orderId' => $order->id,
                'orderStatusId' => $status->id,
                'trackerId' => $tracker->id,
                'trackingCode' => $tracker->tracking_code,
                'itemIds' => $status->refresh()->load('items')->items->pluck('id')->values()->all(),
                'customerId' => $customer->id,
                'supplierId' => $supplier->id,
                'orderStatusUrl' => route('orders.status.show', $order, false),
            ];
        });
    }

    private function createOfferVersion(QuotationRequest $quotation, $items, User $user): ClientOfferVersion
    {
        $subtotal = (float) $items->sum('line_total');
        $taxAmount = round($subtotal * 0.15, 2);

        $offer = ClientOfferVersion::create([
            'quotation_request_id' => $quotation->id,
            'version_number' => 1,
            'reference' => $quotation->reference.'-OF-V1',
            'status' => 'draft',
            'currency' => 'NIO',
            'exchange_rate' => 36.50,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
            'payment_terms' => 'Contado',
            'valid_until' => now()->addDays(15)->toDateString(),
            'pdf_path' => null,
            'created_by' => $user->id,
            'snapshot_json' => ['source' => 'e2e_fixture'],
        ]);

        foreach ($items as $item) {
            ClientOfferVersionItem::create([
                'client_offer_version_id' => $offer->id,
                'quotation_request_item_id' => $item->id,
                'part_number' => $item->part_number,
                'brand' => $item->brand,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]);
        }

        return $offer;
    }

    private function makeProduct(string $partNumber, string $name, string $brand, string $model): Product
    {
        $values = [
            'name' => $name,
            'sku' => $partNumber,
            'type' => 'pieza',
            'brand' => $brand,
            'sales_price' => 100,
            'cost' => 60,
            'stock_on_hand' => 10,
            'active' => true,
        ];

        if (Schema::hasColumn('products', 'model')) {
            $values['model'] = $model;
        }

        return Product::updateOrCreate(
            ['part_number' => $partNumber],
            $values
        );
    }

    private function attachProductReferenceImages(OrderStatus $status): void
    {
        $sourceImage = storage_path('app/public/storefront/products/prod_535_1779083029.jpg');
        $fallbackImage = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAA+klEQVR4nO2ZQQ7DMAgE7f9P2pMKkWhRggE7h9TUSOPc2tibg8dxHAfwGcBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAbwGsBrAK8BvAZ4As2WAx/T8V+QAAAAAElFTkSuQmCC'
        );
        $imageBytes = is_file($sourceImage) ? file_get_contents($sourceImage) : $fallbackImage;

        foreach ($status->items as $index => $item) {
            if (! $item->product) {
                continue;
            }

            $path = 'product-reference/e2e-'.$item->product->id.'.jpg';
            Storage::disk('public')->put($path, $imageBytes);
            $item->product->forceFill(['image_path' => $path])->save();
        }
    }

    private function attachOrderStatusFixtureDocuments(OrderStatus $status, User $admin): void
    {
        foreach ([
            [OrderStatusService::PHASE_PURCHASE, 'purchase_order_provider_pdf', 'OC proveedor E2E.txt'],
            [OrderStatusService::PHASE_TRANSIT, 'shipping_document', 'Guia envio E2E.txt'],
            [OrderStatusService::PHASE_WAREHOUSE, 'warehouse_receipt', 'Recepcion bodega E2E.txt'],
            [OrderStatusService::PHASE_KARDEX_ENTRY, 'kardex_entry_document', 'Entrada Kardex E2E.txt'],
            [OrderStatusService::PHASE_KARDEX_OUTPUT, 'kardex_output_document', 'Salida Kardex E2E.txt'],
            [OrderStatusService::PHASE_LOGISTICS, 'delivery_logistics_document', 'Despacho cliente E2E.txt'],
        ] as [$phase, $type, $name]) {
            $path = "e2e/order-status/{$status->id}/{$type}.txt";
            Storage::disk('private')->put($path, "Documento {$type} para {$status->order_code}");

            OrderStatusDocument::updateOrCreate(
                ['order_status_id' => $status->id, 'document_type' => $type],
                [
                    'phase' => $phase,
                    'order_status_item_id' => null,
                    'file_name' => $name,
                    'file_path' => $path,
                    'uploaded_by' => $admin->id,
                    'generated_by' => $admin->id,
                    'notes' => 'Documento fixture E2E para flujo packing list.',
                ]
            );
        }
    }

    private function entity(string $type): Entity
    {
        $reference = $this->reference($type === 'cliente' ? 'CUST' : 'SUP');
        $email = strtolower($reference).'@e2e.test';

        return Entity::create([
            'name' => ($type === 'cliente' ? 'Cliente ' : 'Proveedor ').$reference,
            'type' => $type,
            'status' => 'activo',
            'email' => $email,
            'contact_name' => 'Contacto '.$reference,
            'phone_mobile' => '+505 0000 0000',
            'address' => 'Managua, Nicaragua',
            'country' => $type === 'cliente' ? 'Nicaragua' : 'Estados Unidos',
        ]);
    }

    private function user(string $email, string $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::headline($role).' E2E',
                'password' => Hash::make('Password123!'),
                'role' => $role,
                'is_active' => true,
            ]
        );
    }

    private function reference(string $prefix): string
    {
        return 'E2E-'.$prefix.'-'.now()->format('YmdHis').'-'.$this->tinyToken();
    }

    private function tinyToken(): string
    {
        return strtoupper(Str::random(5));
    }

    private function normalizeEntityStatus(string $status): string
    {
        return in_array($status, ['inactive', 'inactivo'], true) ? 'inactivo' : 'activo';
    }
}
