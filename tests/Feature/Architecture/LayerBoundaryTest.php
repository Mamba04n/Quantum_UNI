<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LayerBoundaryTest extends TestCase
{
    public function test_domain_code_does_not_import_http_controllers_or_livewire(): void
    {
        foreach (File::allFiles(app_path('Domain')) as $file) {
            $contents = $file->getContents();

            $this->assertStringNotContainsString(
                'use App\\Http\\Controllers\\',
                $contents,
                $file->getPathname().' imports an HTTP controller.'
            );
            $this->assertStringNotContainsString(
                'use Livewire\\',
                $contents,
                $file->getPathname().' imports Livewire.'
            );
        }
    }

    public function test_domain_does_not_increase_legacy_app_services_dependencies(): void
    {
        $legacyImports = collect(File::allFiles(app_path('Domain')))->filter(
            fn ($file): bool => str_contains($file->getContents(), 'use App\\Services\\')
        );

        $this->assertLessThanOrEqual(
            51,
            $legacyImports->count(),
            'The legacy App\\Services dependency baseline grew; inject a Domain contract instead.'
        );
    }

    public function test_legacy_client_model_is_not_imported_by_application_code(): void
    {
        foreach (File::allFiles(app_path()) as $file) {
            $this->assertStringNotContainsString(
                'use App\\Models\\Client;',
                $file->getContents(),
                $file->getPathname().' introduced a dependency on the deprecated Client adapter.'
            );
        }
    }

    public function test_absolute_storefront_assets_referenced_by_css_exist_in_public(): void
    {
        $css = File::get(resource_path('css/storefront-public.css'));
        preg_match_all('/url\(["\'](\/storefront\/[^"\']+)["\']\)/', $css, $matches);

        foreach (array_unique($matches[1]) as $asset) {
            $this->assertFileExists(
                public_path(ltrim($asset, '/')),
                "The CSS references a missing public asset: {$asset}"
            );
        }
    }

    public function test_static_storefront_assets_referenced_by_blade_exist_in_public(): void
    {
        foreach (File::allFiles(resource_path('views')) as $view) {
            preg_match_all(
                '/asset\(\s*[\'\"](storefront\/[^\'\"]+)[\'\"]\s*\)/',
                $view->getContents(),
                $matches
            );

            foreach (array_unique($matches[1]) as $asset) {
                $this->assertFileExists(
                    public_path($asset),
                    "{$view->getPathname()} references a missing public asset: {$asset}"
                );
            }
        }
    }

    #[DataProvider('rbacPivotProvider')]
    public function test_application_code_does_not_write_rbac_pivots_directly(string $pivot): void
    {
        foreach (File::allFiles(app_path()) as $file) {
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR)
                || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Rbac'.DIRECTORY_SEPARATOR)
                || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = $file->getContents();
            $this->assertDoesNotMatchRegularExpression(
                "/(?:DB::table|from|into)\\s*\\(?'?\"?{$pivot}/i",
                $contents,
                $file->getPathname()." accesses {$pivot} directly."
            );
        }
    }

    /** @return array<string, array{string}> */
    public static function rbacPivotProvider(): array
    {
        return [
            'role_user' => ['role_user'],
            'permission_user' => ['permission_user'],
            'permission_role' => ['permission_role'],
        ];
    }
}
