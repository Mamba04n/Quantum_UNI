@php $user = auth()->user(); @endphp
@php
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

    $pendingCorrectionsCount = 0;
    if ($user && $user->canAny(['client_offer.view', 'order_approval.view'])) {
        $pendingCorrectionsCount = \App\Models\QuotationRequest::whereIn('status', [
            \App\Models\QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            \App\Models\QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
        ])->count();
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
        + $pendingFinanceChangeCount;
    $pendingApprovalCenterCount += $pendingStageChangeCount;


@endphp

<aside
    class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full px-4 py-4 overflow-y-auto transition-transform duration-300 bg-white border-r border-gray-200 lg:static lg:flex-shrink-0 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0 ease-out' : '-translate-x-full lg:translate-x-0 ease-in'"
>
    <div class="relative mb-6 flex justify-center items-center w-full">
        <a href="{{ route('dashboard') }}" class="block">
            <img src="{{ asset('storefront/images/logo.png') }}"
                 alt="Quantum Soluciones"
                 class="h-16 w-auto object-contain drop-shadow-md hover:scale-105 transition-transform duration-300">
        </a>
        <button type="button" @click="sidebarOpen = false" class="absolute right-0 top-0 lg:hidden text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    @php
        $roleColors = [
            'admin'           => 'bg-gray-100 text-gray-700',
            'agente_finanzas' => 'bg-gray-100 text-gray-700',
            'operaciones'     => 'bg-gray-100 text-gray-700',
            'ventas'          => 'bg-gray-100 text-gray-700',
            'bodega'          => 'bg-gray-100 text-gray-700',
        ];
        $userRole = $user->role ?? 'guest';
        $badge = $roleColors[$userRole] ?? 'bg-gray-100 text-gray-600';
        $roleLabels = \App\Models\User::$roles;
        $displayRoles = $user ? implode(' / ', $user->roleLabels()) : 'Invitado';
    @endphp
    <div class="mb-4 px-2 flex items-center gap-2">
        <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-[10px] font-black text-slate-600 uppercase shrink-0">
            {{ mb_strtoupper(mb_substr(trim($user->name ?? 'GU'), 0, 2, 'UTF-8'), 'UTF-8') }}
        </div>
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-700 truncate">{{ $user->name ?? 'Invitado' }}</p>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $badge }}">
                {{ $displayRoles ?: ($roleLabels[$userRole] ?? $userRole) }}
            </span>
        </div>
    </div>

    <nav class="mt-2 space-y-5">


        {{-- Dashboard (todos) --}}
        <div>
            <ul class="space-y-1">
                <li>
                    <a href="{{ url('/') }}" target="_blank"
                       class="text-gray-600 hover:bg-gray-50 flex items-center px-3 py-2 text-sm rounded-md transition-colors font-semibold border border-gray-100 mb-2">
                        <svg class="w-5 h-5 mr-3 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Ver Tienda Online
                    </a>
                </li>
                <li>
                    <a href="{{ route('dashboard') }}"
                       class="{{ request()->routeIs('dashboard') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="{{ route('explorer.index') }}"
                       class="{{ request()->routeIs('explorer.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="m20 20-4-4"/></svg>
                        Explorador
                    </a>
                </li>
            </ul>
        </div>

        {{-- ADMIN PANEL --}}
        @if($user && $user->canAccess('admin'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 4.5-2.9 7.9-7 9-4.1-1.1-7-4.5-7-9V7l7-4z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.5 12l1.7 1.7 3.8-4"/>
                </svg>
                Admin
            </h3>
            <ul class="mt-2 space-y-1">
                <li>
                    <a href="{{ route('admin.users') }}"
                       class="{{ request()->routeIs('admin.users') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        Usuarios
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.activity') }}"
                       class="{{ request()->routeIs('admin.activity') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Monitor de Actividad
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.kardex') }}"
                       class="{{ request()->routeIs('admin.kardex') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 12h8m-8 5h8M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                        Visor Kardex
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.approvals') }}"
                       class="{{ request()->routeIs('admin.approvals', 'admin.offer-reviews', 'admin.rfq-reviews', 'admin.finance-reviews', 'admin.dispatch-authorizations') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14l-4-2-3 2-3-2-4 2V6a2 2 0 012-2z"/></svg>
                        <span class="flex-1">Aprobaciones</span>
                        @if($pendingApprovalCenterCount > 0)
                            <span class="ml-2 min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-black flex items-center justify-center">{{ $pendingApprovalCenterCount > 9 ? '9+' : $pendingApprovalCenterCount }}</span>
                        @endif
                    </a>
                </li>
                @can('kpi.view')
                <li>
                    <a href="{{ route('admin.kpis') }}"
                       class="{{ request()->routeIs('admin.kpis') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3v18M7 8h8M5 13h12M3 18h16"/></svg>
                        KPIs
                    </a>
                </li>
                @endcan
                <li>
                    <a href="{{ route('admin.exports') }}"
                       class="{{ request()->routeIs('admin.exports') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                        Exportaciones
                    </a>
                </li>
            </ul>
        </div>
        @endif

        {{-- TICKETS --}}
        @if($user && $user->canAccess('tickets'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Atencion</h3>
            <ul class="mt-2 space-y-1">
                <li>
                    <a href="{{ route('admin.tickets') }}"
                       class="{{ request()->routeIs('admin.tickets') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        Tickets
                    </a>
                </li>
            </ul>
        </div>
        @endif

        {{-- PROVEEDORES --}}
        @if($user && $user->canAccess('suppliers'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Proveedores</h3>
            <ul class="mt-2 space-y-1">
                <li>
                    <a href="{{ route('admin.suppliers') }}"
                       class="{{ request()->routeIs('admin.suppliers') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7v12h14V7M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        Perfil Proveedores
                    </a>
                </li>
            </ul>
        </div>
        @endif

        {{-- VENTAS — Flujo Comercial --}}
        @if($user && ($user->canAccess('sales') || $user->hasAnyPermission(['order.view', 'direct_order.view', 'direct_order.create', 'tracker.view', 'order_status.view'])))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Ventas</h3>
            <ul class="mt-2 space-y-1">

                {{-- Nueva Solicitud --}}
                @can('customer_request.create')
                <li>
                    <a href="{{ route('sales.customer-request.create') }}"
                       class="{{ request()->routeIs('sales.customer-request.create') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nueva Solicitud
                    </a>
                </li>
                @endcan

                @canany(['direct_order.create', 'direct_order.create_from_inventory'])
                <li>
                    <a href="{{ route('sales.direct-orders.create') }}" class="{{ request()->routeIs('sales.direct-orders.create') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Venta directa
                    </a>
                </li>
                @endcanany

                {{-- Fase de Cotizacion --}}
                @if($user->can('customer_request.view') || $user->can('supplier_bid.view') || $user->can('client_offer.view') || $user->can('order_approval.view'))
                <li>
                    <a href="{{ route('sales.quotations.phase') }}"
                       class="{{ request()->routeIs('sales.quotations.phase') || request()->routeIs('sales.quotations.manage*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14l-4-2-3 2-3-2-4 2V6a2 2 0 012-2z"/></svg>
                        <span class="flex-1">Fase de Cotizacion</span>
                        @if($pendingApprovalCount > 0)
                            <span class="ml-2 min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-black flex items-center justify-center">{{ $pendingApprovalCount > 9 ? '9+' : $pendingApprovalCount }}</span>
                        @endif
                    </a>
                </li>
                @endif
                
                {{-- Correcciones de Ordenes --}}
                @canany(['client_offer.view', 'order_approval.view'])
                <li>
                    <a href="{{ route('sales.quotations.corrections') }}"
                       class="{{ request()->routeIs('sales.quotations.corrections') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span class="flex-1">Correcciones de Ordenes</span>
                        @if($pendingCorrectionsCount > 0)
                            <span class="ml-2 min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-black flex items-center justify-center">{{ $pendingCorrectionsCount > 9 ? '9+' : $pendingCorrectionsCount }}</span>
                        @endif
                    </a>
                </li>
                @endcanany

                {{-- Order Status --}}
                @can('order_status.view')
                <li>
                    <a href="{{ route('orders.status.index') }}"
                       class="{{ request()->routeIs('orders.status.*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h7"/></svg>
                        Order Status
                    </a>
                </li>
                @endcan


            </ul>
        </div>
        @endif

        {{-- CONTENIDO WEB --}}
        @if($user && $user->canAccess('admin'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase flex items-center gap-2">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9-9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3 4.03 3 9s-1.343 9-3 9m0-18c-1.657 0-3 4.03-3 9s1.343 9 3 9m-9-9a9 9 0 019-9"></path></svg>
                Contenido Web
            </h3>
            <ul class="mt-2 space-y-1">
                <li>
                    <a href="{{ route('web.blog') }}"
                       class="{{ request()->routeIs('web.blog') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM14 4v4h4"></path></svg>
                        Blog Técnico
                    </a>
                </li>
                <li>
                    <a href="{{ route('web.catalogos') }}"
                       class="{{ request()->routeIs('web.catalogos') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2H6a2 2 0 01-2-2z"></path></svg>
                        Catálogos
                    </a>
                </li>
            </ul>
        </div>
        @endif

        {{-- CRM --}}
        @if($user && $user->canAccess('crm'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">CRM</h3>
            <ul class="mt-2 space-y-1">
                @can('crm.clients.manage')
                <li>
                    <a href="{{ route('clients.index') }}"
                       class="{{ request()->routeIs('clients.index') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Leads / Clients
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endif

        {{-- BODEGA --}}
        @if($user && ($user->canAccess('inventory') || $user->canAccess('logistics')))
        <div class="mb-4">
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Bodega</h3>
            <ul class="mt-2 space-y-1">
                @if($user->canAccess('inventory'))
                <li>
                    <a href="{{ route('inventory.products') }}"
                       class="{{ request()->routeIs('inventory.products*') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                        Products / Stock
                    </a>
                </li>
                <li>
                    <a href="{{ route('inventory.moves') }}"
                       class="{{ request()->routeIs('inventory.moves') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h13M9 7h13M5 7h.01M5 17h.01M3 12h18"></path></svg>
                        Kardex
                    </a>
                </li>
                <li>
                    <a href="{{ route('warehouse.incoming-orders') }}"
                       class="{{ request()->routeIs('warehouse.incoming-orders') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2h-3.5L12 3 9.5 5H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4m4 3h8"/></svg>
                        Pedidos esperados
                    </a>
                </li>
                @endif
            </ul>
        </div>
        @endif

        {{-- COMPRAS --}}
        @if($user && !$user->hasRole(\App\Models\User::ROLE_BODEGA) && $user->canAccess('purchases'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Compras</h3>
            <ul class="mt-2 space-y-1"><li><a href="{{ route('purchases.create') }}" class="{{ request()->routeIs('purchases.create') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors"><svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17"/></svg>Orden de compra</a></li></ul>
        </div>
        @endif

        {{-- LOGISTICA --}}
        @if($user && !$user->hasRole(\App\Models\User::ROLE_BODEGA) && $user->can('tracker.view'))
        <div>
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Logística</h3>
            <ul class="mt-2 space-y-1"><li><a href="{{ route('sales.tracker') }}" class="{{ request()->routeIs('sales.tracker') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors"><svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>Seguimiento de pedidos</a></li></ul>
        </div>
        @endif

        {{-- FINANZAS --}}
        @if($user && $user->canAccess('finance'))
        <div class="mb-8">
            <h3 class="px-2 text-xs font-extrabold tracking-wider text-gray-400 uppercase">Finance</h3>
            <ul class="mt-2 space-y-1">
                <li>
                    <a href="{{ route('finance.cxc') }}"
                       class="{{ request()->routeIs('finance.cxc') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        (CXC) Cobrar
                    </a>
                </li>
                <li>
                    <a href="{{ route('finance.cxp') }}"
                       class="{{ request()->routeIs('finance.cxp') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        (CXP) Pagar
                    </a>
                </li>
                <li>
                    <a href="{{ route('finance.invoices') }}"
                       class="{{ request()->routeIs('finance.invoices') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Facturas
                    </a>
                </li>
                <li>
                    <a href="{{ route('finance.exchange-rates') }}"
                       class="{{ request()->routeIs('finance.exchange-rates') ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }} flex items-center px-3 py-2 text-sm rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h14m0-8l4 4m0 0l-4 4m4-4H7"/></svg>
                        Tasas de cambio
                    </a>
                </li>
            </ul>
        </div>
        @endif

    </nav>
</aside>
