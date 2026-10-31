<?php

namespace App\Exports;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\LogisticsShipment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\StockMove;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AdminModuleExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private string $module)
    {
        abort_unless(array_key_exists($module, self::modules()), 404);
    }

    public static function modules(): array
    {
        return [
            'users' => [
                'label' => 'Usuarios',
                'model' => User::class,
                'columns' => [
                    ['ID', 'id'],
                    ['Nombre', 'name'],
                    ['Email', 'email'],
                    ['Rol', 'role'],
                    ['Creado', 'created_at'],
                    ['Actualizado', 'updated_at'],
                ],
            ],
            'entities' => [
                'label' => 'Clientes / Proveedores',
                'model' => Entity::class,
                'columns' => [
                    ['ID', 'id'],
                    ['Nombre', 'name'],
                    ['Tipo', 'type'],
                    ['RUC', 'rfc_ruc'],
                    ['Email', 'email'],
                    ['Telefono', 'phone_mobile'],
                    ['Direccion', 'address'],
                    ['Creado', 'created_at'],
                ],
            ],
            'products' => [
                'label' => 'Productos',
                'model' => Product::class,
                'columns' => [
                    ['ID', 'id'],
                    ['SKU', 'sku'],
                    ['Nombre', 'name'],
                    ['Descripcion', 'description'],
                    ['Marca', 'brand'],
                    ['Tipo', 'type'],
                    ['Stock', 'stock_on_hand'],
                    ['Costo', 'cost'],
                    ['Precio Venta', 'sales_price'],
                    ['Activo', 'active'],
                    ['Creado', 'created_at'],
                ],
            ],
            'stock_moves' => [
                'label' => 'Kardex / Movimientos',
                'model' => StockMove::class,
                'with' => ['product', 'user'],
                'columns' => [
                    ['ID', 'id'],
                    ['Fecha', 'movement_date'],
                    ['Producto', 'product.name'],
                    ['SKU', 'product.sku'],
                    ['Tipo', 'movement_type'],
                    ['Cantidad', 'quantity'],
                    ['Saldo Resultante', 'balance_after'],
                    ['Costo Entrada', 'cost_in'],
                    ['Costo Salida', 'cost_out'],
                    ['Costo Unitario', 'unit_cost'],
                    ['Referencia', 'reference'],
                    ['Usuario', 'user.name'],
                    ['Adjunto', 'attachment_path'],
                ],
            ],
            'sale_orders' => [
                'label' => 'Ordenes de venta',
                'model' => SaleOrder::class,
                'columns' => [
                    ['ID', 'id'],
                    ['Referencia', 'reference'],
                    ['Fecha', 'date'],
                    ['Cliente', 'client_name'],
                    ['Email Cliente', 'client_email'],
                    ['Estado', 'status'],
                    ['Subtotal', 'subtotal'],
                    ['Impuesto', 'tax_amount'],
                    ['Total', 'total_amount'],
                    ['Creado', 'created_at'],
                ],
            ],
            'quotation_requests' => [
                'label' => 'Solicitudes / Cotizaciones',
                'model' => QuotationRequest::class,
                'with' => ['user', 'saleOrder'],
                'columns' => [
                    ['ID', 'id'],
                    ['Fecha', 'date'],
                    ['Cliente', 'client_name'],
                    ['Estado', 'status'],
                    ['Cantidad Solicitada', 'request_quantity'],
                    ['Fecha Entrega', 'delivery_date'],
                    ['Usuario', 'user.name'],
                    ['Orden Venta', 'saleOrder.reference'],
                    ['Creado', 'created_at'],
                ],
            ],
            'purchase_orders' => [
                'label' => 'Ordenes de compra',
                'model' => PurchaseOrder::class,
                'with' => ['supplier'],
                'columns' => [
                    ['ID', 'id'],
                    ['Proveedor', 'supplier.name'],
                    ['Referencia', 'reference_number'],
                    ['Estado', 'status'],
                    ['Fecha Orden', 'order_date'],
                    ['Fecha Esperada', 'expected_date'],
                    ['Total FOB', 'total_fob'],
                    ['Creado', 'created_at'],
                ],
            ],
            'shipments' => [
                'label' => 'Envios / Logistica',
                'model' => LogisticsShipment::class,
                'with' => ['client', 'product', 'user'],
                'columns' => [
                    ['ID', 'id'],
                    ['Orden', 'order_id'],
                    ['Cliente', 'client.name'],
                    ['Producto', 'product.name'],
                    ['Cantidad', 'quantity'],
                    ['Costo Envio', 'shipping_cost'],
                    ['Moneda', 'currency'],
                    ['Enviado', 'shipped_at'],
                    ['Usuario', 'user.name'],
                ],
            ],
            'account_receivables' => [
                'label' => 'Cuentas por cobrar',
                'model' => AccountReceivable::class,
                'with' => ['entity'],
                'columns' => [
                    ['ID', 'id'],
                    ['Cliente', 'entity.name'],
                    ['Factura', 'invoice_number'],
                    ['Concepto', 'concept'],
                    ['Clasificacion', 'classification'],
                    ['Condicion', 'condition'],
                    ['Fecha Factura', 'invoice_date'],
                    ['Importe USD', 'amount_usd'],
                    ['Importe C$', 'total_amount'],
                    ['Pagado C$', 'paid_amount'],
                    ['Saldo C$', fn ($row) => (float) $row->total_amount - (float) $row->paid_amount],
                    ['Vencimiento', 'due_date'],
                    ['Estado', 'status'],
                ],
            ],
            'account_payables' => [
                'label' => 'Cuentas por pagar',
                'model' => AccountPayable::class,
                'with' => ['entity'],
                'columns' => [
                    ['ID', 'id'],
                    ['Acreedor', 'entity.name'],
                    ['Tipo Acreedor', 'entity.type'],
                    ['Factura', 'invoice_number'],
                    ['Concepto', 'concept'],
                    ['Clasificacion', 'classification'],
                    ['Condicion', 'condition'],
                    ['Fecha Factura', 'invoice_date'],
                    ['Importe USD', 'amount_usd'],
                    ['Importe C$', 'total_amount'],
                    ['Pagado C$', 'paid_amount'],
                    ['Saldo C$', fn ($row) => (float) $row->total_amount - (float) $row->paid_amount],
                    ['Vencimiento', 'due_date'],
                    ['Estado', 'status'],
                ],
            ],
            'invoices' => [
                'label' => 'Facturas',
                'model' => Invoice::class,
                'with' => ['entity'],
                'columns' => [
                    ['ID', 'id'],
                    ['Numero Factura', 'invoice_number'],
                    ['Cliente / Entidad', 'entity.name'],
                    ['Fecha', 'date'],
                    ['Referencia Cotizacion', 'cotizacion_ref'],
                    ['Descripcion', 'description'],
                    ['Monto C$', 'amount_nio'],
                    ['Retencion', 'retention_amount'],
                    ['Neto C$', 'net_amount'],
                    ['Monto USD', 'amount_usd'],
                    ['Vencimiento', 'due_date'],
                    ['Estado', 'status'],
                    ['Clasificacion', 'classification'],
                    ['Condicion', 'condition'],
                ],
            ],
            'payments' => [
                'label' => 'Pagos / Abonos',
                'model' => Payment::class,
                'columns' => [
                    ['ID', 'id'],
                    ['Monto', 'amount'],
                    ['Fecha Pago', 'payment_date'],
                    ['Metodo', 'payment_method'],
                    ['Referencia', 'receipt_reference'],
                    ['Tipo Origen', 'payable_type'],
                    ['ID Origen', 'payable_id'],
                    ['Creado', 'created_at'],
                ],
            ],
            'tickets' => [
                'label' => 'Tickets',
                'model' => Ticket::class,
                'with' => ['customer', 'assignee', 'product'],
                'columns' => [
                    ['ID', 'id'],
                    ['Cliente', 'customer.name'],
                    ['Asignado a', 'assignee.name'],
                    ['Tipo', 'type'],
                    ['Prioridad', 'priority'],
                    ['Estado', 'status'],
                    ['Asunto', 'subject'],
                    ['Producto', 'product.name'],
                    ['Ultimo Mensaje', 'last_message_at'],
                    ['Creado', 'created_at'],
                ],
            ],
            'activity_logs' => [
                'label' => 'Actividad',
                'model' => ActivityLog::class,
                'with' => ['user'],
                'columns' => [
                    ['ID', 'id'],
                    ['Usuario', 'user.name'],
                    ['Modulo', 'module'],
                    ['Accion', 'action'],
                    ['Descripcion', 'description'],
                    ['IP', 'ip_address'],
                    ['Fecha', 'created_at'],
                ],
            ],
            'audit_logs' => [
                'label' => 'Auditoria',
                'model' => AuditLog::class,
                'with' => ['user'],
                'columns' => [
                    ['ID', 'id'],
                    ['Usuario', 'user.name'],
                    ['Accion', 'action'],
                    ['Entidad', 'entity_type'],
                    ['Registro', 'entity_id'],
                    ['Fecha', 'created_at'],
                ],
            ],
        ];
    }

    public static function options(): array
    {
        return collect(self::modules())
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'filename' => self::filenameFor($key),
                'count' => self::countFor($config),
            ])
            ->values()
            ->all();
    }

    public static function filenameFor(string $module): string
    {
        $label = self::modules()[$module]['label'] ?? $module;

        return Str::slug($label) . '-' . now()->format('Ymd-His') . '.xlsx';
    }

    public function collection()
    {
        return $this->query()->get();
    }

    public function headings(): array
    {
        return collect($this->config()['columns'])->pluck(0)->all();
    }

    public function map($row): array
    {
        return collect($this->config()['columns'])
            ->map(function (array $column) use ($row) {
                $accessor = $column[1];

                return $this->formatValue(
                    $accessor instanceof \Closure ? $accessor($row) : data_get($row, $accessor)
                );
            })
            ->all();
    }

    private function query(): Builder
    {
        $model = $this->config()['model'];

        return $model::query()
            ->when($this->config()['with'] ?? [], fn ($query, $relations) => $query->with($relations))
            ->orderByDesc('id');
    }

    private function config(): array
    {
        return self::modules()[$this->module];
    }

    private static function countFor(array $config): int
    {
        $model = $config['model'];

        return $model::query()->count();
    }

    private function formatValue($value)
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i');
        }

        if (is_bool($value)) {
            return $value ? 'Si' : 'No';
        }

        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : $value->value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }
}
