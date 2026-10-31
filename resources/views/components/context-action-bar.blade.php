<div {{ $attributes->class(['flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3']) }}>
    <div class="flex min-w-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @isset($workflow)<div class="w-full min-w-0 lg:ml-auto lg:w-[36rem]">{{ $workflow }}</div>@endisset
</div>
