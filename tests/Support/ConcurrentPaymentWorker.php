<?php

use App\Services\PaymentRegistrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $accountId, $sourcePath, $readyPath, $barrierPath, $resultPath, $idempotencyKey, $reference] = $argv;

file_put_contents($readyPath, (string) getmypid());

$deadline = microtime(true) + 15;
while (! file_exists($barrierPath)) {
    if (microtime(true) >= $deadline) {
        file_put_contents($resultPath, json_encode(['error' => 'barrier_timeout'], JSON_THROW_ON_ERROR));
        exit(2);
    }

    usleep(10_000);
}

try {
    $payment = $app->make(PaymentRegistrationService::class)->register(
        'cxp',
        (int) $accountId,
        new UploadedFile($sourcePath, 'proof.pdf', 'application/pdf', null, true),
        'Concurrent supplier',
        [
            'amount' => '25.00',
            'currency' => 'NIO',
            'exchange_rate' => '1',
            'date' => '2026-08-01',
            'method' => 'Transferencia',
            'reference' => $reference,
            'registered_by' => null,
            'notes' => 'Concurrent request',
            'idempotency_key' => $idempotencyKey,
        ],
    );

    file_put_contents($resultPath, json_encode([
        'payment_id' => $payment->id,
        'evidence_path' => $payment->payment_evidence_path,
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    file_put_contents($resultPath, json_encode([
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR));
    exit(1);
}
