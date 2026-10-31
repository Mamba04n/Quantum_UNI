<?php

return [
    'stages' => ['prospectos' => 'Prospectos', 'contactados' => 'Contactados', 'interesados' => 'Interesados', 'calificados' => 'Calificados'],
    'sources' => ['Recomendación', 'Prospección directa B2B', 'Networking', 'Redes sociales', 'Página web', 'Investigación', 'LinkedIn', 'Licitación del Estado', 'Plataforma de compras', 'Aliado comercial', 'Visita técnica', 'Otro'],
    'channels' => ['WhatsApp', 'Llamada', 'Correo', 'Visita', 'LinkedIn', 'Reunión', 'Redes sociales', 'Otro'],
    'contact_roles' => ['Contacto principal', 'Decisor', 'Compras', 'Mantenimiento', 'Administración', 'Gerencia', 'Influenciador', 'Otro'],
    'buying_functions' => ['buyer' => 'Compra', 'decision' => 'Decide', 'influence' => 'Influye', 'user' => 'Usa el producto'],
    'role_functions' => ['Decisor' => 'decision', 'Compras' => 'buyer', 'Mantenimiento' => 'user', 'Influenciador' => 'influence'],
    'context_fields' => ['problem' => 'Problema principal', 'current_supplier' => 'Proveedor actual', 'purchase_frequency' => 'Frecuencia de compra', 'commercial_notes' => 'Observaciones comerciales', 'detected_opportunity' => 'Oportunidad detectada'],
    'criteria' => ['purchase_capacity' => 'Capacidad de compra', 'recurrence' => 'Recurrencia potencial', 'negotiation' => 'Facilidad de negociación', 'decision_access' => 'Acceso al decisor', 'equipment_importance' => 'Cantidad/importancia de maquinaria', 'current_need' => 'Necesidad actual'],
    'levels' => ['Alta', 'Media', 'Baja'],
    'classes' => ['A' => 'Alto potencial', 'B' => 'Potencial medio', 'C' => 'Potencial bajo'],
    'discard_reasons' => ['Sin potencial', 'No pertenece a nuestro mercado', 'Datos inválidos', 'Empresa cerrada', 'No desea contacto', 'Duplicado', 'Otro'],
];
