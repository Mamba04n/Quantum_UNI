<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nueva solicitud de cotización web</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; background-color: #f8fafc; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 28px;">
        <h2 style="margin-top: 0; color: #B50E0E; font-size: 20px;">Nueva solicitud de cotización web</h2>
        <p style="font-size: 14px; color: #475569;">Un cliente ha registrado una solicitud de cotización desde la tienda online:</p>
        
        <div style="background: #f1f5f9; border-radius: 12px; padding: 16px; margin: 20px 0; font-size: 14px; line-height: 1.6;">
            <p style="margin: 0;"><strong>Referencia:</strong> {{ $quotation->reference }}</p>
            <p style="margin: 4px 0 0 0;"><strong>Cliente:</strong> {{ $quotation->quote_to_name }}</p>
            <p style="margin: 4px 0 0 0;"><strong>Correo:</strong> {{ $quotation->quote_to_email }}</p>
            @if($quotation->customer_contact_name)
                <p style="margin: 4px 0 0 0;"><strong>Contacto:</strong> {{ $quotation->customer_contact_name }}</p>
            @endif
            @if($quotation->quote_to_ruc)
                <p style="margin: 4px 0 0 0;"><strong>RUC/Cédula:</strong> {{ $quotation->quote_to_ruc }}</p>
            @endif
            <p style="margin: 4px 0 0 0;"><strong>Artículos solicitados:</strong> {{ $itemsCount }}</p>
            <p style="margin: 4px 0 0 0;"><strong>Canal:</strong> Tienda Web</p>
            <p style="margin: 4px 0 0 0;"><strong>Estado inicial:</strong> Recibida (Licitación Proveedor)</p>
        </div>

        @if($quotation->notes)
            <div style="margin: 16px 0; padding: 12px; background: #fefce8; border-left: 4px solid #eab308; border-radius: 4px; font-size: 13px;">
                <strong>Notas del cliente:</strong>
                <p style="margin: 4px 0 0 0;">{{ $quotation->notes }}</p>
            </div>
        @endif

        <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
            Ingresa al ERP para gestionar esta solicitud en la fase de <strong>Licitación Proveedor</strong>.
        </p>
    </div>
</body>
</html>
