<?php

namespace Tests\Feature;

use App\Support\PhpEntrypointPolicy;
use Tests\TestCase;

class StandalonePhpScriptGuardTest extends TestCase
{
    public function test_repository_does_not_track_standalone_php_executables(): void
    {
        $this->artisan('security:audit-php-entrypoints')->assertSuccessful();
    }

    public function test_only_the_front_controller_is_allowed_inside_public(): void
    {
        $policy = app(PhpEntrypointPolicy::class);

        $this->assertTrue($policy->isAllowed('public/index.php'));
        $this->assertFalse($policy->isAllowed('public/debug.php'));
        $this->assertFalse($policy->isAllowed('public/test.php'));
        $this->assertFalse($policy->isAllowed('public/shell.php'));
        $this->assertFalse($policy->isAllowed('public/nested/index.php'));
        $this->assertFalse($policy->isAllowed('public/shell.phtml'));
        $this->assertFalse($policy->isAllowed('public/archive.phar'));
        $this->assertFalse($policy->isAllowed('public/legacy.php8'));
    }

    public function test_php_source_is_allowed_only_in_laravel_directories(): void
    {
        $policy = app(PhpEntrypointPolicy::class);

        $this->assertTrue($policy->isAllowed('app/Models/User.php'));
        $this->assertTrue($policy->isAllowed('resources/views/example.blade.php'));
        $this->assertFalse($policy->isAllowed('check_db.php'));
        $this->assertFalse($policy->isAllowed('scripts/test.php'));
        $this->assertFalse($policy->isAllowed('other/tool.php'));
        $this->assertFalse($policy->isAllowed('app/tool.phtml'));
    }
}
