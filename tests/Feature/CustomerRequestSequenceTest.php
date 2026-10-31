<?php

namespace Tests\Feature;

use App\Services\DocumentSequenceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerRequestSequenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sequence_test',
            'database.connections.sequence_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);

        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedBigInteger('next_value');
            $table->timestamps();
        });
        Schema::create('quotation_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
        });
    }

    public function test_stale_counter_skips_existing_references_and_persists_next_value(): void
    {
        DB::table('document_sequences')->insert(['name' => 'customer_request', 'next_value' => 2]);
        foreach (['SC-00002', 'SC-00009', 'SC-00010', 'SC-TEST', 'SC-99999-old'] as $reference) {
            DB::table('quotation_requests')->insert(['reference' => $reference]);
        }

        $service = app(DocumentSequenceService::class);
        $this->assertSame('SC-00011', $service->formattedPeek('customer_request', 'SC-', 5));
        $this->assertSame(2, DB::table('document_sequences')->value('next_value'));

        DB::transaction(function () use ($service): void {
            $reference = $service->formattedNext('customer_request', 'SC-', 5);
            $this->assertSame('SC-00011', $reference);
            DB::table('quotation_requests')->insert(['reference' => $reference]);
        });

        $this->assertSame('SC-00012', $service->formattedNext('customer_request', 'SC-', 5));
        $this->assertSame(13, DB::table('document_sequences')->value('next_value'));
    }

    public function test_missing_counter_starts_after_existing_requests(): void
    {
        DB::table('quotation_requests')->insert(['reference' => 'SC-100000']);

        $this->assertSame('SC-100001', app(DocumentSequenceService::class)->formattedNext('customer_request', 'SC-', 5));
    }

    public function test_counter_never_moves_backwards_and_other_sequences_are_unchanged(): void
    {
        DB::table('document_sequences')->insert(['name' => 'customer_request', 'next_value' => 30]);
        DB::table('quotation_requests')->insert(['reference' => 'SC-00002']);

        $service = app(DocumentSequenceService::class);
        $this->assertSame('SC-00030', $service->formattedNext('customer_request', 'SC-', 5));
        $this->assertSame('CXP-000001', $service->formattedNext('account_payable', 'CXP-', 6));
    }

    public function test_empty_requests_start_at_one(): void
    {
        $this->assertSame('SC-00001', app(DocumentSequenceService::class)->formattedNext('customer_request', 'SC-', 5));
    }
}
