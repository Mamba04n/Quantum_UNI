<?php

namespace App\Console\Commands;

use App\Support\PhpEntrypointPolicy;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AuditPhpEntrypoints extends Command
{
    protected $signature = 'security:audit-php-entrypoints';

    protected $description = 'Reject tracked PHP entrypoints outside the approved Laravel paths';

    public function handle(PhpEntrypointPolicy $policy): int
    {
        $process = new Process(['git', 'ls-files'], base_path());
        $process->mustRun();

        $trackedFiles = preg_split('/\R/', trim($process->getOutput())) ?: [];
        $rejected = $policy->rejected($trackedFiles);

        if ($rejected === []) {
            $this->info('PHP entrypoint policy passed. Only public/index.php is web executable.');

            return self::SUCCESS;
        }

        $this->error('Executable PHP files violate the repository policy:');

        foreach ($rejected as $path) {
            $this->line(" - {$path}");
        }

        return self::FAILURE;
    }
}
