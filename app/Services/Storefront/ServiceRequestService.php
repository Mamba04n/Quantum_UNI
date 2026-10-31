<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ServiceRequestService
{
    public function create(array $data, ?UploadedFile $image = null): int
    {
        $path = $image?->store('storefront/uploads', 'private');

        return DB::table('storefront_service_requests')->insertGetId([
            'nombre' => $data['name'],
            'email' => $data['email'],
            'telefono' => $data['phone'] ?? null,
            'ubicacion' => $data['location'] ?? null,
            'maquinaria' => $data['machine'] ?? null,
            'descripcion' => $data['description'],
            'imagen' => $path,
            'fecha' => now(),
            'estado' => 'pendiente',
        ]);
    }
}
