<?php

// Run only against the dedicated ERP testing database. Never a production seed.
use App\Models\IntegrationClient;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
$connection = config('database.default');
if (! $app->environment('testing') || ! str_ends_with((string) config("database.connections.$connection.database"), '_testing')) {
    throw new RuntimeException('This fixture requires the isolated ERP testing database.');
}

$client = IntegrationClient::firstOrCreate(['name' => 'cmms-http-smoke']);
$client->tokens()->delete();
if (in_array('--cleanup', $argv, true)) {
    User::where('email', 'cmms-smoke@example.test')->delete();
    $client->delete();
    exit;
}
$user = User::firstOrCreate(['email' => 'cmms-smoke@example.test'], [
    'name' => 'CMMS HTTP smoke', 'is_active' => true,
    'password' => Str::random(64),
]);
echo json_encode([
    'id' => (string) $user->id,
    'token' => $client->createToken('http-smoke', ['integration:read', 'users:read'], now()->addMinutes(10))->plainTextToken,
], JSON_THROW_ON_ERROR);
