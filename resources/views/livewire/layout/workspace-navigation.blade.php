@php
    $hasProgress = request()->routeIs('sales.customer-request.create', 'sales.quotations.phase.customer-request', 'sales.quotations.phase.customer-request.alias');
@endphp

@if($hasProgress)
<div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-gray-100 bg-white px-6 py-2 sm:px-8 sticky top-0 z-40 shrink-0">
    <div class="ml-auto w-full min-w-0 lg:w-[36rem]">
        <x-quotation-progress :current="1" :compact="true" />
    </div>
</div>
@endif
