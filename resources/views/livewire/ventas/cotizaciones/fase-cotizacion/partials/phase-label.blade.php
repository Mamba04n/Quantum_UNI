{{ match ($currentStage) {
    'customer_request' => 'Solicitud',
    'supplier_bid' => 'Licitaci?n proveedor',
    'supplier_response' => 'Preliquidación',
    'supplier_selection' => 'Selecci?n de proveedor',
    'client_offer' => 'Oferta cliente',
    'order_changes' => 'Modificaci?n de orden',
    'customer_approval' => 'Aprobaci?n cliente',
    'purchase_preparation' => 'Preparaci?n de compra',
    'purchase_order_generated' => 'Orden de compra',
    default => 'Cotizaci?n',
} }}