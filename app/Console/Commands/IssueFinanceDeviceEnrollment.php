<?php

namespace App\Console\Commands;

use App\Models\FinanceDeviceEnrollment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueFinanceDeviceEnrollment extends Command
{
    protected $signature = 'finance:approve-device
        {email : Correo del usuario financiero}
        {device_id : UUID estable del dispositivo}
        {device_name : Nombre reconocible del dispositivo}
        {--minutes=15 : Vigencia del código de un solo uso}';

    protected $description = 'Aprueba un dispositivo financiero y emite un código de enrolamiento de un solo uso';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $deviceId = (string) $this->argument('device_id');
        $deviceName = trim((string) $this->argument('device_name'));
        $minutes = max(1, min(60, (int) $this->option('minutes')));

        if (! Str::isUuid($deviceId)) {
            $this->error('device_id debe ser un UUID válido.');

            return self::INVALID;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! $user->is_active || ! $user->hasPermissionTo('finance.view')) {
            $this->error('El usuario no existe o no está habilitado para finanzas.');

            return self::FAILURE;
        }

        $code = Str::random(48);
        FinanceDeviceEnrollment::query()->updateOrCreate(
            ['user_id' => $user->id, 'device_id' => $deviceId],
            [
                'device_name' => $deviceName,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes($minutes),
                'consumed_at' => null,
                'approved_by' => null,
            ]
        );

        $this->warn('El código se muestra una sola vez y no debe enviarse por el mismo canal que la contraseña.');
        $this->line($code);

        return self::SUCCESS;
    }
}
