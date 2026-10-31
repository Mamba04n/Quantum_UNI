<?php

namespace App\Domain\Administracion\Auditoria\Queries;

use App\Infrastructure\Audit\AuditContext;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UnifiedActivityQuery
{
    public function base(): Builder
    {
        $quote = fn ($value) => DB::connection()->getPdo()->quote($value);
        $case = 'CASE';
        foreach (AuditContext::MODELS as $module => $names) {
            $classes = array_map(fn ($name) => $quote('App\\Models\\'.$name), $names);
            $case .= ' WHEN entity_type IN ('.implode(',', $classes).') THEN '.$quote($module);
        }
        foreach (['OrderStatus' => 'order_status', 'Tracker' => 'order_status', 'Shipment' => 'logistica', 'Package' => 'logistica', 'Ticket' => 'soporte', 'Quotation' => 'ventas', 'Offer' => 'ventas', 'SupplierBid' => 'ventas'] as $name => $module) {
            $case .= ' WHEN entity_type LIKE '.$quote('%'.$name.'%').' THEN '.$quote($module);
        }
        $case .= " ELSE 'sistema' END";

        $activity = DB::table('activity_logs')->select('id', 'user_id', 'module', 'action', 'description', 'metadata', 'ip_address', 'created_at')
            ->selectRaw("'actividad' AS source, NULL AS movement_type");
        $audit = DB::table('audit_logs')->select('id', 'user_id')
            ->selectRaw("COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.module')), 'null'), $case) AS module")
            ->addSelect('action')
            ->selectRaw("CONCAT(action, ' · ', COALESCE(entity_type, 'Sistema'), ' #', COALESCE(CAST(entity_id AS CHAR), ''), ' ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.changes.payment_code')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.changes.receivable_code')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.changes.payable_code')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.changes.reference')), JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.changes.name')), '')) AS description")
            ->addSelect('metadata')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.ip')) AS ip_address")
            ->addSelect('created_at')->selectRaw("'auditoria' AS source, NULL AS movement_type");

        $inventory = DB::table('stock_moves as moves')->leftJoin('products', 'products.id', '=', 'moves.product_id')
            ->select('moves.id', 'moves.user_id')->selectRaw("'inventario' AS module, 'inventory.movement' AS action")
            ->selectRaw("CONCAT(CASE WHEN moves.movement_type = 'in' THEN 'Entrada' WHEN moves.movement_type = 'out' THEN 'Salida' ELSE 'Movimiento' END, ' · ', COALESCE(products.name, 'Producto'), ' · ', COALESCE(products.sku, ''), ' · Cantidad: ', moves.quantity, ' · ', COALESCE(moves.reference, '')) AS description")
            ->selectRaw("JSON_OBJECT('product_id', moves.product_id, 'reference', moves.reference, 'quantity', moves.quantity, 'movement_type', moves.movement_type, 'movement_date', moves.movement_date) AS metadata, NULL AS ip_address")
            ->addSelect('moves.created_at')->selectRaw("'inventario' AS source")->addSelect('moves.movement_type');
        $tickets = DB::table('tickets')->select('id')->selectRaw("NULL AS user_id, 'soporte' AS module, 'ticket.opened' AS action")
            ->selectRaw("CONCAT('Ticket ', code, ' · ', subject) AS description")
            ->selectRaw("JSON_OBJECT('ticket_id', id, 'code', code, 'requester_id', customer_id) AS metadata, NULL AS ip_address")
            ->addSelect('created_at')->selectRaw("'soporte' AS source, NULL AS movement_type");
        $tracking = DB::table('order_tracker_activities')->select('id', 'user_id')->selectRaw("'order_status' AS module")
            ->addSelect('action', 'description')
            ->selectRaw("JSON_OBJECT('tracker_id', tracker_id, 'phase', phase, 'old_status', old_status, 'new_status', new_status) AS metadata, NULL AS ip_address")
            ->addSelect('created_at')->selectRaw("'seguimiento' AS source, NULL AS movement_type");

        return DB::query()->fromSub($activity->unionAll($audit)->unionAll($inventory)->unionAll($tickets)->unionAll($tracking), 'events');
    }

    public function paginate(?string $user, ?string $module, ?string $role, ?string $search, int $perPage, ?string $from = null, ?string $to = null, ?string $source = null, ?string $moveType = null)
    {
        $query = $this->base()
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($moveType, fn ($q) => $q->where('movement_type', $moveType))
            ->when($user, fn ($q) => $q->where('user_id', $user))
            ->when($module, fn ($q) => $q->where('module', $module))
            ->when($role, fn ($q) => $q->whereIn('user_id', User::whereHas('roles', fn ($r) => $r->where('slug', $role))->select('id')))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('description', 'like', '%'.$search.'%')->orWhere('action', 'like', '%'.$search.'%')))
            ->when($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from), fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to), fn ($q) => $q->whereDate('created_at', '<=', $to));
        $page = $query->orderByDesc('created_at')->orderByDesc('source')->orderByDesc('id')->paginate(max(10, min(100, $perPage)));
        $users = User::whereIn('id', $page->getCollection()->pluck('user_id')->filter())->get()->keyBy('id');
        $page->getCollection()->transform(function ($event) use ($users) {
            $event->user = $users->get($event->user_id);
            $event->created_at = Carbon::parse($event->created_at);
            $metadata = json_decode($event->metadata ?? '{}', true);
            $event->metadata = AuditContext::sanitize(is_array($metadata) ? $metadata : []);
            if ($event->source === 'auditoria') {
                $meta = $event->metadata;
                $labels = ['created' => 'Registro creado', 'updated' => 'Registro actualizado', 'deleted' => 'Registro eliminado', 'restored' => 'Registro restaurado', 'baseline.snapshot' => 'Estado inicial observado (no es un movimiento nuevo)', 'auth.login' => 'Inicio de sesión', 'auth.logout' => 'Cierre de sesión', 'auth.failed' => 'Acceso rechazado', 'http.operation' => 'Solicitud al sistema', 'http.error' => 'Solicitud rechazada / error'];
                $description = str_replace('App\\Models\\', '', $event->description);
                $event->description = ($labels[$event->action] ?? $event->action).' — '.explode(' · ', $description, 2)[1];
                if (isset($meta['path'])) {
                    $event->description .= ' · '.($meta['method'] ?? '').' /'.ltrim($meta['path'], '/');
                }
                if (isset($meta['status'])) {
                    $event->description .= ' · HTTP '.$meta['status'];
                }
            }

            return $event;
        });

        return $page;
    }
}
