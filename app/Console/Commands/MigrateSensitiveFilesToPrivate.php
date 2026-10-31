<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MigrateSensitiveFilesToPrivate extends Command
{
    protected $signature = 'security:migrate-private-files
                            {--dry-run : Report changes without copying, updating, or deleting}
                            {--rollback : Move managed files back to the public disk}
                            {--allow-missing : Do not fail when a database record points to a missing file}
                            {--force : Skip rollback confirmation}';

    protected $description = 'Move sensitive files between public and private storage safely and idempotently';

    /** @var array<string, int> */
    private array $totals = [
        'moved' => 0,
        'already' => 0,
        'missing' => 0,
        'conflicts' => 0,
        'invalid' => 0,
        'records' => 0,
    ];

    public function handle(): int
    {
        $rollback = (bool) $this->option('rollback');
        $dryRun = (bool) $this->option('dry-run');

        if ($rollback && ! $dryRun && app()->environment('production')) {
            $this->error('Rollback to public storage is disabled in production.');

            return self::FAILURE;
        }

        if ($rollback && ! $dryRun && ! $this->option('force')
            && ! $this->confirm('Rollback makes sensitive files web-public. Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $sourceName = $rollback ? 'private' : 'public';
        $destinationName = $rollback ? 'public' : 'private';
        $this->info(sprintf(
            '%s sensitive files: %s -> %s',
            $dryRun ? 'Scanning' : 'Migrating',
            $sourceName,
            $destinationName
        ));

        foreach (config('private-files.resources', []) as [$table, $column, $classification]) {
            $this->migrateColumn($table, $column, $classification, $sourceName, $destinationName, $dryRun);
        }

        $this->newLine();
        $this->table(['Result', 'Count'], collect($this->totals)->map(fn ($value, $key) => [$key, $value])->values()->all());

        $failures = $this->totals['conflicts'] + $this->totals['invalid'];

        if (! $this->option('allow-missing')) {
            $failures += $this->totals['missing'];
        }

        return $failures > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function migrateColumn(
        string $table,
        string $column,
        string $classification,
        string $sourceName,
        string $destinationName,
        bool $dryRun
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            $this->line("SKIP {$table}.{$column} (not present)");

            return;
        }

        $this->line("SCAN {$table}.{$column} [{$classification}]");

        DB::table($table)
            ->select('id', $column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($table, $column, $sourceName, $destinationName, $dryRun): void {
                foreach ($records as $record) {
                    $this->migrateRecord(
                        $table,
                        $column,
                        $record->id,
                        (string) $record->{$column},
                        $sourceName,
                        $destinationName,
                        $dryRun
                    );
                }
            });
    }

    private function migrateRecord(
        string $table,
        string $column,
        int|string $id,
        string $storedPath,
        string $sourceName,
        string $destinationName,
        bool $dryRun
    ): void {
        $this->totals['records']++;

        try {
            $path = $this->normalizePath($storedPath);
        } catch (\InvalidArgumentException $exception) {
            $this->totals['invalid']++;
            $this->error("INVALID {$table}#{$id}: {$exception->getMessage()}");

            return;
        }

        $source = Storage::disk($sourceName);
        $destination = Storage::disk($destinationName);
        $sourceExists = $source->exists($path);
        $destinationExists = $destination->exists($path);

        if ($sourceExists && $destinationExists && $this->checksum($source, $path) !== $this->checksum($destination, $path)) {
            $this->totals['conflicts']++;
            $this->error("CONFLICT {$table}#{$id}: {$path}");

            return;
        }

        if (! $sourceExists && ! $destinationExists) {
            $this->totals['missing']++;
            $this->warn("MISSING {$table}#{$id}: {$path}");

            return;
        }

        if ($dryRun) {
            $action = $sourceExists ? 'WOULD MOVE' : 'ALREADY';
            $this->line("{$action} {$table}#{$id}: {$storedPath} -> {$path}");
            $this->totals[$sourceExists ? 'moved' : 'already']++;

            return;
        }

        try {
            if ($sourceExists && ! $destinationExists) {
                $this->copyAndVerify($source, $destination, $path);
            }

            DB::transaction(function () use ($table, $column, $id, $path): void {
                DB::table($table)->where('id', $id)->update([$column => $path]);
            });

            if ($sourceExists) {
                $this->removeSource($source, $path, $sourceName);
                $this->totals['moved']++;
                $this->info("MOVED {$table}#{$id}: {$path}");
            } else {
                $this->totals['already']++;
            }
        } catch (Throwable $exception) {
            $this->totals['conflicts']++;
            $this->error("ERROR {$table}#{$id}: {$exception->getMessage()}");
        }
    }

    protected function removeSource(FilesystemAdapter $source, string $path, string $sourceName): void
    {
        $deleted = $source->delete($path);

        if (! $deleted || $source->exists($path)) {
            throw new RuntimeException("The {$sourceName} source could not be removed: {$path}");
        }
    }

    private function copyAndVerify(FilesystemAdapter $source, FilesystemAdapter $destination, string $path): void
    {
        $stream = $source->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Cannot read {$path}");
        }

        try {
            if (! $destination->writeStream($path, $stream)) {
                throw new RuntimeException("Cannot write {$path}");
            }
        } finally {
            fclose($stream);
        }

        if ($this->checksum($source, $path) !== $this->checksum($destination, $path)) {
            $destination->delete($path);
            throw new RuntimeException("Checksum verification failed for {$path}");
        }
    }

    private function checksum(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Cannot checksum {$path}");
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/?(?:storage|public|private)/+#', '', $path) ?? $path;

        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_contains($path, "\0") || in_array('..', explode('/', $path), true)
            || filter_var($path, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('unsafe or non-relative path');
        }

        return str_starts_with($path, './') ? substr($path, 2) : $path;
    }
}
