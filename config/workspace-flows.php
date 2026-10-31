<?php

return [
    'ventas' => [
        'sales.customer-request.create' => ['Registrar la solicitud', 'Captura la necesidad y las partidas del cliente.'],
        'sales.quotations.phase' => ['Trabajar la cotización', 'Continúa desde licitación hasta oferta y aprobación del cliente.'],
        'orders.status.index' => ['Seguir el pedido', 'Consulta compras al extranjero, tránsito y entrega.'],
        'sales.direct-orders.create' => ['Vender desde inventario', 'Inicia una venta directa cuando el producto ya está disponible.'],
    ],
    'finanzas' => [
        'finance.exchange-rates' => ['Revisar tipos de cambio', 'Comprueba las tasas antes de registrar operaciones en otra moneda.'],
        'finance.cxc' => ['Gestionar cobros', 'Revisa vencimientos y registra abonos de clientes.'],
        'finance.cxp' => ['Gestionar pagos', 'Revisa obligaciones y registra abonos a proveedores.'],
        'finance.hub' => ['Consultar el flujo financiero', 'Consulta ingresos, egresos y cartera.'],
    ],
    'bodega' => [
        'warehouse.incoming-orders' => ['Recibir pedidos', 'Verifica la mercancía y registra las cantidades recibidas.'],
        'inventory.moves' => ['Registrar movimientos', 'Registra entradas, salidas y ajustes con su evidencia.'],
        'inventory.products' => ['Consultar existencias', 'Comprueba productos y disponibilidad actual.'],
        'admin.kardex' => ['Revisar trazabilidad', 'Consulta el historial de movimientos del inventario.'],
    ],
    'clientes' => [
        'clients.index' => ['Consultar al cliente', 'Revisa sus datos comerciales y contactos.'],
        'admin.tickets' => ['Atender solicitudes', 'Da seguimiento a la atención y sus conversaciones.'],
    ],
    'proveedores' => [
        'admin.suppliers' => ['Consultar al proveedor', 'Comprueba sus datos y contactos comerciales.'],
        'purchases.create' => ['Emitir la orden de compra', 'Prepara la compra con el proveedor seleccionado.'],
    ],
    'operaciones' => [
        'admin.approvals' => ['Revisar aprobaciones', 'Resuelve las solicitudes pendientes de decisión.'],
        'admin.dispatch-authorizations' => ['Autorizar despachos', 'Revisa las solicitudes de salida.'],
        'sales.tracker' => ['Dar seguimiento', 'Consulta la trazabilidad de las órdenes.'],
    ],
    'equipo' => [
        'admin.users' => ['Gestionar el equipo', 'Revisa usuarios, roles y acceso operativo.'],
        'admin.chat' => ['Coordinar el trabajo', 'Continúa la comunicación con el equipo interno.'],
    ],
    'reportes' => [
        'dashboard' => ['Revisar el negocio', 'Consulta resultados y alertas del resumen general.'],
        'admin.kpis' => ['Analizar indicadores', 'Revisa el desempeño de la operación.'],
        'admin.activity' => ['Consultar actividad', 'Comprueba la trazabilidad de las acciones.'],
        'admin.exports' => ['Exportar información', 'Prepara y descarga los reportes necesarios.'],
    ],
    'configuracion' => [
        'web.blog' => ['Mantener el contenido', 'Actualiza las publicaciones de la tienda.'],
        'web.catalogos' => ['Gestionar catálogos', 'Revisa los documentos publicados.'],
        'profile' => ['Actualizar tu perfil', 'Mantén tus datos de cuenta al día.'],
    ],
];
