<?php

return [
    'ventas' => ['title' => 'Ventas', 'description' => 'Solicitudes, cotizaciones, pedidos de clientes y compras al extranjero.', 'items' => [
        'sales.acquisition' => ['Captación de clientes', 'Prospectos, contactos y calificación comercial.'],
        'sales.customer-request.create' => ['Nueva solicitud', 'Registrar la necesidad del cliente.'],
        'sales.direct-orders.create' => ['Venta directa', 'Crear un pedido desde inventario.'],
        'sales.quotations.phase' => ['Cotizaciones', 'Gestión integral del flujo de cotización.'],
        'orders.status.index' => ['Pedidos al extranjero', 'Seguimiento de compras, tránsito y aduana.'],
    ]],
    'bodega' => ['title' => 'Bodega', 'description' => 'Existencias, movimientos y recepción.', 'items' => [
        'inventory.products' => ['Productos y existencias', 'Consultar disponibilidad y catálogo.'],
        'warehouse.incoming-orders' => ['Recepción de pedidos', 'Verificar mercancía y registrar recepción.'],
        'inventory.moves' => ['Movimientos de inventario', 'Entradas, salidas y ajustes de stock.'],
        'admin.kardex' => ['Visor de kardex', 'Revisar trazabilidad del inventario.'],
    ]],
    'finanzas' => ['title' => 'Finanzas', 'description' => 'Flujo financiero, balance, cartera pendiente y tasas de cambio.', 'items' => [
        'finance.hub' => ['Flujo financiero', 'Panel consolidado de ingresos, egresos y cartera (Treinta).'],
        'finance.cxc' => ['Cuentas por cobrar', 'Revisar vencimientos y abonos de clientes.'],
          'finance.payment-links' => ['Enlaces de pago', 'Cobros PayPal y enlaces públicos.'],
        'finance.cxp' => ['Cuentas por pagar', 'Revisar obligaciones y abonos a proveedores.'],
        'finance.exchange-rates' => ['Tipos de cambio', 'Consultar y registrar tasas vigentes.'],
    ]],
    'clientes' => ['title' => 'Clientes', 'description' => 'Relaciones comerciales y atención al cliente.', 'items' => [
        'clients.index' => ['Directorio de clientes', 'Consultar contactos, facturación y saldos.'],
        'admin.tickets' => ['Atención y tickets', 'Responder y dar seguimiento a solicitudes.'],
    ]],
    'proveedores' => ['title' => 'Proveedores', 'description' => 'Directorio, compras y relación con proveedores.', 'items' => [
        'admin.suppliers' => ['Directorio de proveedores', 'Consultar contactos y datos comerciales.'],
        'purchases.supplier-prices' => ['Banco de precios', 'Historial de cotizaciones de proveedores en USD.'],
        'purchases.create' => ['Orden de compra', 'Emitir orden de compra al proveedor.'],
    ]],
    'operaciones' => ['title' => 'Operaciones', 'description' => 'Aprobaciones y trazabilidad operativa.', 'items' => [
        'admin.approvals' => ['Centro de aprobaciones', 'Revisar solicitudes pendientes de decisión.'],
        'admin.dispatch-authorizations' => ['Autorización de despacho', 'Revisar solicitudes de salida.'],
        'sales.tracker' => ['Seguimiento maestro', 'Trazabilidad unificada de órdenes.'],
    ]],
    'equipo' => ['title' => 'Equipo interno', 'description' => 'Personas, acceso y comunicación interna.', 'items' => [
        'admin.users' => ['Usuarios', 'Gestionar empleados, roles y acceso.'],
        'admin.chat' => ['Chat interno', 'Coordinar el trabajo con el equipo.'],
    ]],
    'reportes' => ['title' => 'Reportes e indicadores', 'description' => 'Resultados, actividad y exportaciones.', 'items' => [
        'dashboard' => ['Dashboard general', 'Consultar métricas, alertas y actividad.'],
        'admin.kpis' => ['Indicadores KPI', 'Revisar el desempeño del negocio.'],
        'admin.exports' => ['Exportaciones', 'Preparar reportes y descargar información.'],
        'admin.activity' => ['Monitor de actividad', 'Consultar actividad y auditoría.'],
    ]],
    'configuracion' => ['title' => 'Configuración', 'description' => 'Contenido público y preferencias de cuenta.', 'items' => [
        'web.blog' => ['Blog', 'Administrar publicaciones de la tienda.'],
        'web.catalogos' => ['Catálogos', 'Administrar catálogos publicados.'],
        'profile' => ['Mi perfil', 'Actualizar los datos de la cuenta.'],
    ]],
];

