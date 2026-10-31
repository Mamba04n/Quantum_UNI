@if(session('message'))
    <div class="mb-5 px-4 py-3 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
@endif
