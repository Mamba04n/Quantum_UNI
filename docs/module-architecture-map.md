# Mapa Modular ERP CRM

Este mapa define donde debe vivir el codigo nuevo y como se esta migrando el monolito Laravel hacia modulos internos sin romper compatibilidad.

## Regla General

- UI Livewire: `app/Livewire/{Modulo}` y `resources/views/livewire/{modulo}`.
- Logica de negocio nueva: `app/Domain/{Modulo}/{Proceso}`.
- Fachadas legacy compatibles: `app/Services`.
- Modelos Eloquent compartidos: `app/Models` por ahora.
- API publica/ERP: `app/Http/Controllers/Api/V1`.

No mover modelos Eloquent masivamente todavia. Muchos factories, relaciones, policies, casts, migrations, Livewire y tests importan `App\Models\...`. El modelo se considera compartido; la regla modular se aplica primero a procesos, acciones, queries y servicios.

El ownership modular de modelos se documenta en `docs/model-ownership-map.md`.

## Modulos Actuales

| Modulo | Carpeta principal | Responsabilidad |
| --- | --- | --- |
| Administration | `app/Domain/Administration` | Aprobaciones internas, configuracion de compania, KPIs administrativos. |
| Administracion/Auditoria | `app/Livewire/Admin/Auditoria`, `resources/views/livewire/admin/auditoria` y `app/Domain/Administracion/Auditoria` | Monitor administrativo de actividad, movimientos, tickets recientes y metricas del dia. |
| Administracion/Indicadores | `app/Livewire/Admin/Indicadores`, `resources/views/livewire/admin/indicadores` y `app/Domain/Administration/Kpis` | Panel KPI administrativo, filtros, exportaciones y lectura del servicio KPI existente. |
| Reportes/Exportaciones | `app/Livewire/Admin/Reportes`, `resources/views/livewire/admin/reportes/exportaciones` y `app/Exports` | Centro administrativo de exportaciones Excel por modulo operativo. |
| Customers | `app/Domain/Customers` | Clientes, soporte y tickets. |
| Clientes | `app/Domain/Clientes` | Tickets y soporte con nombres nuevos en espanol para cortes progresivos. |
| Documents | `app/Domain/Documents` | PDF, mappers documentales compartidos y render de documentos. |
| Finance | `app/Domain/Finance` | Cuentas por pagar/cobrar, evidencia financiera, pagos y tipo de cambio. |
| Inventory | `app/Domain/Inventory` | Productos, SKU, reservas, movimientos, maquinaria y componentes. |
| Inventario/Kardex | `app/Livewire/Admin/Kardex`, `resources/views/livewire/admin/kardex` y `app/Domain/Inventario/Kardex` | Visor administrativo de movimientos Kardex, productos activos y saldo calculado. |
| Orders | `app/Domain/Orders`, `app/Livewire/Orders` y `resources/views/livewire/ordenes/seguimiento` | Seguimiento de orden, tracker, packing list, kanban operativo, detalle de partidas y logistica operativa. |
| Purchases | `app/Domain/Purchases` | Preparacion de compra, ordenes de compra, documentos y recepcion. |
| Compras/Documentos | `app/Livewire/Admin/Compras/Documentos`, `resources/views/livewire/admin/compras/documentos` y `app/Domain/Purchases/PurchaseOrders` | Formulario administrativo de documento OC proveedor, datos de consignatario, envio y comentarios. |
| Proveedores | `app/Domain/Proveedores` | Perfil de proveedores, consultas de listado, detalle y opciones de filtros. |
| Sales | `app/Domain/Sales` | Solicitudes, RFQ, Supplier Bid, Client Offer, workflow comercial y conversion a orden. |
| Ventas/Cotizaciones | `app/Livewire/Sales/Cotizaciones` y `resources/views/livewire/sales/cotizaciones` | Fase comercial de cotizacion, solicitud cliente, licitacion proveedor, correcciones de orden y compatibilidad con rutas comerciales historicas. |
| Ventas/SupplierBids | `app/Domain/Sales/SupplierBids` y `app/Livewire/Sales/Concerns/QuotationProcess` | Respuestas proveedor, seleccion de partidas, adjuntos de proforma, tracker comercial y envio hacia Oferta Cliente. |
| Ventas/OfertasCliente | `app/Livewire/Admin/Ventas/Ofertas`, `resources/views/livewire/admin/ventas/ofertas` y `app/Domain/Ventas/OfertasCliente` | Revision administrativa de ofertas cliente, listado pendiente, detalle financiero y resolucion. |
| Ventas/RFQ | `app/Livewire/Admin/Ventas/Rfq`, `resources/views/livewire/admin/ventas/rfq` y `app/Domain/Sales/SupplierRfqs` | Revision administrativa de RFQ, generacion controlada de PDF y acciones de documentos RFQ proveedor. |
| Suppliers | `app/Livewire/Admin/Proveedores` y `resources/views/livewire/admin/proveedores` | Perfil de proveedores, marcas, productos, maquinaria, pagos, bancos e historial. |
| Tickets | `app/Livewire/Admin/Tickets` y `resources/views/livewire/admin/tickets` | Bandeja administrativa de soporte, reclamos, solicitudes y conversacion con cliente. |
| Usuarios | `app/Livewire/Admin/Usuarios`, `resources/views/livewire/admin/usuarios` y `app/Domain/Administracion/Usuarios` | Gestion administrativa de usuarios internos, roles disponibles y exclusion de clientes de tienda. |

## Patron de Migracion

1. Identificar un servicio grande en `app/Services`.
2. Mover implementacion real a `app/Domain/{Modulo}/{Proceso}`.
3. Dejar `app/Services/{Clase}.php` como fachada compatible.
4. Mantener imports existentes hasta que el modulo este cubierto por tests.
5. Migrar consumidores gradualmente desde `App\Services` hacia `App\Domain`.

## Estado Aplicado

Ya se movieron implementaciones de servicios a carpetas de dominio:

- Administration: `ApprovalService`, `KpiService`, `CompanyProfileService`.
- Customers: `CustomerService`, `TicketService`.
- Documents: mappers documentales y servicios PDF.
- Finance: `FinanceService`, `FinanceDocumentService`, `ExchangeRateService`.
- Inventory: `InventoryService`, reservas, SKU y ciclo de componentes.
- Orders: `OrderStatusService`, sync, tracker, documentos tracker y packing list.
- Purchases: preparacion, ordenes, documentos y recepcion.
- Sales: workflow, ordenes, Client Offer, Supplier Bid y Supplier RFQ.
- Ventas/FlujoTrabajo: `QuotationStageChangeService` concentra solicitud,
  aprobación y rechazo del retroceso comercial; `ListPendingQuotationStageChanges`
  alimenta el centro administrativo sin consultas Eloquent embebidas en Livewire.
- Modelos: se audito `app/Models` y se dejo mapa de ownership sin cambiar namespaces.
- Suppliers/Profile: se reorganizo el front/back Livewire de proveedores hacia carpetas en espanol, dejando fachada compatible para referencias historicas.
- Proveedores/Perfil: se extrajeron consultas de listado, detalle y opciones de filtro hacia `app/Domain/Proveedores/Perfil/Queries`.
- Tickets: se reorganizo el front/back Livewire hacia `app/Livewire/Admin/Tickets` y `resources/views/livewire/admin/tickets`, con queries en `app/Domain/Clientes/Tickets/Queries`.
- Usuarios: se reorganizo el front/back Livewire hacia `app/Livewire/Admin/Usuarios` y `resources/views/livewire/admin/usuarios`, con queries en `app/Domain/Administracion/Usuarios/Queries`.
- Auditoria: se reorganizo el front/back Livewire hacia `app/Livewire/Admin/Auditoria` y `resources/views/livewire/admin/auditoria`, con queries en `app/Domain/Administracion/Auditoria/Queries`.
- Indicadores/KPIs: se reorganizo el front/back Livewire hacia `app/Livewire/Admin/Indicadores` y `resources/views/livewire/admin/indicadores`, reutilizando `app/Domain/Administration/Kpis/Services/KpiService`.
- Kardex: se reorganizo el front/back Livewire hacia `app/Livewire/Admin/Kardex` y `resources/views/livewire/admin/kardex`, con queries en `app/Domain/Inventario/Kardex/Queries`.
- Compras/Documentos OC: se reorganizo el formulario de documento OC hacia `app/Livewire/Admin/Compras/Documentos` y `resources/views/livewire/admin/compras/documentos`, conservando `PurchaseOrderDocumentService`.
- Ventas/OfertasCliente: se reorganizo revision admin de ofertas hacia `app/Livewire/Admin/Ventas/Ofertas` y `resources/views/livewire/admin/ventas/ofertas`, con queries en `app/Domain/Ventas/OfertasCliente/Queries`.
- Reportes/Exportaciones: se reorganizo el centro de exportaciones hacia `app/Livewire/Admin/Reportes` y `resources/views/livewire/admin/reportes/exportaciones`, conservando `AdminModuleExport`.
- Ventas/Cotizaciones: se reorganizo `sales/quotations/fase-cotizacion`, solicitud cliente, licitacion proveedor y correcciones de orden hacia `app/Livewire/Sales/Cotizaciones` y `resources/views/livewire/sales/cotizaciones`, conservando bridges legacy.
- Ventas/SupplierBids: se redujo duplicacion en `RecordSupplierBidResponse` y `RecordSupplierBidResponseItem` mediante soportes `SupplierBidResponseData` y `SupplierBidResponseTracker`.
- Ventas/RFQ: se restauro revision admin de RFQ como modulo bajo `app/Livewire/Admin/Ventas/Rfq`, con bridge `App\Livewire\Admin\RfqReviewManager` y accion `DeleteSupplierRfqDocument`.
- Ordenes/Seguimiento: se modularizo el Blade de `OrderStatusShow` hacia `resources/views/livewire/ordenes/seguimiento`, conservando `resources/views/livewire/orders/order-status-show.blade.php` como wrapper compatible.
- Ordenes/Seguimiento PHP: se redujo `app/Livewire/Orders/OrderStatusShow.php` a componente compatible y se separo la logica Livewire en concerns bajo `app/Livewire/Orders/Seguimiento/Concerns`.
- Ordenes/OrderStatus: se avanzo la extraccion de `OrderStatusService` hacia Actions (`EnsureOrderStatusForSaleOrder`, `SyncPurchaseOrderToOrderStatus`, `MarkProviderPurchaseOrderSent`, `MarkOrderStatusItemStatus`, `CancelSupplierItem`, `RequestSupplierReplacement`, `ConfirmSupplierItem`, `RegisterCustomsEntry`, `AuthorizePartialDispatch`, `HoldItemForOtherItems`, `CreateShipmentBatch`, `RegisterWarehouseReceipt`, `CreateKardexEntry`, `ReceiveItemAndCreateKardexEntry`, `CreateKardexOutput`, `RegisterDeliveryDispatch`, `RegisterDelivery`), soporte compartido (`OrderStatusActivity`, `OrderStatusDocumentRequirement`, `OrderStatusInventoryProduct`, `OrderStatusOperationalDocumentStorage`, `OrderStatusStateCatalog`, `OrderStatusGeneralStatusResolver`) y servicio dedicado `OrderStatusRecalculator`.

## Siguientes Cortes

1. Extraer `GenerateSupplierRfqPdf` y `MarkSupplierRfqSent` desde `ManagesSupplierRfqDocuments` hacia `app/Domain/Sales/SupplierRfqs/Actions`.
2. Extraer acciones de `ManagesSupplierBidResponseFlow` hacia `app/Domain/Sales/SupplierBids/Actions`.
3. Continuar con `Sales/SupplierBids`: dividir `ManagesSupplierBidResponseFlow` en concerns locales por proveedor, formulario, guardado, seleccion y envio a oferta cliente.
4. Separar `ApprovalManager` en aprobacion, edicion de proveedor/precio y generacion de OC.
5. Dividir `ManagesClientOfferFlow` hacia `app/Domain/Sales/ClientOffers/Actions`.
6. Crear contratos internos si empiezan a repetirse patrones entre Sales, Orders y Purchases.
