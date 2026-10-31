<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\ProductService;

class HomeController extends Controller
{
    public function index(ProductService $products)
    {
        return view('storefront.home', [
            'featuredProducts' => $products->featured(30)->unique(fn ($product) => mb_strtolower(trim($product->brand ?: $product->name)))->take(6)->values(),
            'browseCategories' => $products->categoryOptions(6),
        ]);
    }

    public function about()
    {
        return view('storefront.about');
    }
}
