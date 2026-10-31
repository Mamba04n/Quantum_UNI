<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeCleanTrackingDefaults extends Command
{
    protected $signature = 'normalization:clean-tracking-defaults';
    protected $description = 'Clean tracking defaults that contain redundant quotes (e.g. \'\'\'pedido_extranjero\'\'\')';

    public function handle()
    {
        $this->info('Cleaning Tracking Defaults...');

        // Clean order_statuses
        $statusesCount = DB::table('order_statuses')
            ->where('administrative_status', 'LIKE', "'''%'''")
            ->count();
            
        if ($statusesCount > 0) {
            DB::statement("UPDATE order_statuses SET administrative_status = REPLACE(administrative_status, '''', '')");
            $this->info("Cleaned $statusesCount records in order_statuses.administrative_status");
        }
        
        $phaseCount = DB::table('order_statuses')
            ->where('administrative_phase', 'LIKE', "'''%'''")
            ->count();
            
        if ($phaseCount > 0) {
            DB::statement("UPDATE order_statuses SET administrative_phase = REPLACE(administrative_phase, '''', '')");
            $this->info("Cleaned $phaseCount records in order_statuses.administrative_phase");
        }

        // Clean order_trackers
        $tStatusesCount = DB::table('order_trackers')
            ->where('operational_status', 'LIKE', "'''%'''")
            ->count();
            
        if ($tStatusesCount > 0) {
            DB::statement("UPDATE order_trackers SET operational_status = REPLACE(operational_status, '''', '')");
            $this->info("Cleaned $tStatusesCount records in order_trackers.operational_status");
        }

        $tPhaseCount = DB::table('order_trackers')
            ->where('operational_phase', 'LIKE', "'''%'''")
            ->count();
            
        if ($tPhaseCount > 0) {
            DB::statement("UPDATE order_trackers SET operational_phase = REPLACE(operational_phase, '''', '')");
            $this->info("Cleaned $tPhaseCount records in order_trackers.operational_phase");
        }

        // Clean any double quotes
        DB::statement("UPDATE order_statuses SET administrative_status = REPLACE(administrative_status, '\"', '')");
        DB::statement("UPDATE order_statuses SET administrative_phase = REPLACE(administrative_phase, '\"', '')");
        DB::statement("UPDATE order_trackers SET operational_status = REPLACE(operational_status, '\"', '')");
        DB::statement("UPDATE order_trackers SET operational_phase = REPLACE(operational_phase, '\"', '')");

        $this->info('Cleanup complete.');
    }
}
