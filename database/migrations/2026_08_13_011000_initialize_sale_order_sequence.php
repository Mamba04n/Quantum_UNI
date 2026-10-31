<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $highestExisting = DB::table('sale_orders')
                ->pluck('reference')
                ->reduce(function (int $highest, mixed $reference): int {
                    return preg_match('/^OC(\d+)$/', (string) $reference, $matches)
                        ? max($highest, (int) $matches[1])
                        : $highest;
                }, 0);

            $minimumNext = $highestExisting + 1;
            $sequence = DB::table('document_sequences')
                ->where('name', 'sale_order')
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('document_sequences')->insert([
                    'name' => 'sale_order',
                    'next_value' => $minimumNext,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            }

            if ((int) $sequence->next_value < $minimumNext) {
                DB::table('document_sequences')->where('name', 'sale_order')->update([
                    'next_value' => $minimumNext,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Sequence values are operational state and must not be rewound.
    }
};
