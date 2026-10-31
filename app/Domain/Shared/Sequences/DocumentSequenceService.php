<?php

namespace App\Domain\Shared\Sequences;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentSequenceService
{
    public function peek(string $name, int $minimum = 1): int
    {
        return max($this->minimumFromExistingDocuments($name, $minimum), (int) DB::table('document_sequences')->where('name', $name)->value('next_value'));
    }

    public function next(string $name, int $minimum = 1): int
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($name, $minimum): int {
                    $row = DB::table('document_sequences')->where('name', $name)->lockForUpdate()->first();
                    $minimum = $this->minimumFromExistingDocuments($name, $minimum);
                    if (! $row) {
                        DB::table('document_sequences')->insert([
                            'name' => $name,
                            'next_value' => $minimum + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        return $minimum;
                    }

                    $value = max($minimum, (int) $row->next_value);
                    DB::table('document_sequences')->where('name', $name)->update([
                        'next_value' => $value + 1,
                        'updated_at' => now(),
                    ]);

                    return $value;
                });
            } catch (QueryException $exception) {
                if ($attempt === 2 || ! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('No fue posible reservar el consecutivo.');
    }

    public function formattedNext(string $name, string $prefix = '', int $padding = 2, int $minimum = 1): string
    {
        return $prefix.str_pad((string) $this->next($name, $minimum), $padding, '0', STR_PAD_LEFT);
    }

    public function formattedPeek(string $name, string $prefix = '', int $padding = 2, int $minimum = 1): string
    {
        return $prefix.str_pad((string) $this->peek($name, $minimum), $padding, '0', STR_PAD_LEFT);
    }

    private function minimumFromExistingDocuments(string $name, int $minimum): int
    {
        if ($name !== 'customer_request') {
            return $minimum;
        }

        // Legacy/imported requests may exist ahead of the sequence counter.
        // Read after acquiring the sequence lock when reserving a number.
        foreach (DB::table('quotation_requests')->select('reference')->where('reference', 'like', 'SC-%')->cursor() as $request) {
            if (preg_match('/^SC-(\d+)$/', $request->reference, $matches)) {
                $minimum = max($minimum, (int) $matches[1] + 1);
            }
        }

        return $minimum;
    }
}
