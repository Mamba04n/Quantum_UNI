# Mapa de Ownership de Modelos

Este documento ordena `app/Models` por modulo de negocio sin cambiar el namespace
publico `App\Models`. Es una capa de transicion segura mientras el ERP se modulariza
en `app/Domain`.

## Decision Arquitectonica

`app/Models` queda como capa de persistencia compartida. Los modelos no se mueven de
carpeta en esta fase porque el proyecto usa `App\Models\...` en policies, factories,
migrations, tests, Livewire, controladores y servicios de dominio.

La regla es:

- ownership funcional: definido por este mapa;
- logica de negocio: `app/Domain/{Modulo}/{Proceso}`;
- persistencia Eloquent: `app/Models`;
- compatibilidad legacy interna: `app/Services`.

## Administration / Security / Audit

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `User` | Administration / Security | Usuario, roles legacy, permisos y acceso a modulos. |
| `Role` | Administration / Security | Roles RBAC. |
| `Permission` | Administration / Security | Permisos RBAC. |
| `EmployeeProfile` | Administration / Security | Perfil interno de empleado. |
| `ActivityLog` | Administration / Audit | Bitacora funcional visible del ERP. |
| `AuditLog` | Administration / Audit | Auditoria generica por entidad. |
| `ApprovalRequest` | Administration / Approvals | Aprobaciones internas y formales. |
| `ClientApproval` | Administration / Approvals | Aprobacion formal del cliente/oferta. |
| `ProcessedEvent` | Administration / System | Idempotencia/eventos procesados. |

## Customers / CRM / Support

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `Entity` | Customers / Suppliers Shared | Entidad central para clientes y proveedores. |
| `Client` | Customers / Legacy | Cliente legacy usado por factories/flujos antiguos. |
| `ClientMachine` | Customers / Machines | Equipo/maquina asociada a un cliente. |
| `Ticket` | Customers / Support | Caso de soporte. |
| `TicketMessage` | Customers / Support | Mensajes del ticket. |
| `TicketNote` | Customers / Support | Notas internas del ticket. |

## Inventory / Machines

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `Product` | Inventory / Products | Producto principal ERP y base storefront nueva. |
| `StockMove` | Inventory / Kardex | Movimiento de inventario. |
| `InventoryReservation` | Inventory / Reservations | Reserva de inventario para cotizacion. |
| `Machine` | Inventory / Legacy Machines | Maquina legacy asociada a `Client`. |
| `MachinePart` | Inventory / Legacy Machines | Parte legacy de maquina. |
| `MaintenanceLog` | Inventory / Legacy Machines | Mantenimiento legacy. |
| `CustomerMachineComponent` | Inventory / Customer Machines | Componente de maquina de cliente. |
| `ComponentUsageLog` | Inventory / Customer Machines | Historial de uso/cambio de componente. |

## Sales

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `QuotationRequest` | Sales / Quotation Process | Solicitud/cotizacion principal. |
| `QuotationRequestItem` | Sales / Quotation Process | Partidas solicitadas. |
| `SupplierBid` | Sales / Supplier Bids | RFQ/enlace proveedor. |
| `SupplierBidItem` | Sales / Supplier Bids | Partidas enviadas a proveedor. |
| `SupplierBidResponse` | Sales / Supplier Bids | Respuesta de proveedor. |
| `SupplierBidResponseItem` | Sales / Supplier Bids | Precio/condiciones por partida. |
| `SupplierBidSelection` | Sales / Supplier Bids | Seleccion de proveedor por partida. |
| `SupplierRfqDocument` | Sales / Supplier RFQ | Documento RFQ generado/revisado. |
| `ClientOfferVersion` | Sales / Client Offers | Version de oferta al cliente. |
| `ClientOfferVersionItem` | Sales / Client Offers | Partida de oferta al cliente. |
| `ClientOfferReview` | Sales / Client Offers | Revision/aprobacion comercial. |
| `ClientOfferChangeRequest` | Sales / Client Offers | Cambios solicitados por cliente/admin. |
| `SaleOrder` | Sales / Sale Orders | Orden de venta generada. |
| `SaleOrderItem` | Sales / Sale Orders | Partidas de orden de venta. |

## Suppliers / Purchases

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `SupplierBrand` | Suppliers / Profile | Marcas trabajadas por proveedor. |
| `SupplierProductType` | Suppliers / Profile | Tipos de producto del proveedor. |
| `SupplierMachineType` | Suppliers / Profile | Tipos de maquinaria atendidos. |
| `SupplierPaymentMethod` | Suppliers / Profile | Metodos de pago aceptados. |
| `SupplierBankAccount` | Suppliers / Profile | Cuentas bancarias del proveedor. |
| `PurchasePreparationItem` | Purchases / Preparation | Partida revisada por admin antes de OC. |
| `PurchaseOrder` | Purchases / Purchase Orders | Orden de compra. |
| `PurchaseOrderItem` | Purchases / Purchase Orders | Partida de orden de compra. |
| `PurchaseOrderDocument` | Purchases / Documents | PDF/evidencia de orden de compra. |
| `PurchaseReceipt` | Purchases / Receipts | Recepcion de compra. |
| `PurchaseReceiptItem` | Purchases / Receipts | Partida recibida. |

## Orders / Logistics

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `OrderTracker` | Orders / Tracking | Tracking operativo de orden. |
| `OrderTrackerReference` | Orders / Tracking | Referencias externas del tracking. |
| `OrderTrackerActivity` | Orders / Tracking | Actividad del tracking. |
| `OrderTrackerAttachment` | Orders / Tracking | Adjuntos del tracking. |
| `OrderTrackerComment` | Orders / Tracking | Comentarios del tracking. |
| `OrderStatus` | Orders / Status | Seguimiento post OC por fases. |
| `OrderStatusItem` | Orders / Status | Partida seguida por fase. |
| `OrderStatusDocument` | Orders / Status | Documento por fase/partida. |
| `OrderStatusEvent` | Orders / Status | Evento historico de seguimiento. |
| `ShipmentBatch` | Orders / Logistics | Lote de envio. |
| `ShipmentBatchItem` | Orders / Logistics | Partida dentro del lote. |
| `LogisticsShipment` | Orders / Logistics Legacy | Envio legacy. |
| `ReplacementRequest` | Orders / Exceptions | Cambio de proveedor/reemplazo. |

## Finance

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `AccountPayable` | Finance / Payables | Cuenta por pagar. |
| `AccountReceivable` | Finance / Receivables | Cuenta por cobrar. |
| `Payment` | Finance / Payments | Pago polimorfico CXP/CXC. |
| `Invoice` | Finance / Invoices | Factura. |
| `JournalEntry` | Finance / Accounting | Asiento contable. |
| `JournalLine` | Finance / Accounting | Linea de asiento. |
| `ExchangeRate` | Finance / Exchange Rates | Tipo de cambio. |

## Storefront

| Modelo | Ownership | Nota |
| --- | --- | --- |
| `StorefrontProduct` | Storefront / Legacy Catalog | Lectura de tabla legacy `productos`. |
| `StorefrontProductImage` | Storefront / Legacy Catalog | Imagenes legacy `productos_imagenes`. |
| `Product` | Storefront / API V1 + Inventory | Producto ERP usado por API storefront nueva. |

## Traits Compartidos

| Archivo | Ownership | Nota |
| --- | --- | --- |
| `Traits/LogsActivity` | Administration / Audit | Trait Eloquent transversal para ActivityLog. |

## Proximos Pasos Seguros

1. No mover FQCN de modelos hasta tener una fase especifica con tests completos.
2. Extraer logica no-Eloquent desde modelos hacia `app/Domain`.
3. Si un modelo empieza a crecer, crear actions/queries del modulo antes de agregar mas metodos al modelo.
4. Evaluar `Relation::morphMap` antes de cualquier cambio de namespace que pueda afectar historicos.
5. Cuando un modulo este estable, considerar wrappers compatibles solo para modelos de bajo riesgo.
