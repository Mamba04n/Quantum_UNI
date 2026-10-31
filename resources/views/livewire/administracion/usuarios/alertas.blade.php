@if(session('message'))
    <div class="mb-4 flex items-center gap-2 p-3 text-sm text-teal-800 bg-teal-50 border border-teal-200 rounded-xl">
        {{ session('message') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 flex items-center gap-2 p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-xl">
        {{ session('error') }}
    </div>
@endif
