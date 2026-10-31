<?php

namespace App\Services\Storefront;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BlogService
{
    public function published(?string $category = null): Collection
    {
        if (! Schema::hasTable('tech_blog_posts')) {
            return collect();
        }

        return DB::table('tech_blog_posts')
            ->where('activo', true)
            ->when($category, fn ($query) => $query->where('categoria', $category))
            ->latest()
            ->get();
    }

    public function categories(): Collection
    {
        if (! Schema::hasTable('tech_blog_posts')) {
            return collect();
        }

        return DB::table('tech_blog_posts')
            ->select('categoria', DB::raw('count(*) as total'))
            ->where('activo', true)
            ->whereNotNull('categoria')
            ->where('categoria', '<>', '')
            ->groupBy('categoria')
            ->orderBy('categoria')
            ->get();
    }

    public function findBySlug(string $slug): object
    {
        abort_unless(Schema::hasTable('tech_blog_posts'), 404);

        return DB::table('tech_blog_posts')->where('activo', true)->where('slug', $slug)->firstOrFail();
    }
}
