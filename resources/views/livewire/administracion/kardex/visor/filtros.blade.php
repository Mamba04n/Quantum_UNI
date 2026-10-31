<div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-5">
    <select wire:model.live="filterProductId" class="bg-gray-50 border-transparent rounded-xl py-2 px-3 text-sm">
        <option value="">Todos los productos</option>
        @foreach($products as $product)
            <option value="{{ $product->id }}">{{ $product->name }}</option>
        @endforeach
    </select>

    <input wire:model.live.debounce.300ms="filterSku" type="text" placeholder="SKU" class="bg-gray-50 border-transparent rounded-xl py-2 px-3 text-sm font-mono uppercase">

    <select wire:model.live="filterType" class="bg-gray-50 border-transparent rounded-xl py-2 px-3 text-sm">
        <option value="">Todos los tipos</option>
        <option value="in">Entrada</option>
        <option value="out">Salida</option>
    </select>

    <input wire:model.live="dateFrom" type="date" class="bg-gray-50 border-transparent rounded-xl py-2 px-3 text-sm">
    <input wire:model.live="dateTo" type="date" class="bg-gray-50 border-transparent rounded-xl py-2 px-3 text-sm">
</div>
