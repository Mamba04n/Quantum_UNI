@props(['label', 'model', 'type' => 'text'])
<label class="block min-w-0 text-sm font-medium text-gray-700">
    <span>{{ $label }}</span>
    <input type="{{ $type }}" @if(!count($attributes->whereStartsWith('wire:model')->getAttributes())) wire:model="{{ $model }}" @endif {{ $attributes->class(['mt-1 block w-full rounded-xl border-gray-200 text-sm focus:border-slate-400 focus:ring-slate-200']) }}>
</label>
