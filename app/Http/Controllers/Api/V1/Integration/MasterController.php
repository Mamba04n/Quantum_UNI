<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Services\Integration\MasterFeed;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class MasterController extends Controller
{
    public function index(Request $request, MasterFeed $feed): JsonResponse
    {
        $customer = $request->route('customer');
        $resource = $request->route()->defaults['resource'];
        $input = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:200',
            'updated_since' => 'sometimes|date_format:Y-m-d\TH:i:s.u\Z',
            'cursor' => 'sometimes|string|max:8192',
        ]);
        $client = $request->attributes->get('integration.client_id');
        if (isset($input['cursor'])) {
            try {
                $state = json_decode(Crypt::decryptString($input['cursor']), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException) {
                abort(422, 'Cursor inválido.');
            }
            abort_unless(($state['resource'] ?? null) === $resource && ($state['client'] ?? null) === $client && ($state['customer'] ?? null) === $customer, 422);
            abort_if(isset($input['limit']) && (int) $input['limit'] !== $state['limit'], 422);
            abort_if(isset($input['updated_since']) && $input['updated_since'] !== $state['since'], 422);
        } else {
            $snapshot = $feed->snapshot();
            $state = ['resource' => $resource, 'client' => $client, 'customer' => $customer,
                'snapshot' => $snapshot['version'], 'until' => $snapshot['until'],
                'since' => $input['updated_since'] ?? null, 'limit' => (int) ($input['limit'] ?? 100),
                'last_version' => 0, 'last_id' => 0];
        }
        if ($customer !== null) {
            $owner = $feed->query('customers', $state['snapshot'])->where('v.external_id', $customer)->first();
            abort_unless($owner && ! $owner->deleted, 404);
        }
        $query = $feed->query($resource, $state['snapshot']);
        if ($state['since']) {
            $query->where('v.observed_at', '>=', CarbonImmutable::parse($state['since'])->format('Y-m-d H:i:s.u'));
        }
        if ($customer !== null) {
            // Include exits from this owner's scope as tombstones, so a transfer
            // cannot leave a stale asset in an incremental customer-specific feed.
            $query->whereExists(function ($q) use ($customer, $state) {
                $q->selectRaw('1')->from('integration_master_versions as history')
                    ->where('history.resource', 'assets')->whereColumn('history.external_id', 'v.external_id')
                    ->where('history.version', '<=', $state['snapshot'])
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(history.payload, '$.customer_id')) = ?", [$customer]);
            });
        }
        $count = (clone $query)->count();
        $rows = $query->where(function ($q) use ($state) {
            $q->where('v.version', '>', $state['last_version'])->orWhere(function ($q) use ($state) {
                $q->where('v.version', $state['last_version'])->where('v.external_id', '>', $state['last_id']);
            });
        })->orderBy('v.version')->orderBy('v.external_id')->limit($state['limit'] + 1)->get();
        $more = $rows->count() > $state['limit'];
        $rows = $rows->take($state['limit']);
        $next = null;
        if ($more) {
            $state['last_version'] = (int) $rows->last()->version;
            $state['last_id'] = (int) $rows->last()->external_id;
            $next = Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR));
        }

        return response()->json(['data' => $rows->map(function ($row) use ($feed, $customer) {
            if ($customer !== null && (json_decode($row->payload, true)['customer_id'] ?? null) !== $customer) {
                $row->deleted = true;
            }

            return $feed->serialize($row);
        })->values(), 'meta' => [
            'next_cursor' => $next, 'snapshot_until' => $state['until'], 'snapshot_version' => $state['snapshot'],
            'total' => $count, 'schema_version' => '1.1',
        ]]);
    }

    public function show(Request $request, MasterFeed $feed, string $id): JsonResponse
    {
        $input = $request->validate(['snapshot_version' => 'sometimes|integer|min:0']);
        $current = $feed->snapshot()['version'];
        $snapshot = (int) ($input['snapshot_version'] ?? $current);
        abort_if($snapshot > $current, 422);
        $row = $feed->query($request->route()->defaults['resource'], $snapshot)->where('v.external_id', $id)->first();
        abort_unless($row && ! $row->deleted, 404);

        return response()->json(['data' => $feed->serialize($row)]);
    }
}
