<x-app-layout>
    <div class="p-6 font-sans sm:p-8" data-testid="profile-page">
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Mi perfil</h1>
            <p class="mt-0.5 text-sm text-gray-500">Datos personales y seguridad de tu cuenta.</p>
        </div>
        <div class="grid overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)_minmax(0,1.4fr)]">
            <div class="border-b border-gray-100 px-5 py-4 lg:border-r">
                <livewire:profile.account-summary />
            </div>
            <div class="border-b border-gray-100 px-5 py-4 lg:border-r">
                <livewire:profile.update-profile-information-form />
            </div>
            <div class="border-b border-gray-100 px-5 py-4">
                <livewire:profile.update-password-form />
            </div>
        </div>
        <section class="mt-5" aria-labelledby="profile-activity-heading">
            <h2 id="profile-activity-heading" class="mb-3 text-base font-semibold text-gray-900">Actividad reciente de tu cuenta</h2>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" data-testid="profile-activity">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Fecha</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Módulo</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Actividad</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentActivity as $activity)
                                <tr class="transition-colors hover:bg-gray-50/50">
                                    <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $activity->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-gray-700">{{ match($activity->module) {
                                        'sales', 'ventas', 'commercial', 'cotizaciones' => 'Ventas',
                                        'finance', 'finanzas' => 'Finanzas',
                                        'inventory', 'warehouse', 'inventario', 'bodega' => 'Bodega',
                                        'purchases', 'compras', 'proveedores' => 'Proveedores',
                                        'crm', 'clientes' => 'Clientes',
                                        'users', 'usuarios', 'equipo', 'profile' => 'Cuenta y equipo',
                                        'demo' => 'Demostración',
                                        default => 'General',
                                    } }}</td>
                                    <td class="min-w-64 px-5 py-3 text-gray-900">{{ $activity->description }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-4 text-sm text-gray-500">Todavía no hay actividad registrada para tu cuenta.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <div class="mt-5 border-t border-gray-200 pt-4">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
