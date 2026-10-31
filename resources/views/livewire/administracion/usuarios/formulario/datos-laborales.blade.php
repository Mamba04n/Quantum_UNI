<section class="rounded-2xl border border-gray-100 p-5">
    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-4">Datos laborales</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <input wire:model="job_title" type="text" placeholder="Cargo" class="bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        <input wire:model="department" type="text" placeholder="Departamento" class="bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        <input wire:model="hire_date" type="date" class="bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        <input wire:model="identity_document" type="text" placeholder="Documento de identidad" class="bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
        <input wire:model="emergency_contact" type="text" placeholder="Contacto de emergencia" class="md:col-span-2 bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
    </div>
</section>
