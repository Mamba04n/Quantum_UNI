<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $recentActivity = ActivityLog::query()->where('user_id', $request->user()->id)
            ->latest('created_at')->latest('id')->limit(5)
            ->get(['id', 'module', 'description', 'created_at']);

        return view('profile', compact('recentActivity'));
    }
}
