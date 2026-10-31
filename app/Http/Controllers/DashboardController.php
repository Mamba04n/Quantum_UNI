<?php

namespace App\Http\Controllers;

use App\Domain\Administracion\Dashboard\Queries\GetDashboardMetrics;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class DashboardController extends Controller
{
    public function __invoke(GetDashboardMetrics $query): View|RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->hasRole(User::ROLE_CLIENTE_TIENDA)) {
            return redirect()->route('customer.portal');
        }

        abort_unless($user->canAccess('dashboard'), 403);

        return view('dashboard', $query->execute()->toViewData());
    }
}
