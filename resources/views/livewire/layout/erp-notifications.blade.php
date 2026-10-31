<div class="flex items-center gap-1.5 sm:gap-2" wire:poll.visible.15s>
        @php
            $currentUser = auth()->user();
            $canSeeApprovals = $currentUser && ($currentUser->can('internal_approval.view') || $currentUser->can('order_approval.view') || $currentUser->can('approval.view'));
            $approvalAlerts = collect();
            $approvalCount = 0;

            if ($canSeeApprovals) {
                $approvalQuery = \App\Models\ApprovalRequest::with(['tracker.references', 'requester'])
                    ->where('status', \App\Models\ApprovalRequest::STATUS_PENDING)
                    ->where(function ($query) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('approval_requests', 'approval_type')) {
                            $query->where('approval_type', \App\Models\ApprovalRequest::TYPE_INTERNAL_PURCHASE)
                                ->orWhere('request_type', \App\Models\ApprovalRequest::TYPE_INTERNAL_PURCHASE);

                            return;
                        }

                        $query->where('request_type', \App\Models\ApprovalRequest::TYPE_INTERNAL_PURCHASE);
                    });

                $approvalCount = (clone $approvalQuery)->count();
                $approvalAlerts = $approvalQuery->latest()->limit(3)->get();
            }

            $canSeeCorrections = $currentUser && $currentUser->canAny(['client_offer.view', 'order_approval.view']);
            $correctionsCount = 0;
            if ($canSeeCorrections) {
                $correctionsCount = \App\Models\QuotationRequest::whereIn('status', [
                    \App\Models\QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
                    \App\Models\QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
                ])->count();
            }

            $canSeeOfferReviews = $currentUser && $currentUser->can('client_offer_review.view');
            $offerReviewCount = 0;
            if ($canSeeOfferReviews && \Illuminate\Support\Facades\Schema::hasTable('client_offer_reviews')) {
                $offerReviewCount = \App\Models\ClientOfferReview::where('status', \App\Models\ClientOfferReview::STATUS_PENDING)->count();
            }

            $canSeeQuotations = $currentUser && app(\App\Support\WorkspaceNavigation::class)->allows($currentUser, 'sales.quotations.licitacion');
            $webQuotesQuery = \App\Models\QuotationRequest::where('source_channel', 'web')
                ->whereIn('status', [\App\Models\QuotationRequest::STATUS_RECEIVED, \App\Models\QuotationRequest::STATUS_IN_REVIEW])
                ->whereNull('sale_order_id');
            $webQuotesCount = $canSeeQuotations ? (clone $webQuotesQuery)->count() : 0;
            $webQuotesAlerts = $canSeeQuotations ? (clone $webQuotesQuery)->latest()->limit(4)->get() : collect();

            $canSeeOrders = $currentUser?->canAny(['order_status.view', 'tracker.view']);
            $newOrdersQuery = \App\Models\SaleOrder::where('created_at', '>=', now()->subDay());
            $newOrdersCount = $canSeeOrders ? (clone $newOrdersQuery)->count() : 0;
            $newOrdersAlerts = $canSeeOrders ? (clone $newOrdersQuery)->latest()->limit(4)->get() : collect();
            $totalNotifications = $approvalCount + $correctionsCount + $offerReviewCount + $webQuotesCount + $newOrdersCount;
            $canSeeNotifications = $canSeeApprovals || $canSeeCorrections || $canSeeOfferReviews || $canSeeQuotations || $canSeeOrders;

            // Pending queues formerly summarized in the sidebar remain visible here.
            $additionalQueues = [];
            $queueDefinitions = [
                ['rfq_review.view', 'supplier_rfq_documents', \App\Models\SupplierRfqDocument::class, \App\Models\SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW, 'admin.rfq-reviews', 'Revisión de RFQ'],
                ['dispatch_authorization.view', 'order_status_items', \App\Models\OrderStatusItem::class, \App\Services\OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED, 'admin.dispatch-authorizations', 'Autorizaciones de despacho'],
                [['client_offer_review.view', 'finance.view', 'full_access'], 'finance_change_requests', \App\Models\FinanceChangeRequest::class, \App\Models\FinanceChangeRequest::STATUS_PENDING, 'admin.finance-reviews', 'Cambios financieros'],
                [['internal_approval.view', 'approval.view', 'order_approval.view'], 'quotation_stage_change_requests', \App\Models\QuotationStageChangeRequest::class, \App\Models\QuotationStageChangeRequest::STATUS_PENDING, 'admin.approvals', 'Cambios de etapa'],
            ];
            foreach ($queueDefinitions as [$permissions, $table, $model, $status, $destination, $label]) {
                if ($currentUser?->canAny((array) $permissions) && \Illuminate\Support\Facades\Schema::hasTable($table)) {
                    $count = $model::where('status', $status)->count();
                    $canSeeNotifications = true;
                    $totalNotifications += $count;
                    if ($count > 0) {
                        $additionalQueues[] = compact('destination', 'label', 'count');
                    }
                }
            }

            $ticketUser = $currentUser;
            $canSeeTickets = $ticketUser && method_exists($ticketUser, 'canAccess') && $ticketUser->canAccess('tickets');
            $ticketAlerts = collect();
            $ticketCount = 0;

            if ($canSeeTickets) {
                $ticketAlerts = \App\Models\Ticket::with('customer')
                    ->whereIn('status', [\App\Models\Ticket::STATUS_OPEN, \App\Models\Ticket::STATUS_IN_PROGRESS])
                    ->where(function ($query) {
                        $query->whereNull('last_admin_message_at')
                            ->orWhereColumn('last_customer_message_at', '>', 'last_admin_message_at');
                    })
                    ->latest('last_customer_message_at')
                    ->limit(5)
                    ->get();

                $ticketCount = \App\Models\Ticket::whereIn('status', [\App\Models\Ticket::STATUS_OPEN, \App\Models\Ticket::STATUS_IN_PROGRESS])
                    ->where(function ($query) {
                        $query->whereNull('last_admin_message_at')
                            ->orWhereColumn('last_customer_message_at', '>', 'last_admin_message_at');
                    })
                    ->count();
            }
        @endphp

        @if($canSeeNotifications)
            <div class="relative">
                <x-dropdown align="right" width="80">
                    <x-slot name="trigger">
                    <button type="button" aria-label="Notificaciones y tareas pendientes" class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200/80 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"/>
                            </svg>
                            @if($totalNotifications > 0)
                                <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-black flex items-center justify-center">{{ $totalNotifications > 9 ? '9+' : $totalNotifications }}</span>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-bold text-gray-900">Notificaciones Globales</p>
                            <p class="text-xs text-gray-500">{{ $totalNotifications }} tareas pendientes</p>
                        </div>

                        <div class="max-h-80 overflow-y-auto">
                            @if($totalNotifications === 0)
                                <div class="px-4 py-8 text-center text-sm text-gray-400">Sin tareas pendientes.</div>
                            @else
                                @if($webQuotesCount > 0)
                                    <a href="{{ route('sales.quotations.licitacion') }}" class="block px-4 py-3 hover:bg-emerald-50 border-b border-gray-50 group transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                                <p class="text-sm font-bold text-gray-800 group-hover:text-emerald-700">Cotizaciones Web Recibidas</p>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">{{ $webQuotesCount }}</span>
                                        </div>
                                    </a>
                                    @foreach($webQuotesAlerts as $webQuote)
                                        <a href="{{ route('sales.quotations.licitacion', ['quoteId' => $webQuote->id]) }}" class="block px-4 py-2 hover:bg-gray-50 border-b border-gray-50 ml-4 border-l-2 border-l-emerald-300">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-gray-800 truncate">{{ $webQuote->reference }} · {{ $webQuote->quote_to_name ?: $webQuote->requested_by }}</p>
                                                    <p class="text-[10px] text-gray-500 truncate">{{ $webQuote->created_at?->diffForHumans() ?? 'Reciente' }} · {{ $webQuote->part_code ?: 'Repuestos' }}</p>
                                                </div>
                                                <span class="shrink-0 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Licitación</span>
                                            </div>
                                        </a>
                                    @endforeach
                                @endif

                                @if($newOrdersCount > 0)
                                    <div class="border-b border-gray-100 px-4 py-3"><p class="text-sm font-semibold text-gray-800">Órdenes recientes ({{ $newOrdersCount }})</p><p class="text-xs text-gray-500">Creadas en las últimas 24 horas</p></div>
                                    @foreach($newOrdersAlerts as $newOrder)
                                        <a href="{{ route('orders.status.show', $newOrder) }}" class="block border-b border-gray-50 px-4 py-3 hover:bg-gray-50"><p class="text-xs font-semibold text-gray-800">{{ $newOrder->reference }}</p><p class="truncate text-xs text-gray-500">{{ $newOrder->client_name }} · {{ $newOrder->created_at?->diffForHumans() }}</p></a>
                                    @endforeach
                                @endif
                                @foreach($additionalQueues as $queue)
                                    <a href="{{ route($queue['destination']) }}" class="flex items-center justify-between gap-3 border-b border-gray-50 px-4 py-3 text-sm hover:bg-gray-50">
                                        <span class="font-semibold text-gray-800">{{ $queue['label'] }}</span>
                                        <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ $queue['count'] }}</span>
                                    </a>
                                @endforeach
                                @if($correctionsCount > 0)
                                    <a href="{{ route('sales.quotations.corrections') }}" class="block px-4 py-3 hover:bg-amber-50 border-b border-gray-50 group transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                                <p class="text-sm font-bold text-gray-800 group-hover:text-amber-700">Correcciones de Ordenes</p>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ $correctionsCount }}</span>
                                        </div>
                                    </a>
                                @endif
                                
                                @if($offerReviewCount > 0)
                                    <a href="{{ route('admin.offer-reviews') }}" class="block px-4 py-3 hover:bg-indigo-50 border-b border-gray-50 group transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                                                <p class="text-sm font-bold text-gray-800 group-hover:text-indigo-700">Revisión Ofertas Cliente</p>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-black px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">{{ $offerReviewCount }}</span>
                                        </div>
                                    </a>
                                @endif

                                @if($approvalCount > 0)
                                    <a href="{{ route('admin.approvals') }}" class="block px-4 py-3 hover:bg-rose-50 border-b border-gray-50 group transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                                                <p class="text-sm font-bold text-gray-800 group-hover:text-rose-700">Aprobaciones Internas (OC)</p>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-black px-2 py-0.5 rounded-full bg-rose-100 text-rose-800">{{ $approvalCount }}</span>
                                        </div>
                                    </a>
                                    <!-- List top 3 recent approval requests directly in dropdown -->
                                    @foreach($approvalAlerts as $approval)
                                        @php
                                            $quoteRef = $approval->tracker?->references?->firstWhere('source_module', 'quotation_requests');
                                        @endphp
                                        <a href="{{ route('admin.approvals') }}" class="block px-4 py-2 hover:bg-gray-50 border-b border-gray-50 ml-4 border-l-2 border-l-rose-200">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-gray-700 truncate">{{ $quoteRef?->reference_code ?? ('Solicitud #'.$approval->source_id) }}</p>
                                                    <p class="text-[10px] text-gray-400 truncate">Por {{ $approval->requester?->name ?? 'Sistema' }}</p>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                @endif
                            @endif
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        @endif

        @if($canSeeTickets)
            <div class="relative">
                <x-dropdown align="right" width="80">
                    <x-slot name="trigger">
                        <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200/80 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            @if($ticketCount > 0)
                                <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-black flex items-center justify-center">{{ $ticketCount > 9 ? '9+' : $ticketCount }}</span>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-bold text-gray-900">Tickets pendientes</p>
                            <p class="text-xs text-gray-500">{{ $ticketCount }} requieren respuesta</p>
                        </div>

                        <div class="max-h-80 overflow-y-auto">
                            @forelse($ticketAlerts as $ticket)
                                <a href="{{ route('admin.tickets') }}" class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-50">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs font-mono text-gray-400">{{ $ticket->code }}</p>
                                            <p class="text-sm font-bold text-gray-800 truncate">{{ $ticket->subject }}</p>
                                            <p class="text-xs text-gray-500 truncate">{{ $ticket->customer?->name ?? 'Cliente' }}</p>
                                        </div>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-50 text-rose-700">{{ $ticket->priority }}</span>
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-gray-400">Sin tickets pendientes.</div>
                            @endforelse
                        </div>

                        <div class="px-4 py-3 border-t border-gray-100">
                            <a href="{{ route('admin.tickets') }}" class="text-sm font-bold text-indigo-600 hover:text-indigo-800">Ver todos</a>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        @endif
        

</div>
