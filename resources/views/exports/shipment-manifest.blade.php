<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manifiesto de Envío</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; margin: 20px; font-size: 14px; }
        .header { border-bottom: 2px solid #5a67d8; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #5a67d8; }
        h1 { margin-top: 5px; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-block: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f7fafc; }
        .footer { margin-top: 30px; font-size: 12px; text-align: center; color: #718096; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">ERP Logistics</div>
        <h1>Manifiesto de Envío: {{ $shipment->order_id }}</h1>
    </div>

    <div>
        <p><strong>Fecha de Envío:</strong> {{ $shipment->shipped_at ? $shipment->shipped_at->format('d/m/Y H:i') : 'N/A' }}</p>
        <p><strong>Registrado por:</strong> {{ $shipment->user ? $shipment->user->name : 'N/A' }}</p>
    </div>

    <table>
        <tr>
            <th>Cliente Destino</th>
            <td>{{ $shipment->client ? $shipment->client->name : 'Sin cliente asignado' }}</td>
        </tr>
        <tr>
            <th>Producto Descontado</th>
            <td>{{ $shipment->product ? $shipment->product->name : 'N/A' }}</td>
        </tr>
        <tr>
            <th>Cantidad Enviada</th>
            <td>{{ $shipment->quantity }} unidades</td>
        </tr>
        <tr>
            <th>Costo de Envío</th>
            <td>${{ number_format($shipment->shipping_cost, 2) }} {{ $shipment->currency }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Este documento es un comprobante interno del sistema.</p>
    </div>
</body>
</html>
