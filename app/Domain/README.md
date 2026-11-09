# Domain Modules

Esta carpeta contiene la implementacion modular del ERP. La regla es organizar por dominio de negocio y proceso, no por tipo generico de clase.

## Convencion

- `Actions`: casos de uso que cambian estado.
- `Queries`: lecturas, filtros, calculos o reportes sin mutar estado.
- `Services`: servicios de dominio que coordinan varias acciones o integraciones internas.
- `Support`: helpers internos del modulo.
- `Documents`: mappers o generadores documentales propios del modulo.

## Compatibilidad

Los archivos bajo `app/Services` se mantienen como fachadas delgadas para no romper imports historicos (`App\Services\...`). La logica nueva debe ir en `app/Domain/{Modulo}`.

Los modelos Eloquent permanecen en `app/Models` como contrato publico estable. El ownership modular de modelos esta documentado en `docs/model-ownership-map.md`.

## Modulos

- `Administration`: aprobaciones, compania, KPI administrativos.
- `Customers`: clientes y soporte/tickets.
- `Documents`: PDF y mappers documentales compartidos.
- `Finance`: CXP, CXC, pagos, documentos financieros y tipo de cambio.
- `Inventory`: productos, reservas, movimientos y maquinaria.
- `Orders`: order status, tracker, packing list y logistica de pedidos.
- `Purchases`: preparacion de compra, ordenes de compra y recepciones.
- `Sales`: flujo comercial, RFQ, Supplier Bid, Client Offer y conversion a orden.
