<?php

namespace Tests\Feature;

use App\Console\Commands\MigrateSensitiveFilesToPrivate;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MigrateSensitiveFilesToPrivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dry_run_then_moves_normalizes_and_can_be_repeated(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $customer = User::factory()->create();
        $legacyPath = 'ticket-messages/legacy.pdf';
        Storage::disk('public')->put($legacyPath, 'legacy-sensitive-file');

        $ticket = Ticket::create([
            'code' => 'TKT-MIGRATION-1',
            'customer_id' => $customer->id,
            'type' => 'soporte',
            'subject' => 'Migracion',
            'description' => 'Documento legado.',
            'priority' => 'media',
            'status' => Ticket::STATUS_OPEN,
            'attachment_path' => 'storage/'.$legacyPath,
        ]);

        $this->artisan('security:migrate-private-files', ['--dry-run' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertExists($legacyPath);
        Storage::disk('private')->assertMissing($legacyPath);
        $this->assertSame('storage/'.$legacyPath, $ticket->fresh()->attachment_path);

        $this->artisan('security:migrate-private-files')->assertSuccessful();

        Storage::disk('public')->assertMissing($legacyPath);
        Storage::disk('private')->assertExists($legacyPath);
        $this->assertSame($legacyPath, $ticket->fresh()->attachment_path);

        $this->artisan('security:migrate-private-files')->assertSuccessful();
        Storage::disk('private')->assertExists($legacyPath);
    }

    public function test_command_reports_missing_files_without_damaging_database_path(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $customer = User::factory()->create();
        $path = 'tickets/missing.pdf';
        $ticket = Ticket::create([
            'code' => 'TKT-MIGRATION-2',
            'customer_id' => $customer->id,
            'type' => 'soporte',
            'subject' => 'Faltante',
            'description' => 'Documento faltante.',
            'priority' => 'media',
            'status' => Ticket::STATUS_OPEN,
            'attachment_path' => $path,
        ]);

        $this->artisan('security:migrate-private-files')->assertFailed();

        $this->assertSame($path, $ticket->fresh()->attachment_path);
        Storage::disk('private')->assertMissing($path);

        $this->artisan('security:migrate-private-files', ['--allow-missing' => true])
            ->assertSuccessful();
    }

    public function test_conflicting_destination_is_not_overwritten_or_deleted(): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $customer = User::factory()->create();
        $path = 'tickets/conflict.pdf';
        Storage::disk('public')->put($path, 'public-version');
        Storage::disk('private')->put($path, 'private-version');

        $ticket = Ticket::create([
            'code' => 'TKT-MIGRATION-CONFLICT',
            'customer_id' => $customer->id,
            'type' => 'soporte',
            'subject' => 'Conflicto',
            'description' => 'Documento en conflicto.',
            'priority' => 'media',
            'status' => Ticket::STATUS_OPEN,
            'attachment_path' => $path,
        ]);

        $this->artisan('security:migrate-private-files')->assertFailed();

        $this->assertSame('public-version', Storage::disk('public')->get($path));
        $this->assertSame('private-version', Storage::disk('private')->get($path));
        $this->assertSame($path, $ticket->fresh()->attachment_path);
    }

    public function test_failed_source_deletion_raises_an_error(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')->once()->with('tickets/proof.pdf')->andReturnFalse();

        $command = new class extends MigrateSensitiveFilesToPrivate
        {
            public function remove(FilesystemAdapter $disk): void
            {
                $this->removeSource($disk, 'tickets/proof.pdf', 'public');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('public source could not be removed');

        $command->remove($disk);
    }

    public function test_public_rollback_is_blocked_in_production(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $this->artisan('security:migrate-private-files', [
                '--rollback' => true,
                '--force' => true,
            ])->assertFailed();
        } finally {
            $this->app->instance('env', $originalEnvironment);
        }
    }

    #[DataProvider('unsafePathProvider')]
    public function test_unsafe_database_paths_are_rejected(string $path): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $customer = User::factory()->create();
        $ticket = Ticket::create([
            'code' => 'TKT-INVALID-'.md5($path),
            'customer_id' => $customer->id,
            'type' => 'soporte',
            'subject' => 'Ruta invalida',
            'description' => 'Documento con ruta insegura.',
            'priority' => 'media',
            'status' => Ticket::STATUS_OPEN,
            'attachment_path' => $path,
        ]);

        $this->artisan('security:migrate-private-files')->assertFailed();
        $this->assertSame($path, $ticket->fresh()->attachment_path);
    }

    /** @return array<string, array{string}> */
    public static function unsafePathProvider(): array
    {
        return [
            'parent traversal' => ['../secret.pdf'],
            'absolute unix path' => ['/var/private/secret.pdf'],
            'absolute windows path' => ['C:/private/secret.pdf'],
            'remote url' => ['https://example.test/secret.pdf'],
        ];
    }
}
