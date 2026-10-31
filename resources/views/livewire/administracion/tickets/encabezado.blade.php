<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Tickets de Atencion</h2>
        <p class="text-sm text-gray-500 mt-0.5">Bandeja CRM para soporte, reclamos, consultas y solicitudes de compra.</p>
    </div>
    @if(session('message'))
        <div class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-xl text-sm font-semibold">
            {{ session('message') }}
        </div>
    @endif
</div>
