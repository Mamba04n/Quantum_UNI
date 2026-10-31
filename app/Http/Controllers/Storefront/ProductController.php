<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\CartService;
use App\Services\Storefront\ProductService;
use App\Support\StorefrontUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(Product $product, ProductService $products, ?string $slug = null)
    {
        abort_unless($product->active, 404);

        if ($slug !== StorefrontUrl::productSlug($product)) {
            return redirect()->to(StorefrontUrl::product($product), 301);
        }

        $heading = trim($product->name.' '.($product->part_number && ! str_contains(Str::lower($product->name), Str::lower($product->part_number)) ? $product->part_number : ''));

        return view('storefront.products.show', [
            'product' => $product,
            'relatedProducts' => $products->related($product),
            'seoTitle' => $heading.' | Quantum Soluciones',
            'seoDescription' => Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($product->description ?: $heading.($product->brand ? '. Marca: '.$product->brand : '').'. Consulta este repuesto con Quantum Soluciones.'))), 160, ''),
            'canonicalUrl' => StorefrontUrl::product($product),
        ]);
    }

    public function addToCart(Request $request, Product $product, CartService $cart)
    {
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:999']]);
        $cart->add($product, (int) ($data['quantity'] ?? 1));

        return redirect()->route('storefront.cart')->with('success', 'Producto agregado a la solicitud.');
    }
}
