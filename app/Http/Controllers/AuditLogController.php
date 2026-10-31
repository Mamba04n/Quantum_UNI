<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

final class AuditLogController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.activity');
    }
}
