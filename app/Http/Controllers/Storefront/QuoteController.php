<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;

class QuoteController extends Controller
{
    public function index()
    {
        return redirect()->route('storefront.cart');
    }
}
