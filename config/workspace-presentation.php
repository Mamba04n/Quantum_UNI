<?php

return [
    'icons' => [
        'ventas' => 'chart', 'compras' => 'cart', 'bodega' => 'box',
        'finanzas' => 'wallet', 'clientes' => 'users', 'proveedores' => 'building',
        'operaciones' => 'check', 'equipo' => 'users', 'reportes' => 'chart',
        'configuracion' => 'settings',
    ],
    'process-icons' => [
        'sales.acquisition' => 'users',
        'sales.customer-request.create' => 'document', 'sales.direct-orders.create' => 'cart',
        'sales.quotations.phase' => 'document', 'sales.quotations.corrections' => 'edit',
        'orders.status.index' => 'box', 'sales.quotations.solicitud' => 'document',
        'sales.quotations.licitacion' => 'document', 'sales.quotations.oferta' => 'document',
        'sales.quotations.aprobacion' => 'check', 'purchases.create' => 'cart',
        'warehouse.incoming-orders' => 'box', 'inventory.products' => 'box',
        'inventory.moves' => 'box', 'admin.kardex' => 'document',
        'finance.invoices' => 'document', 'finance.hub' => 'wallet', 'finance.cxc' => 'wallet', 'finance.cxp' => 'wallet',
        'finance.payments' => 'wallet', 'finance.exchange-rates' => 'chart',
        'clients.index' => 'users', 'admin.tickets' => 'message', 'admin.suppliers' => 'building',
        'purchases.supplier-prices' => 'document',
        'admin.approvals' => 'check', 'admin.users' => 'users', 'admin.chat' => 'message',
        'dashboard' => 'chart', 'admin.kpis' => 'chart', 'admin.exports' => 'document',
    ],
    'tracking' => [
        'sales.quotations.phase', 'sales.quotations.corrections', 'orders.status.index',
        'warehouse.incoming-orders', 'sales.quotations.aprobacion', 'sales.tracker',
        'admin.approvals', 'admin.offer-reviews', 'admin.rfq-reviews',
        'admin.finance-reviews', 'admin.dispatch-authorizations', 'admin.activity',
        'admin.tickets', 'finance.hub', 'finance.cxc', 'finance.cxp',
    ],
    'consultation' => [
        'inventory.products', 'admin.kardex', 'clients.index', 'admin.suppliers',
        'purchases.supplier-prices',
        'dashboard', 'admin.kpis', 'finance.exchange-rates', 'finance.invoices',
    ],
    'primary' => [
        'ventas' => 'sales.customer-request.create', 'compras' => 'purchases.create',
        'bodega' => 'inventory.moves', 'finanzas' => 'finance.hub',
        'clientes' => 'clients.index', 'proveedores' => 'admin.suppliers',
        'operaciones' => 'admin.approvals', 'equipo' => 'admin.users',
        'reportes' => 'dashboard', 'configuracion' => 'web.blog',
    ],
    'quick' => [
        'sales.customer-request.create', 'sales.direct-orders.create',
        'purchases.create', 'finance.hub', 'inventory.moves',
        'admin.approvals', 'admin.chat',
    ],
];
