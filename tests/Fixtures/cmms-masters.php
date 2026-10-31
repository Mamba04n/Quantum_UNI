<?php

use App\Models\IntegrationClient;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
$connection = config('database.default');
if (! $app->environment('testing') || ! str_ends_with((string) config("database.connections.$connection.database"), '_testing')) {
    throw new RuntimeException('Requires the isolated ERP testing database.');
}
$ids = DB::table('entities')->where('name', 'like', 'CMMS-P2-%')->orderBy('id')->pluck('id');
$user = User::where('email', 'cmms-p2@example.test')->first();
if (in_array('--mutate', $argv, true)) {
    DB::transaction(function () use ($ids, $user) {
        DB::table('entities')->where('id', $ids[0])->update(['name' => 'CMMS-P2-renamed']);
        DB::table('client_machines')->where('internal_code', 'CMMS-P2')->update(['entity_id' => $ids[1]]);
        DB::table('entities')->where('id', $ids->last())->delete();
        DB::table('users')->where('id', $user->id)->update(['is_active' => false, 'name' => 'CMMS-P2-inactive']);
    });
    echo "ERP testing fixtures changed.\n";
    exit;
}
if (in_array('--cleanup', $argv, true)) {
    DB::table('client_machines')->where('internal_code', 'CMMS-P2')->delete();
    DB::table('entities')->where('name', 'like', 'CMMS-P2-%')->delete();
    $user?->delete();
    $client = IntegrationClient::where('name', 'cmms-p2-smoke')->first();
    $client?->tokens()->delete();
    $client?->delete();
    echo "Temporary ERP records and credentials removed.\n";
    exit;
}
if ($ids->isNotEmpty() || $user) {
    throw new RuntimeException('Clean up the previous fixture before creating another.');
}
$result = DB::transaction(function () {
    $ids = [];
    for ($i = 1; $i <= 28; $i++) {
        $ids[] = (string) DB::table('entities')->insertGetId(['name' => 'CMMS-P2-'.$i, 'type' => 'cliente', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }
    $asset = DB::table('client_machines')->insertGetId(['entity_id' => $ids[0], 'name' => 'CMMS-P2-asset', 'internal_code' => 'CMMS-P2', 'status' => 'activa', 'created_at' => now(), 'updated_at' => now()]);
    $user = User::create(['name' => 'CMMS-P2-person', 'email' => 'cmms-p2@example.test', 'password' => Str::random(64), 'is_active' => true]);
    $employee = DB::table('employee_profiles')->insertGetId(['user_id' => $user->id, 'job_title' => 'Técnico', 'status' => 'active', 'identity_document' => 'NOT_EXPOSED', 'created_at' => now(), 'updated_at' => now()]);
    $client = IntegrationClient::create(['name' => 'cmms-p2-smoke']);
    $token = $client->createToken('phase2-test', ['integration:read', 'users:read', 'customers:read', 'assets:read', 'employees:read'], now()->addMinutes(30));

    return ['customers' => $ids, 'asset' => (string) $asset, 'employee' => (string) $employee, 'user' => (string) $user->id, 'token' => $token->plainTextToken];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
