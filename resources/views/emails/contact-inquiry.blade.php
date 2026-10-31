<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Nueva consulta web</title></head>
<body>
    <h2>Nueva consulta web #{{ $inquiryId }}</h2>
    <p><strong>Nombre:</strong> {{ $inquiry['name'] }}</p>
    <p><strong>Correo:</strong> {{ $inquiry['email'] }}</p>
    <p><strong>Teléfono:</strong> {{ $inquiry['phone'] ?? 'No indicado' }}</p>
    <p><strong>Mensaje:</strong></p>
    <div style="white-space: pre-wrap">{{ $inquiry['message'] }}</div>
    <p>Puedes responder directamente a este correo para contactar al solicitante.</p>
</body>
</html>
