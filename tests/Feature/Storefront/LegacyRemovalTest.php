<?php

namespace Tests\Feature\Storefront;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class LegacyRemovalTest extends TestCase
{
    public function test_legacy_directories_and_runtime_references_do_not_exist(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('quantum-soluciones'));
        $this->assertDirectoryDoesNotExist(public_path('quantum-soluciones'));

        foreach ([app_path(), resource_path('views'), base_path('routes'), base_path('config')] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                    $this->assertStringNotContainsString('quantum-soluciones', file_get_contents($file->getPathname()), $file->getPathname());
                }
            }
        }
    }
}
