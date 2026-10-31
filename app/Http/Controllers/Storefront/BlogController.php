<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\BlogService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request, BlogService $blog)
    {
        $category = $request->query('categoria');

        return view('storefront.blog.index', [
            'posts' => $blog->published($category),
            'categories' => $blog->categories(),
            'activeCategory' => $category,
        ]);
    }

    public function show(string $post, BlogService $blog)
    {
        return view('storefront.blog.show', ['post' => $blog->findBySlug($post)]);
    }
}
