<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\Entity;
use App\Models\Payment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FinancePaymentConcurrencyTest extends TestCase
{
    public function test_two_independent_processes_create_one_payment_and_one_evidence_file(): void
    {
        $entity = Entity::create(['name' => 'Concurrent supplier', 'type' => 'proveedor']);
        $account = AccountPayable::create([
            'entity_id' => $entity->id,
            'concept' => 'Concurrent payment test',
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'due_date' => '2026-09-01',
            'status' => 'pendiente',
        ]);

        $runId = (string) Str::ulid();
        $directory = storage_path('framework/testing/payment-concurrency-'.$runId);
        File::ensureDirectoryExists($directory);
        $sourcePath = $directory.DIRECTORY_SEPARATOR.'proof.pdf';
        $barrierPath = $directory.DIRECTORY_SEPARATOR.'go';
        File::put($sourcePath, 'identical-concurrent-proof-'.$runId);

        $processes = [];
        $resultPaths = [];
        $reference = 'CONCURRENT-'.$runId;
        $idempotencyKey = 'concurrent-'.$runId;

        try {
            foreach ([1, 2] as $worker) {
                $readyPath = $directory.DIRECTORY_SEPARATOR."ready-{$worker}";
                $resultPath = $directory.DIRECTORY_SEPARATOR."result-{$worker}.json";
                $resultPaths[] = $resultPath;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/ConcurrentPaymentWorker.php'),
                    (string) $account->id,
                    $sourcePath,
                    $readyPath,
                    $barrierPath,
                    $resultPath,
                    $idempotencyKey,
                    $reference,
                ], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $this->waitForWorkers($directory, $processes);
            File::put($barrierPath, 'go');

            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            }

            $results = array_map(
                fn (string $path): array => json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR),
                $resultPaths,
            );

            $this->assertSame($results[0]['payment_id'], $results[1]['payment_id']);
            $this->assertSame($results[0]['evidence_path'], $results[1]['evidence_path']);
            $this->assertDatabaseCount('payments', 1);
            $this->assertSame('25.00', $account->fresh()->paid_amount);
            $this->assertCount(1, $this->matchingEvidenceFiles($reference));
            Storage::disk('private')->assertExists($results[0]['evidence_path']);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            foreach ($this->matchingEvidenceFiles($reference) as $path) {
                Storage::disk('private')->delete($path);
            }

            Payment::where('idempotency_key', $idempotencyKey)->delete();
            $account->delete();
            $entity->delete();
            File::deleteDirectory($directory);
        }
    }

    private function waitForWorkers(string $directory, array $processes): void
    {
        $deadline = microtime(true) + 15;

        while (! File::exists($directory.DIRECTORY_SEPARATOR.'ready-1')
            || ! File::exists($directory.DIRECTORY_SEPARATOR.'ready-2')) {
            foreach ($processes as $process) {
                if ($process->isTerminated()) {
                    $this->fail('Un proceso termino antes de alcanzar la barrera: '.$process->getErrorOutput().$process->getOutput());
                }
            }

            if (microtime(true) >= $deadline) {
                $this->fail('Los procesos concurrentes no alcanzaron la barrera a tiempo.');
            }

            usleep(10_000);
        }
    }

    private function matchingEvidenceFiles(string $reference): array
    {
        $needle = Str::slug($reference);

        return array_values(array_filter(
            Storage::disk('private')->allFiles('finance/payments'),
            fn (string $path): bool => str_contains($path, $needle),
        ));
    }
}
