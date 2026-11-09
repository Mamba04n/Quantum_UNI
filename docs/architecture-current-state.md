# Arquitectura actual

## Resumen

Quantum es un monolito Laravel 12 con Blade, Livewire, Tailwind y Vite. La aplicación ya contiene una capa de dominio significativa, pero conserva compatibilidad con una estructura anterior centrada en `App\Services` y componentes Livewire grandes.

| Capa | Archivos PHP | Situación observada |
|---|---:|---|
| `app/Domain` | 137 | Actions, Queries, Services, Events y documentos por módulo |
| `app/Livewire` | 92 | Estado de UI, pero también coordinación y lógica operativa |
| `app/Http` | 58 | Controladores, middleware, requests y API |
| `app/Services` | 51 | 43 adaptadores delgados y algunos servicios propios |
| `app/Application` | 5 | Controladores/requests parciales, sin uso uniforme |
| `app/Infrastructure` | 1 | Auditoría, todavía no una capa transversal completa |

## Módulos de dominio existentes

- Administración: aprobaciones, auditoría, empresa, KPI y usuarios.
- Clientes: directorio canónico, máquinas y soporte.
- Ventas: solicitudes, licitación, ofertas, órdenes y flujo comercial.
- Compras: preparación, órdenes y recepción.
- Inventario: productos, reservas, Kardex y máquinas.
- Pedidos/Logística: estado, tracking, documentos y packing.
- Finanzas: pagos, documentos, dinero decimal y tipos de cambio.
- Documentos: mappers y generación PDF.

## Deuda confirmada

### Servicios duplicados y adaptadores

`App\Services` contiene adaptadores que extienden implementaciones en `App\Domain`, por ejemplo `FinanceService`, `ExchangeRateService`, `CustomerService`, `OrderStatusService`, `PurchaseOrderService` y `TicketService`. Se mantienen porque numerosos constructores e imports todavía solicitan esos nombres. Eliminarlos ahora rompería resolución de contenedor y contratos existentes.

También existen tres nombres para algunos mappers: `App\Services\*`, `App\Services\Documents\*` y `App\Domain\Documentos\Mappers\*`.

### Livewire con demasiadas responsabilidades

Los focos principales por tamaño son:

- `GestionaAvanceSeguimiento.php`: 564 líneas.
- `GestorProveedores.php`: 523 líneas.
- `GestionaTableroSeguimiento.php`: 446 líneas.
- `ManagesCommercialWorkflow.php`: 435 líneas.
- `ClientProfile.php`: 415 líneas.
- `ShipmentManager.php`: 394 líneas.
- `ClientManager.php`: 344 líneas.

Ya existen Traits y Actions, pero todavía hay validación operativa, almacenamiento, transacciones y consultas dentro de componentes.

### Rutas y controladores

`routes/web.php` tiene 368 líneas. Contiene consultas del dashboard, closures de autorización/PDF y rutas de todos los módulos. La separación se aborda en la Fase 8 manteniendo nombres y URLs.

### Calidad de formato

Pint detecta deuda histórica en archivos no modificados. El CI comprueba archivos cambiados para evitar ampliar esa deuda. Un reformateo global separado produciría un diff muy grande y debe hacerse como trabajo mecánico independiente.

## Dependencias y flujo actual

```text
HTTP / Livewire
    ├── App\Services (adaptadores de compatibilidad)
    │       └── App\Domain\<Módulo>\Services
    ├── Actions / Queries de dominio
    └── Eloquent Models
             └── MySQL + storage privado
```

Los eventos existen en inventario, logística y pedidos, pero la mayor parte del flujo continúa siendo síncrona. No se detectó necesidad de microservicios y el despliegue sigue siendo un único artefacto.

El cambio excepcional de etapa de cotizaciones se ejecuta en
`App\Domain\Ventas\FlujoTrabajo\Services\QuotationStageChangeService`. Solicitar,
aprobar y rechazar bloquean los registros afectados y escriben solicitud,
cotización, tracker y actividad dentro de una sola transacción. El centro de
aprobaciones obtiene sus pendientes mediante
`ListPendingQuotationStageChanges`; ya no existe una implementación paralela en
`App\Services`.

## Riesgos

- Eliminar adaptadores sin migrar consumidores rompe inyección de dependencias.
- Mover clases masivamente dificulta revisar regresiones y mezcla cambios funcionales con namespaces.
- Componentes grandes aumentan la probabilidad de transacciones parciales si agregan lógica nueva directamente.
- La duplicidad de Requests web/API puede producir reglas distintas para el mismo caso de uso.
