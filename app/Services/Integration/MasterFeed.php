<?php

namespace App\Services\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MasterFeed
{
    public function snapshot(): array
    {
        $clock = DB::table('integration_master_clock')->where('id', 1)->first();

        return ['version' => (int) $clock->version, 'until' => $this->utc($clock->observed_at)];
    }

    public function query(string $resource, int $snapshot): Builder
    {
        $latest = DB::table('integration_master_versions')->selectRaw('external_id, MAX(version) AS version')
            ->where('resource', $resource)->where('version', '<=', $snapshot)->groupBy('external_id');

        return DB::table('integration_master_versions as v')->joinSub($latest, 'latest', function ($join) {
            $join->on('v.external_id', '=', 'latest.external_id')->on('v.version', '=', 'latest.version');
        })->where('v.resource', $resource)->select('v.*');
    }

    public function serialize(object $row): array
    {
        $data = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
        if ($row->deleted) {
            $data = ['id' => (string) $row->external_id, 'active' => false];
        } else {
            $data['active'] = (bool) $data['active'];
        }

        return $data + ['deleted' => (bool) $row->deleted, 'version' => (int) $row->version, 'updated_at' => $this->utc($row->observed_at)];
    }

    private function utc(string $date): string
    {
        return CarbonImmutable::parse($date, 'UTC')->format('Y-m-d\TH:i:s.u\Z');
    }
}
