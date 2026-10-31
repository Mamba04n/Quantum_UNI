<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\IntegrationClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueIntegrationToken extends Command
{
    protected $signature = 'integration:token {client=QuantumCMMS} {--days=7} {--masters : Incluye lectura de clientes, activos y empleados} {--revoke-existing}';

    protected $description = 'Emite una credencial técnica CMMS de identidad y, con --masters, lectura de maestros; se muestra una sola vez.';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $name = trim((string) $this->argument('client'));
        if (! $days || $days < 1 || $days > 30 || $name === '' || mb_strlen($name) > 100) {
            $this->error('Nombre requerido y vigencia de 1 a 30 días.');

            return self::FAILURE;
        }

        $secret = DB::transaction(function () use ($name, $days): string {
            $client = IntegrationClient::firstOrCreate(['name' => $name]);
            if (! $client->fresh()->is_active) {
                throw new \RuntimeException('El cliente de integración está deshabilitado.');
            }
            if ($this->option('revoke-existing')) {
                $client->tokens()->delete();
            }
            $abilities = ['integration:read', 'users:read'];
            if ($this->option('masters')) {
                $abilities = [...$abilities, 'customers:read', 'assets:read', 'employees:read'];
            }
            $token = $client->createToken('cmms-integration', $abilities, now()->addDays($days));
            AuditLog::create([
                'user_id' => null, 'action' => 'integration.token_issued',
                'entity_type' => 'integration_client', 'entity_id' => $client->id,
                'metadata' => ['actor_type' => 'system', 'correlation_id' => (string) Str::uuid(), 'expires_in_days' => $days],
                'created_at' => now(),
            ]);

            return $token->plainTextToken;
        });
        $this->line($secret);

        return self::SUCCESS;
    }
}
