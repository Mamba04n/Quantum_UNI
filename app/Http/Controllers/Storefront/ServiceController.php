<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;

class ServiceController extends Controller
{
    public function index()
    {
        return view('storefront.services.index');
    }
}
