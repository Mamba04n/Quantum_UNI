<?php

namespace App\Http\Controllers;

use App\Domain\Pedidos\Tracking\Services\PublicTrackingService;
use App\Models\OrderTracker;
use Illuminate\Http\Request;

class PublicTrackingController extends Controller
{
    public function show(Request $request, PublicTrackingService $service, ?string $code = null)
    {
        $code = strtoupper(trim($code ?? $request->query('code', '')));
        $tracker = preg_match('/^QS-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{12}$/D', $code) ? OrderTracker::where('public_tracking_code', $code)->first() : null;
        $data = $tracker ? $service->publicData($tracker) : null;

        return response()->view('public-commerce.tracking', compact('code', 'data'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
