@php
    $user = auth()->user();
    $pendingApprovalCount = 0;
    $pendingOfferReviewCount = 0;
    $pendingRfqReviewCount = 0;
    $pendingDispatchAuthorizationCount = 0;
    $pendingStageChangeCount = 0;

    if ($user && ($user->can('internal_approval.view') || $user->can('order_approval.view') || $user->can('approval.view'))) {
        $pendingApprovalCount = \App\Models\ApprovalRequest::where('status', \App\Models\ApprovalRequest::STATUS_PENDING)
            ->where(function ($query) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('approval_requests', 'approval_type')) {
                    $query->where('approval_type', \App\Models\ApprovalRequest::TYPE_INTERNAL_PURCHASE)
                        ->orWhere('request_type', \App\Models\ApprovalRequest::TYPE_INTERNAL_PURCHASE);
                    return;
                }
                $query->where('request_type', \App\Models\ApprovalRequest::TYPE_INTERNAL_PURCHASE);
            })
            ->count();
    }

    if ($user && $user->can('client_offer_review.view') && \Illuminate\Support\Facades\Schema::hasTable('client_offer_reviews')) {
        $pendingOfferReviewCount = \App\Models\ClientOfferReview::where('status', \App\Models\ClientOfferReview::STATUS_PENDING)->count();
    }

    if ($user && $user->can('dispatch_authorization.view')) {
        $pendingDispatchAuthorizationCount = \App\Models\OrderStatusItem::where(
            'status',
            \App\Services\OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED
        )->count();
    }

    $pendingFinanceChangeCount = 0;
    if ($user && ($user->can('client_offer_review.view') || $user->can('finance.view') || $user->can('full_access')) && \Illuminate\Support\Facades\Schema::hasTable('finance_change_requests')) {
        $pendingFinanceChangeCount = \App\Models\FinanceChangeRequest::where('status', \App\Models\FinanceChangeRequest::STATUS_PENDING)->count();
    }

    if ($user && $user->can('rfq_review.view') && \Illuminate\Support\Facades\Schema::hasTable('supplier_rfq_documents')) {
        $pendingRfqReviewCount = \App\Models\SupplierRfqDocument::where('status', \App\Models\SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW)->count();
    }

    if ($user && ($user->can('internal_approval.view') || $user->can('approval.view') || $user->can('order_approval.view')) && \Illuminate\Support\Facades\Schema::hasTable('quotation_stage_change_requests')) {
        $pendingStageChangeCount = \App\Models\QuotationStageChangeRequest::where('status', \App\Models\QuotationStageChangeRequest::STATUS_PENDING)->count();
    }

    $pendingApprovalCenterCount = $pendingApprovalCount
        + $pendingOfferReviewCount
        + $pendingRfqReviewCount
        + $pendingDispatchAuthorizationCount
        + $pendingFinanceChangeCount
        + $pendingStageChangeCount;

    $userInitials = mb_strtoupper(mb_substr(trim($user->name ?? 'U'), 0, 2, 'UTF-8'), 'UTF-8');
@endphp

<aside
    id="erp-sidebar"
    x-ref="sidebar"
    x-data="{
        tooltip: { show: false, text: '', top: 0, left: 0 },
        showTooltip(el, text) {
            if (!this.collapsed) return;
            const rect = el.getBoundingClientRect();
            this.tooltip = {
                show: true,
                text: text,
                top: rect.top + (rect.height / 2),
                left: rect.right + 12
            };
        },
        hideTooltip() {
            this.tooltip.show = false;
        }
    }"
    class="fixed inset-y-0 left-0 z-50 flex flex-col bg-white border-r border-[#e8e8e9] transition-all duration-300 ease-in-out lg:static lg:h-screen lg:sticky lg:top-0 shrink-0 select-none shadow-sm lg:shadow-none"
    :class="{
        'translate-x-0': menuOpen,
        '-translate-x-full lg:translate-x-0': !menuOpen,
        'w-64': !collapsed,
        'w-64 lg:w-[72px]': collapsed
    }"
    aria-label="Navegación principal Quantum ERP"
>
    {{-- Header de Marca / Logo (alineado a la altura de la topbar h-16) --}}
    <div class="h-16 px-4 flex items-center border-b border-[#e8e8e9] shrink-0 justify-between">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 overflow-hidden group">
            <img src="{{ asset('storefront/images/logo.webp') }}" alt="Quantum" class="h-8 w-auto object-contain shrink-0 transition-transform group-hover:scale-105">
            <div x-show="!collapsed" class="flex flex-col min-w-0 transition-opacity">
                <span class="font-extrabold text-[#1e1e1e] text-[15px] tracking-tight leading-none whitespace-nowrap">
                    Quantum <span class="text-[#b50e0e]">SOLUCIONES</span>
                </span>
                <span class="text-[10px] text-gray-400 font-semibold tracking-wider uppercase mt-0.5">Módulo ERP</span>
            </div>
        </a>

        {{-- Botón para cerrar en móvil --}}
        <button
            type="button"
            @click="closeMenu()"
            class="lg:hidden p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"
            aria-label="Cerrar navegación móvil"
        >
            <x-portal.icon name="close" class="w-5 h-5" />
        </button>
    </div>

    {{-- Navegación con Scroll --}}
    <nav @scroll.passive="hideTooltip()" class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-4 text-sm" style="scrollbar-width: thin;">

        {{-- SECCIÓN: INICIO --}}
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Inicio
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @php $active = request()->routeIs('workspace*') || request()->routeIs('dashboard'); @endphp
                <a href="{{ route('workspace') }}"
                   @mouseenter="showTooltip($el, 'Inicio')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                   @if($active) aria-current="page" @endif
                >
                    <x-portal.icon name="grid" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                    <span x-show="!collapsed" class="truncate">Inicio</span>
                </a>
            </div>
        </div>

        {{-- SECCIÓN: VENTAS --}}
        @if($user && ($user->canAccess('sales') || $user->hasAnyPermission(['order.view', 'direct_order.view', 'direct_order.create', 'tracker.view', 'order_status.view'])))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Ventas
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @if($user->can('crm.acquisition.view') || $user->can('lead.view') || $user->can('full_access'))
                    @php $active = request()->routeIs('sales.acquisition*'); @endphp
                    <a href="{{ route('sales.acquisition') }}"
                       @mouseenter="showTooltip($el, 'Captación comercial')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="user" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Captación</span>
                    </a>
                @endif

                @can('customer_request.create')
                    @php $active = request()->routeIs('sales.customer-request.*'); @endphp
                    <a href="{{ route('sales.customer-request.create') }}"
                       @mouseenter="showTooltip($el, 'Nueva solicitud')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="plus" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Nueva solicitud</span>
                    </a>
                @endcan

                @canany(['direct_order.create', 'direct_order.create_from_inventory'])
                    @php $active = request()->routeIs('sales.direct-orders.create'); @endphp
                    <a href="{{ route('sales.direct-orders.create') }}"
                       @mouseenter="showTooltip($el, 'Venta directa')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="cart" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Venta directa</span>
                    </a>
                @endcanany

                @can('quote.view')
                    @php $active = request()->routeIs('sales.quotations.*'); @endphp
                    <a href="{{ route('sales.quotations.phase') }}"
                       @mouseenter="showTooltip($el, 'Cotizaciones')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="quote" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Cotizaciones</span>
                    </a>
                @endcan

                @can('order_status.view')
                    @php $active = request()->routeIs('orders.status.*'); @endphp
                    <a href="{{ route('orders.status.index') }}"
                       @mouseenter="showTooltip($el, 'Pedidos al extranjero')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="truck" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Pedidos al extranjero</span>
                    </a>
                @endcan
            </div>
        </div>
        @endif

        {{-- SECCIÓN: BODEGA --}}
        @if($user && ($user->canAccess('warehouse') || $user->hasAnyPermission(['product.view', 'warehouse.receive_orders', 'inventory.move', 'kardex.view'])))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Bodega
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @can('product.view')
                    @php $active = request()->routeIs('inventory.products'); @endphp
                    <a href="{{ route('inventory.products') }}"
                       @mouseenter="showTooltip($el, 'Productos y stock')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="box" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Productos y stock</span>
                    </a>
                @endcan

                @can('warehouse.receive_orders')
                    @php $active = request()->routeIs('warehouse.incoming-orders'); @endphp
                    <a href="{{ route('warehouse.incoming-orders') }}"
                       @mouseenter="showTooltip($el, 'Recepción de pedidos')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="truck" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Recepción pedidos</span>
                    </a>
                @endcan

                @can('inventory.move')
                    @php $active = request()->routeIs('inventory.moves'); @endphp
                    <a href="{{ route('inventory.moves') }}"
                       @mouseenter="showTooltip($el, 'Movimientos de inventario')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="machine" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Movimientos</span>
                    </a>
                @endcan

                @can('kardex.view')
                    @php $active = request()->routeIs('admin.kardex'); @endphp
                    <a href="{{ route('admin.kardex') }}"
                       @mouseenter="showTooltip($el, 'Visor de Kardex')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="document" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Visor Kardex</span>
                    </a>
                @endcan
            </div>
        </div>
        @endif

        {{-- SECCIÓN: FINANZAS --}}
        @if($user && ($user->canAccess('finance') || $user->hasAnyPermission(['finance.view', 'cxc.view', 'cxp.view', 'exchange_rates.view'])))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Finanzas
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @php $active = request()->routeIs('finance.hub*'); @endphp
                <a href="{{ route('finance.hub') }}"
                   @mouseenter="showTooltip($el, 'Flujo financiero Treinta')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="wallet" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                    <span x-show="!collapsed" class="truncate">Flujo financiero</span>
                </a>

                @can('cxc.view')
                    @php $active = request()->routeIs('finance.cxc*'); @endphp
                    <a href="{{ route('finance.cxc') }}"
                       @mouseenter="showTooltip($el, 'Cuentas por cobrar')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="chart" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Cuentas por cobrar</span>
                    </a>
                @endcan

                @can('cxp.view')
                    @php $active = request()->routeIs('finance.cxp*'); @endphp
                    <a href="{{ route('finance.cxp') }}"
                       @mouseenter="showTooltip($el, 'Cuentas por pagar')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="wallet" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Cuentas por pagar</span>
                    </a>
                @endcan

                @can('exchange_rates.view')
                    @php $active = request()->routeIs('finance.exchange-rates*'); @endphp
                    <a href="{{ route('finance.exchange-rates') }}"
                       @mouseenter="showTooltip($el, 'Tipos de cambio')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="chart" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Tipos de cambio</span>
                    </a>
                @endcan
            </div>
        </div>
        @endif

        {{-- SECCIÓN: CLIENTES Y SOPORTE --}}
        @if($user && ($user->canAccess('clients') || $user->canAccess('tickets')))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Clientes
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @if($user->canAccess('clients'))
                    @php $active = request()->routeIs('clients.*'); @endphp
                    <a href="{{ route('clients.index') }}"
                       @mouseenter="showTooltip($el, 'Directorio de clientes')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="user" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Clientes</span>
                    </a>
                @endif

                @if($user->canAccess('tickets'))
                    @php $active = request()->routeIs('admin.tickets*'); @endphp
                    <a href="{{ route('admin.tickets') }}"
                       @mouseenter="showTooltip($el, 'Tickets de soporte')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="support" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Tickets de soporte</span>
                    </a>
                @endif
            </div>
        </div>
        @endif

        {{-- SECCIÓN: PROVEEDORES --}}
        @if($user && ($user->canAccess('suppliers') || $user->can('purchase_order.create')))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Proveedores
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @if($user->canAccess('suppliers'))
                    @php $active = request()->routeIs('admin.suppliers*'); @endphp
                    <a href="{{ route('admin.suppliers') }}"
                       @mouseenter="showTooltip($el, 'Directorio de proveedores')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="building" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Proveedores</span>
                    </a>

                    @php $active = request()->routeIs('purchases.supplier-prices*'); @endphp
                    <a href="{{ route('purchases.supplier-prices') }}"
                       @mouseenter="showTooltip($el, 'Banco de precios')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="document" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Banco de precios</span>
                    </a>
                @endif

                @can('purchase_order.create')
                    @php $active = request()->routeIs('purchases.create'); @endphp
                    <a href="{{ route('purchases.create') }}"
                       @mouseenter="showTooltip($el, 'Orden de compra')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="cart" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Orden de compra</span>
                    </a>
                @endcan
            </div>
        </div>
        @endif

        {{-- SECCIÓN: OPERACIONES Y APROBACIONES --}}
        @if($user && ($user->canAccess('operations') || $pendingApprovalCenterCount > 0 || $user->can('tracker.view')))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Operaciones
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @php $active = request()->routeIs('admin.approvals*', 'admin.offer-reviews*', 'admin.rfq-reviews*', 'admin.finance-reviews*'); @endphp
                <a href="{{ route('admin.approvals') }}"
                   @mouseenter="showTooltip($el, 'Centro de aprobaciones ({{ $pendingApprovalCenterCount }})')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="check" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                    <span x-show="!collapsed" class="truncate flex-1">Aprobaciones</span>
                    @if($pendingApprovalCenterCount > 0)
                        <span x-show="!collapsed" class="ml-auto inline-flex items-center justify-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700">
                            {{ $pendingApprovalCenterCount > 99 ? '99+' : $pendingApprovalCenterCount }}
                        </span>
                        <span x-show="collapsed" class="absolute top-1 right-1 h-2 w-2 rounded-full bg-[#b50e0e] ring-2 ring-white"></span>
                    @endif
                </a>

                @can('dispatch_authorization.view')
                    @php $active = request()->routeIs('admin.dispatch-authorizations*'); @endphp
                    <a href="{{ route('admin.dispatch-authorizations') }}"
                       @mouseenter="showTooltip($el, 'Autorizaciones de despacho')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="check" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Despachos</span>
                    </a>
                @endcan

                @can('tracker.view')
                    @php $active = request()->routeIs('sales.tracker'); @endphp
                    <a href="{{ route('sales.tracker') }}"
                       @mouseenter="showTooltip($el, 'Seguimiento maestro')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="pin" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Seguimiento</span>
                    </a>
                @endcan
            </div>
        </div>
        @endif

        {{-- SECCIÓN: ADMINISTRACIÓN Y EQUIPO --}}
        @if($user && ($user->canAccess('admin') || $user->can('user.view') || $user->can('full_access')))
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Administración
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                @can('user.view')
                    @php $active = request()->routeIs('admin.users*'); @endphp
                    <a href="{{ route('admin.users') }}"
                       @mouseenter="showTooltip($el, 'Usuarios y accesos')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="user" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">Usuarios</span>
                    </a>
                @endcan

                @php $active = request()->routeIs('admin.activity*'); @endphp
                <a href="{{ route('admin.activity') }}"
                   @mouseenter="showTooltip($el, 'Monitor de actividad')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="activity" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                    <span x-show="!collapsed" class="truncate">Monitor actividad</span>
                </a>

                @can('kpi.view')
                    @php $active = request()->routeIs('admin.kpis*'); @endphp
                    <a href="{{ route('admin.kpis') }}"
                       @mouseenter="showTooltip($el, 'Indicadores KPI')"
                       @mouseleave="hideTooltip()"
                       class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                       :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                    >
                        <x-portal.icon name="chart" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                        <span x-show="!collapsed" class="truncate">KPIs</span>
                    </a>
                @endcan

                @php $active = request()->routeIs('admin.exports*'); @endphp
                <a href="{{ route('admin.exports') }}"
                   @mouseenter="showTooltip($el, 'Exportaciones')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="download" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                    <span x-show="!collapsed" class="truncate">Exportaciones</span>
                </a>

                @php $active = request()->routeIs('admin.chat*'); @endphp
                <a href="{{ route('admin.chat') }}"
                   @mouseenter="showTooltip($el, 'Chat interno')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 {{ $active ? 'bg-[#fef2f2] text-[#b50e0e] font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium' }}"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="chat" class="w-5 h-5 shrink-0 {{ $active ? 'text-[#b50e0e]' : 'text-gray-500 group-hover:text-gray-800' }}" />
                    <span x-show="!collapsed" class="truncate">Chat interno</span>
                </a>
            </div>
        </div>
        @endif

        {{-- SECCIÓN: PORTAL & TIENDA --}}
        <div>
            <div x-show="!collapsed" class="px-2.5 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Enlaces rápidos
            </div>
            <div x-show="collapsed" class="my-1.5 border-t border-gray-100"></div>

            <div class="space-y-0.5">
                <a href="{{ route('customer.portal') }}"
                   target="_blank"
                   @mouseenter="showTooltip($el, 'Portal de clientes')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="user" class="w-5 h-5 shrink-0 text-gray-500 group-hover:text-gray-800" />
                    <span x-show="!collapsed" class="truncate">Portal clientes</span>
                </a>

                <a href="{{ url('/') }}"
                   target="_blank"
                   @mouseenter="showTooltip($el, 'Ver tienda online')"
                   @mouseleave="hideTooltip()"
                   class="erp-nav-link group relative flex items-center rounded-xl transition-all duration-150 text-gray-600 hover:bg-gray-50 hover:text-gray-900 font-medium"
                   :class="collapsed ? 'justify-center h-10 w-10 mx-auto' : 'px-3 py-2 gap-3'"
                >
                    <x-portal.icon name="arrow" class="w-5 h-5 shrink-0 text-gray-500 group-hover:text-gray-800" />
                    <span x-show="!collapsed" class="truncate">Tienda online</span>
                </a>
            </div>
        </div>
    </nav>

    {{-- FOOTER / COLLAPSE BUTTON TIPO CMMS --}}
    <div class="border-t border-[#e8e8e9] p-2.5 shrink-0 bg-white">
        <div class="flex items-center" :class="collapsed ? 'justify-center' : 'justify-between'">
            <div x-show="!collapsed" class="flex items-center gap-2 min-w-0 px-1">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-xs font-bold text-[#b50e0e]">
                    {{ $userInitials }}
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-800 truncate leading-tight">{{ $user->name ?? 'Usuario' }}</p>
                    <p class="text-[10px] text-gray-400 truncate">{{ $user->email ?? '' }}</p>
                </div>
            </div>

            {{-- Botón para colapsar/expandir el sidebar exactamente como en CMMS --}}
            <button
                type="button"
                @click="toggleSidebar()"
                class="hidden lg:flex h-8 w-8 items-center justify-center rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                :title="collapsed ? 'Expandir barra lateral' : 'Contraer barra lateral'"
                :aria-label="collapsed ? 'Expandir barra lateral' : 'Contraer barra lateral'"
            >
                <svg
                    class="w-4 h-4 transition-transform duration-200"
                    :class="{'rotate-180': collapsed}"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Tooltip flotante global con posición fija (teletransportado a body para evitar cualquier recorte por overflow) --}}
    <template x-teleport="body">
        <div
            x-show="collapsed && tooltip.show"
            x-cloak
            :style="`top: ${tooltip.top}px; left: ${tooltip.left}px;`"
            class="fixed z-[99999] pointer-events-none -translate-y-1/2 flex items-center rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white shadow-xl transition-opacity duration-150 whitespace-nowrap"
        >
            <span x-text="tooltip.text"></span>
            <div class="absolute -left-1 top-1/2 -translate-y-1/2 border-y-4 border-y-transparent border-r-4 border-r-gray-900"></div>
        </div>
    </template>
</aside>
