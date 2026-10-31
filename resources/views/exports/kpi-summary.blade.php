<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        .muted { color: #64748b; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid th, .grid td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        .grid th { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>Panel KPI</h1>
    <div class="muted">Generado por {{ $user->name }} el {{ now()->format('Y-m-d H:i') }}</div>

    <h2>Resumen ejecutivo</h2>
    <table class="grid">
        <thead>
            <tr><th>KPI</th><th>Valor</th><th>Detalle</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Margen promedio</td>
                <td>{{ data_get($dashboard, 'salesMargin.available') ? data_get($dashboard, 'salesMargin.averagePercent') . '%' : 'No disponible' }}</td>
                <td>{{ data_get($dashboard, 'salesMargin.salesAnalyzed', 0) }} ventas analizadas</td>
            </tr>
            <tr>
                <td>Tiempo promedio entrega</td>
                <td>{{ data_get($dashboard, 'deliveryTime.available') ? data_get($dashboard, 'deliveryTime.averageDays') . ' dias' : 'No disponible' }}</td>
                <td>{{ data_get($dashboard, 'deliveryTime.message') }}</td>
            </tr>
            <tr>
                <td>Volumen ventas</td>
                <td>{{ number_format((float) data_get($dashboard, 'salesVolume.totalMoney', 0), 2) }}</td>
                <td>{{ data_get($dashboard, 'salesVolume.orders', 0) }} ordenes</td>
            </tr>
            <tr>
                <td>Cierre ventas</td>
                <td>{{ data_get($dashboard, 'closeRate.closeRate', 0) }}%</td>
                <td>{{ data_get($dashboard, 'closeRate.approved', 0) }} aprobadas de {{ data_get($dashboard, 'closeRate.sent', 0) }}</td>
            </tr>
            <tr>
                <td>Costo inventario</td>
                <td>{{ number_format((float) data_get($dashboard, 'inventoryCost.totalValue', 0), 2) }}</td>
                <td>{{ data_get($dashboard, 'inventoryCost.units', 0) }} unidades</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
