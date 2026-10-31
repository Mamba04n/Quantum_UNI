<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceNavigation;
use App\Support\WorkspaceOverview;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class WorkspaceController extends Controller
{
    public function __invoke(Request $request, WorkspaceNavigation $navigation, WorkspaceOverview $overview, ?string $area = null): View|RedirectResponse
    {
        $areas = $navigation->areas($request->user());
        abort_if($area !== null && ! isset($areas[$area]), 404);

        $metrics = $area === null ? $overview->metrics($request->user(), null, $navigation) : [];
        $recentQuotes = collect();
        if ($area === null && $navigation->allows($request->user(), 'sales.quotations.phase')) {
            $presenter = app(\App\Livewire\Ventas\Cotizaciones\Kanban\GestorKanban::class);
            $recentQuotes = \App\Models\QuotationRequest::with('purchaseOrders')
                ->where('user_id', $request->user()->id)->whereNull('sale_order_id')
                ->whereNotIn('status', [\App\Models\QuotationRequest::STATUS_CANCELLED, \App\Models\QuotationRequest::STATUS_CLOSED])
                ->latest('updated_at')->limit(12)->get()
                ->filter(function ($quote) use ($presenter, $request) {
                    $tab = $presenter->flowStages[$presenter->stageKeyForQuote($quote)]['tab'] ?? 'request';
                    return $request->user()->can($presenter->tabs[$tab]['permission']);
                })->take(3)->map(fn ($quote) => [
                    'reference' => $quote->reference, 'client' => $quote->quote_to_name,
                    'stage' => $presenter->flowStages[$presenter->stageKeyForQuote($quote)]['label'] ?? $quote->status,
                    'url' => $presenter->getQuoteUrl($quote),
                    'action' => $presenter->nextActionForStatus($quote->status),
                ]);
        }

        $salesOrders = collect();

        return view('workspaces.index', compact('areas', 'area', 'metrics', 'recentQuotes', 'salesOrders'));
    }
}
